<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import DialogActionButtons from '@/components/common/DialogActionButtons.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import FormHeader from '@/components/FormHeader.vue';
import SelectField from '@/components/SelectField.vue';
import { Dialog, DialogFooter } from '@/components/ui/dialog';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

export type AssignableOperation = {
    id: number;
    name: string;
    raw_product_sheet_reference?: string | null;
    machine_id_reference: number | null;
};

export type ProductSheetOption = { id: number; name: string };

const props = defineProps<{
    open: boolean;
    operation: AssignableOperation | null;
    productSheets: ProductSheetOption[];
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'saved', payload?: { message?: string }): void;
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

const selectedProductSheetId = ref<number | string | null>(null);
const isSaving = ref(false);
const error = ref('');

watch(
    () => [props.open, props.operation] as const,
    ([open]) => {
        if (!open) return;
        selectedProductSheetId.value = null;
        error.value = '';
    },
    { immediate: true },
);

const productSheetOptions = computed(() => props.productSheets.map((ps) => ({ id: ps.id, name: ps.name })));

function submit() {
    if (!props.operation) return;

    if (!selectedProductSheetId.value) {
        error.value = 'Selecciona la sábana de producto.';
        return;
    }

    if (!props.operation.machine_id_reference) {
        error.value = 'Esta operación no tiene máquina asignada. Complétala primero desde "Editar" antes de asignar la ficha.';
        return;
    }

    isSaving.value = true;
    error.value = '';

    axios
        .put(`/produccion/operaciones/${props.operation.id}`, {
            product_sheet_id_reference: selectedProductSheetId.value,
            machine_id_reference: props.operation.machine_id_reference,
        })
        .then((response) => {
            isSaving.value = false;
            emit('saved', { message: response?.data?.message });
            localOpen.value = false;
        })
        .catch((e: any) => {
            error.value = e?.response?.data?.message ?? 'Error al asignar la ficha.';
            isSaving.value = false;
        });
}
</script>

<template>
    <AlertStack :error-message="error" @close-error="error = ''" />
    <Dialog v-model:open="localOpen">
        <FormDialogContent size="sm">
            <div class="border-b border-sidebar-border/70 px-5 py-3 dark:border-sidebar-border">
                <FormHeader
                    title="Asignar ficha de producto"
                    :description="`Selecciona la sábana de producto que corresponde a la operación '${operation?.name ?? ''}'.`"
                />
            </div>

            <div class="space-y-3 px-5 py-4">
                <div v-if="operation?.raw_product_sheet_reference" class="rounded-md border bg-muted/50 px-3 py-2">
                    <p class="text-[10px] font-medium uppercase tracking-widest text-muted-foreground">Código original en el archivo importado</p>
                    <p class="font-mono text-sm font-semibold">{{ operation.raw_product_sheet_reference }}</p>
                </div>

                <SelectField
                    v-model="selectedProductSheetId"
                    label="Sábana de producto"
                    placeholder="Selecciona sábana"
                    :options="productSheetOptions"
                    :searchable="true"
                    required
                />
            </div>

            <DialogFooter class="border-t px-5 py-3">
                <DialogActionButtons
                    cancel-label="Cancelar"
                    submit-label="Asignar"
                    :loading="isSaving"
                    @cancel="localOpen = false"
                    @submit="submit"
                />
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
</template>
