<script setup lang="ts">
import axios from 'axios';
import { onMounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogHeader, DialogTitle, DialogFooter, DialogDescription } from '@/components/ui/dialog';
import FormDialogContent from '@/components/FormDialogContent.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import type { RetentionForm } from '@/domain/Finance';

type AccountOption = {
    id: number;
    name: string;
};

const props = defineProps<{
    open: boolean;
    dialogType: 'create' | 'edit';
    form: RetentionForm;
    loading: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'submit', value: RetentionForm): void;
    (e: 'cancel'): void;
}>();

const normalizeAccountValue = (value: unknown): number | null => {
    if (value === null || value === undefined || value === '') return null;
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
};

const normalizeForm = (source: RetentionForm): RetentionForm => ({
    ...source,
    account_id: normalizeAccountValue(source.account_id),
});

const accountOptions = ref<AccountOption[]>([]);
const localForm = ref<RetentionForm>(normalizeForm(props.form));

watch(
    () => props.form,
    (newVal) => {
        localForm.value = normalizeForm(newVal);
    },
);

onMounted(async () => {
    const response = await axios.get<{ data?: AccountOption[] } | AccountOption[]>(
        '/finanzas/catalogo-cuentas/options',
        {
            params: { allows_entries: true },
        },
    );

    accountOptions.value = Array.isArray(response.data)
        ? response.data
        : Array.isArray(response.data.data)
          ? response.data.data
          : [];
});

function handleClose() {
    emit('update:open', false);
    emit('cancel');
}

function handleSubmit() {
    emit('submit', {
        ...localForm.value,
        name: localForm.value.name.trim(),
        type: localForm.value.type.trim(),
        description: localForm.value.description.trim(),
        account_id: localForm.value.account_id ?? null,
        percent: Number(localForm.value.percent) || 0,
    });
}

const retentionTypeOptions = [
    { id: 'IVA', name: 'Retencion de IVA' },
    { id: 'FUENTE', name: 'Retencion en la fuente' },
    { id: 'ICA', name: 'Retencion de Industria y Comercio' },
    { id: 'CREE', name: 'Retencion de CREE' },
    { id: 'OTRO', name: 'Otro tipo de retencion' },
];
</script>

<template>
    <Dialog :open="props.open" @update:open="emit('update:open', $event)">
        <FormDialogContent class="flex flex-col max-h-[95vh] max-w-2xl px-2 py-2">
            <DialogHeader class="space-y-1 border-b border-sidebar-border/70 bg-muted/20 px-5 py-4 dark:border-sidebar-border">
                <DialogTitle>
                    {{ props.dialogType === 'create' ? 'Crear Retencion' : 'Editar Retencion' }}
                </DialogTitle>
                <DialogDescription>
                    Completa los campos para {{ props.dialogType === 'create' ? 'crear' : 'editar' }} una retencion.
                </DialogDescription>
            </DialogHeader>
            <div class="space-y-4 pt-2">
                <InputField label="Nombre" v-model="localForm.name" placeholder="Nombre de la retencion" required />
                <InputField
                    label="Porcentaje"
                    :model-value="localForm.percent"
                    type="number"
                    min="0"
                    max="100"
                    required
                    @update:modelValue="(value) => (localForm.percent = Number(value) || 0)"
                />
                <SelectField
                    v-model="localForm.type"
                    label="Tipo"
                    :options="retentionTypeOptions"
                    required
                />
                <InputField label="Descripción" v-model="localForm.description" placeholder="Descripción" />
                <SelectField
                    v-model="localForm.account_id"
                    label="Cuenta contable de retención"
                    :options="accountOptions"
                    placeholder="Selecciona una cuenta contable"
                />
                <SelectField
                    v-model="localForm.status"
                    label="Estado"
                    :options="[{ id: 'active', name: 'Activa' }, { id: 'inactive', name: 'Inactiva' }]"
                    required
                />
            </div>
            <DialogFooter class="flex justify-end gap-2">
                <Button variant="outline" size="pill" @click="handleClose">
                    Cancelar
                </Button>
                <button
                    type="button"
                    :disabled="props.loading"
                    class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-5 py-2 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary disabled:opacity-50 disabled:cursor-not-allowed"
                    @click="handleSubmit"
                >
                    {{ props.dialogType === 'create' ? 'Guardar' : 'Actualizar' }}
                </button>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
</template>
