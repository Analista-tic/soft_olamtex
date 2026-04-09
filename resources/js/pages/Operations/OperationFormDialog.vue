<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import DialogActionButtons from '@/components/common/DialogActionButtons.vue';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
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
    productSheets: ProductSheetOption[];
    machines: MachineOption[];
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'saved'): void;
}>();

const localOpen = ref(props.open);
watch(
    () => props.open,
    (v) => {
        localOpen.value = v;
    },
);
watch(localOpen, (v) => {
    if (!v) emit('close');
});

const isEditing = computed(() => !!props.operation);
const isSaving = ref(false);
const error = ref('');
const formRef = ref<InstanceType<typeof OperationFormContent> | null>(null);

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

    // Construir el objeto de datos, normalizando campos opcionales
    const data: Record<string, unknown> = {
        action: String(rest.action ?? '')
            .trim()
            .toUpperCase(),
        garment_part: String(rest.garment_part ?? '')
            .trim()
            .toUpperCase(),
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
            // Cerrar el diálogo después de guardar exitosamente
            localOpen.value = false;
        })
        .catch((e: any) => {
            error.value = e?.response?.data?.message ?? 'Error al guardar la operación.';
            isSaving.value = false;
        });
}
</script>

<template>
    <AlertStack
        :error-message="error"
        @close-error="error = ''"
    />
    <Dialog v-model:open="localOpen">
        <DialogContent class="flex flex-col max-h-[95vh] max-w-2xl px-2 py-2">
            <DialogHeader class="space-y-1 border-b border-sidebar-border/70 bg-muted/20 px-5 py-4 dark:border-sidebar-border">
                <DialogTitle>{{ isEditing ? 'Editar operación' : 'Nueva operación' }}</DialogTitle>
                <DialogDescription>
                    {{
                        isEditing
                            ? 'Modifica los datos de la operación. La combinación acción + parte debe ser única.'
                            : 'Registra una nueva operación. La combinación acción + parte debe ser única.'
                    }}
                </DialogDescription>
            </DialogHeader>

            <div class="flex-1 overflow-y-auto p-1" style="scrollbar-width: thin">
                <OperationFormContent
                    :key="`op-form-${formContentKey}`"
                    ref="formRef"
                    :sections="sections"
                    :actions="actions"
                    :garment-parts="garmentParts"
                    :product-sheets="productSheets"
                    :machines="machines"
                    :operation="operationForForm"
                    @submit="handleSubmit"
                />
            </div>

            <DialogFooter class="mt-4 border-t pt-4">
                <DialogActionButtons
                    cancel-label="Cancelar"
                    :submit-label="isEditing ? 'Actualizar' : 'Crear operación'"
                    :loading="isSaving"
                    @cancel="localOpen = false"
                    @submit="formRef?.submit()"
                />
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
