<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import DialogActionButtons from '@/components/common/DialogActionButtons.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import FormHeader from '@/components/FormHeader.vue';
import { Dialog, DialogFooter } from '@/components/ui/dialog';
import axios from 'axios';
import { computed, ref, watch } from 'vue';
import OperationFormContent, {
    type MachineOption,
    type OperationFormData,
    type ProductSheetOption,
    type SectionOption,
} from './OperationFormContent.vue';

export type OperationRecord = {
    id: number;
    codigo: string | null;
    action: string;
    garment_part: string;
    tipo?: string | null;
    anexo?: string | null;
    section_id: number | null;
    measure: number | null;
    quantity: number | null;
    sam_base: number | null;
    product_sheet_id_reference: number | null;
    machine_id_reference: number | null;
    is_active?: boolean;
};

const props = defineProps<{
    open: boolean;
    operation: OperationRecord | null;
    sections: SectionOption[];
    actions: string[];
    garmentParts: string[];
    types: string[];
    productSheets: ProductSheetOption[];
    machines: MachineOption[];
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'saved'): void;
}>();

const localOpen = ref(props.open);
watch(() => props.open, (v) => { localOpen.value = v; });
watch(localOpen, (v) => { if (!v) emit('close'); });

const isEditing = computed(() => !!props.operation);
const isSaving = ref(false);
const error = ref('');
const formRef = ref<InstanceType<typeof OperationFormContent> | null>(null);

// Copias locales de catálogos — se actualizan cuando el usuario agrega opciones
const localActions = ref<string[]>([...props.actions]);
const localGarmentParts = ref<string[]>([...props.garmentParts]);
const localTypes = ref<string[]>([...props.types]);

// Sincronizar con props cuando cambian (ej: recarga de la página padre)
watch(() => props.actions, (v) => { localActions.value = [...v]; });
watch(() => props.garmentParts, (v) => { localGarmentParts.value = [...v]; });
watch(() => props.types, (v) => { localTypes.value = [...v]; });

/** Cuando OperationFormContent persiste una nueva opción, actualiza la copia local */
function handleCatalogUpdated(catalog: string, options: string[]) {
    if (catalog === 'operation_actions') localActions.value = options;
    else if (catalog === 'operation_garment_parts') localGarmentParts.value = options;
    else if (catalog === 'operation_types') localTypes.value = options;
}

const operationForForm = ref<OperationFormData | null>(null);
const formContentKey = ref(0);
watch(
    () => [props.open, props.operation],
    (val) => {
        const [open, op] = val as [boolean, OperationRecord | null];
        if (!open) return;
        error.value = '';
        formContentKey.value += 1;
        if (op) {
            operationForForm.value = {
                action: op.action ?? '',
                garment_part: op.garment_part ?? '',
                tipo: op.tipo ?? null,
                anexo: op.anexo ?? null,
                section_id: op.section_id,
                measure: op.measure,
                quantity: op.quantity ?? 1,
                sam_base: op.sam_base,
                product_sheet_id_reference: op.product_sheet_id_reference,
                machine_id_reference: op.machine_id_reference,
                codigo: op.codigo ?? '',
                is_active: op.is_active ?? true,
            };
        } else {
            operationForForm.value = null;
        }
    },
    { immediate: true },
);

function handleSubmit(payload: Record<string, unknown>) {
    if (payload.__error) {
        error.value = String(payload.__error);
        return;
    }
    const { __error: _, ...rest } = payload;
    isSaving.value = true;
    error.value = '';

    const data: Record<string, unknown> = {
        action: String(rest.action ?? '').trim().toUpperCase(),
        garment_part: String(rest.garment_part ?? '').trim().toUpperCase(),
        tipo: rest.tipo ? String(rest.tipo).trim().toUpperCase() : null,
        anexo: rest.anexo ? String(rest.anexo).trim().toUpperCase() : null,
        section_id: rest.section_id ?? null,
        measure: rest.measure ?? null,
        quantity: rest.quantity ?? 1,
        sam_base: rest.sam_base ?? null,
        product_sheet_id_reference: rest.product_sheet_id_reference ?? null,
        machine_id_reference: rest.machine_id_reference ?? null,
        is_active: rest.is_active === false ? false : true,
    };

    (isEditing.value && props.operation
        ? axios.put(`/produccion/operaciones/${props.operation.id}`, data)
        : axios.post('/produccion/operaciones', data)
    )
        .then(() => {
            isSaving.value = false;
            emit('saved');
            localOpen.value = false;
        })
        .catch((e: any) => {
            error.value = e?.response?.data?.message ?? 'Error al guardar la operación.';
            isSaving.value = false;
        });
}
</script>

<template>
    <AlertStack :error-message="error" @close-error="error = ''" />
    <Dialog v-model:open="localOpen">
        <FormDialogContent size="lg" class="flex max-h-[95vh] min-h-0 w-[calc(100vw-2rem)] max-w-[1120px] flex-col p-0">
            <div class="border-b border-sidebar-border/70 bg-muted/20 px-5 py-3 dark:border-sidebar-border">
                <FormHeader
                    :title="isEditing ? 'Editar operación' : 'Nueva operación'"
                    :description="
                        isEditing
                            ? 'Modifica los datos de la operación. La combinación acción + parte debe ser única.'
                            : 'Registra una nueva operación. La combinación acción + parte debe ser única.'
                    "
                />
            </div>

            <div class="flex-1 overflow-y-auto px-5 py-3" style="scrollbar-width: thin">
                <div class="mx-auto w-full max-w-[980px]">
                    <OperationFormContent
                        :key="`op-form-${formContentKey}`"
                        ref="formRef"
                        :sections="sections"
                        :actions="localActions"
                        :garment-parts="localGarmentParts"
                        :types="localTypes"
                        :product-sheets="productSheets"
                        :machines="machines"
                        :operation="operationForForm"
                        @submit="handleSubmit"
                        @catalog-updated="handleCatalogUpdated"
                    />
                </div>
            </div>

            <DialogFooter class="border-t px-5 py-3">
                <DialogActionButtons
                    cancel-label="Cancelar"
                    :submit-label="isEditing ? 'Actualizar' : 'Crear operación'"
                    :loading="isSaving"
                    @cancel="localOpen = false"
                    @submit="formRef?.submit()"
                />
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
</template>
