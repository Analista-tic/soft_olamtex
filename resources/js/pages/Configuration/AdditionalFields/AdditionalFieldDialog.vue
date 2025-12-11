<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import TextareaField from '@/components/TextareaField.vue';
import SwitchField from '@/components/SwitchField.vue';
import { Button } from '@/components/ui/button';
import type { AdditionalField, AdditionalFieldType } from '@/types';

type AdditionalFieldForm = Omit<AdditionalField, 'default_value'> & {
    default_value: string;
};

const fieldTypeOptions: Array<{ id: AdditionalFieldType; name: string }> = [
    { id: 'text', name: 'Texto' },
    { id: 'number', name: 'Número' },
    { id: 'decimal', name: 'Número decimal' },
    { id: 'date', name: 'Fecha' },
    { id: 'boolean', name: 'Si/No' },
    { id: 'select', name: 'Lista de opciones' },
];

const props = defineProps<{
    open: boolean;
    dialogType: 'create' | 'edit';
    form: AdditionalField;
    loading?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'submit', value: AdditionalField): void;
    (e: 'cancel'): void;
}>();

const defaultForm: AdditionalFieldForm = {
    name: '',
    field_type: 'text',
    options: [],
    default_value: '',
    description: '',
    is_required: false,
    status: true,
};

// Inicializar localForm de forma segura convirtiendo `default_value` a string
const localForm = ref<AdditionalFieldForm>({
    ...defaultForm,
    ...(props.form ?? {} as Partial<AdditionalFieldForm>),
    default_value: String((props.form as Partial<AdditionalField>)?.default_value ?? ''),
});
const optionsRaw = ref('');

const isSelectField = computed(() => localForm.value.field_type === 'select');

watch(
    () => props.form,
    (value) => {
        localForm.value = {
            ...defaultForm,
            ...(value ?? {}),
            options: value?.options ?? [],
            default_value: value?.default_value ?? '',
        };
        const optionsFromProps = Array.isArray(value?.options) ? (value?.options ?? []) : [];
        optionsRaw.value = optionsFromProps.join('\n');
    },
    { immediate: true },
);

function handleClose() {
    emit('update:open', false);
    emit('cancel');
}

function handleSubmit() {
    const preparedOptions = isSelectField.value
        ? optionsRaw.value
            .split(/\r?\n/)
            .map((value) => value.trim())
            .filter((value) => value)
        : [];

    emit('submit', {
        ...localForm.value,
        options: preparedOptions.length ? preparedOptions : undefined,
    });
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="space-y-4">
            <DialogHeader>
                <DialogTitle>
                    {{ dialogType === 'create' ? 'Crear campo adicional' : 'Editar campo adicional' }}
                </DialogTitle>
            </DialogHeader>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <InputField v-model="localForm.name" label="Nombre" placeholder="Nombre del campo" required />
                <SelectField
                    v-model="localForm.field_type"
                    label="Tipo de campo"
                    :options="fieldTypeOptions"
                    option-label="name"
                    option-value="id"
                    required
                />
            </div>
            <InputField
                v-model="localForm.default_value"
                label="Valor por defecto"
                placeholder="Valor por defecto"
            />
            <TextareaField
                label="Descripcion"
                placeholder="Opcional"
                :rows="3"
                v-model="localForm.description"
            />
            <TextareaField
                v-if="isSelectField"
                v-model="optionsRaw"
                label="Opciones (una por linea)"
                placeholder="Escribe cada opcion en una linea nueva"
                :rows="4"
            />
            <div class="grid grid-cols-2 gap-4">
                <SwitchField v-model="localForm.is_required" label="Obligatorio" />
                <SwitchField v-model="localForm.status" label="Activo" />
            </div>
            <DialogFooter class="justify-end gap-2">
                <Button variant="outline" @click="handleClose">Cancelar</Button>
                <Button variant="default" :disabled="loading" @click="handleSubmit">
                    {{ dialogType === 'create' ? 'Guardar' : 'Actualizar' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
