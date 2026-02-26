<script setup lang="ts">
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
watch(() => props.open, (v) => { localOpen.value = v; });
watch(localOpen, (v) => { if (!v) emit('close'); });

const isEditing = computed(() => !!props.operation);
const isSaving = ref(false);
const error = ref('');
const formRef = ref<InstanceType<typeof OperationFormContent> | null>(null);

const operationForForm = ref<OperationFormData | null>(null);
watch(
    () => [props.open, props.operation],
    (val) => {
        const [open, op] = val as [boolean, OperationRecord | null];
        if (!open) return;
        error.value = '';
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
    const data = {
        ...rest,
        action: (rest.action as string)?.trim().toUpperCase() ?? '',
        garment_part: (rest.garment_part as string)?.trim().toUpperCase() ?? '',
    };
    (isEditing.value && props.operation
        ? axios.put(`/produccion/operaciones/${props.operation.id}`, data)
        : axios.post('/produccion/operaciones', data)
    )
        .then(() => emit('saved'))
        .catch((e: any) => {
            error.value = e?.response?.data?.message ?? 'Error al guardar la operación.';
        })
        .finally(() => { isSaving.value = false; });
}
</script>

<template>
    <Dialog v-model:open="localOpen">
        <DialogContent class="flex max-h-[90vh] max-w-2xl flex-col p-4">
            <DialogHeader>
                <DialogTitle>{{ isEditing ? 'Editar operación' : 'Nueva operación' }}</DialogTitle>
                <DialogDescription>
                    {{ isEditing ? 'Modifica los datos de la operación. La combinación acción + parte debe ser única.' : 'Registra una nueva operación. La combinación acción + parte debe ser única.' }}
                </DialogDescription>
            </DialogHeader>

            <div class="flex-1 overflow-y-auto p-1" style="scrollbar-width: thin">
                <div v-if="error" class="mb-4 rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                    {{ error }}
                </div>
                <OperationFormContent
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
