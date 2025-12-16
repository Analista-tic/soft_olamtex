<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { Dialog, DialogFooter } from '@/components/ui/dialog';
import FormHeader from '@/components/FormHeader.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import TextareaField from '@/components/TextareaField.vue';
import SwitchField from '@/components/SwitchField.vue';
import { Button } from '@/components/ui/button';
import type { AdditionalField, AdditionalFieldType } from '@/types';
import { Trash, Plus } from 'lucide-vue-next';

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
const localOptions = ref<string[]>([]);

const hasDuplicateOptions = computed(() => {
    const normalized = localOptions.value
        .map((value) => String(value ?? '').trim().toUpperCase())
        .filter(Boolean);
    return new Set(normalized).size !== normalized.length;
});

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
        localOptions.value = optionsFromProps.length ? optionsFromProps : [];
    },
    { immediate: true },
);

watch(
    () => localForm.value.field_type,
    (value) => {
        if (value === 'select') {
            if (localOptions.value.length === 0) {
                localOptions.value = [''];
            }
        } else {
            localOptions.value = [];
        }
    },
    { immediate: true },
);

watch(
    localOptions,
    (options) => {
        const transformed = options.map((value) => String(value ?? '').toUpperCase());
        const hasDiff = transformed.some((value, index) => value !== options[index]);
        if (hasDiff) {
            localOptions.value = transformed;
        }
    },
    { deep: true },
);

function addOptionAfter(index?: number) {
    const options = [...localOptions.value];
    const insertionIndex = index !== undefined ? index + 1 : options.length;
    options.splice(insertionIndex, 0, '');
    localOptions.value = options;
}

function removeOption(index: number) {
    if (localOptions.value.length <= 1) {
        localOptions.value = [''];
        return;
    }
    const options = [...localOptions.value];
    options.splice(index, 1);
    localOptions.value = options;
}

function handleClose() {
    emit('update:open', false);
    emit('cancel');
}

function handleSubmit() {
    const preparedOptions = isSelectField.value
        ? localOptions.value
            .map((value) => String(value ?? '').trim().toUpperCase())
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
        <FormDialogContent class="space-y-6">
            <FormHeader
                :title="dialogType === 'create' ? 'Crear campo adicional' : 'Editar campo adicional'"
                :description="dialogType === 'create' ? 'Define un nuevo campo adicional para los ítems.' : 'Modifica la configuración del campo adicional seleccionado.'"
            >
                <template v-if="localForm.field_type === 'select'" #default>
                    <div class="text-sm text-gray-600 mt-1">Agrega las opciones que estarán disponibles en la lista.</div>
                </template>
            </FormHeader>
            <div class="space-y-6">
                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
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
            </div>
            <div v-if="isSelectField" class="space-y-3">
                <label class="text-sm font-medium text-slate-600">Opciones</label>
                <div class="space-y-3">
                    <div
                        v-for="(option, index) in localOptions"
                        :key="index"
                        class="relative"
                    >
                        <InputField
                            v-model="localOptions[index]"
                            placeholder="Opción"
                            class="w-full pr-10"
                            @keydown.enter.prevent="addOptionAfter(index)"
                    />
                        <button
                            v-if="index > 0 || (index === 0 && localOptions.length > 1)"
                            type="button"
                            class="absolute inset-y-0 right-0 flex items-center px-2 text-slate-500 hover:text-slate-700"
                            @click="removeOption(index)"
                            aria-label="Eliminar opción"
                        >
                            <Trash class="h-4 w-4" />
                        </button>
                    </div>
                </div>
                <p v-if="hasDuplicateOptions" class="text-xs text-red-600">
                    Cada opción debe ser única.
                </p>
                <div class="flex items-center justify-between text-xs text-muted-foreground">
                    <span>Presione ENTER para agregar opciones</span>
                    <button
                        type="button"
                        class="flex items-center gap-1 rounded-full border border-border px-3 py-1 text-xs text-primary hover:bg-primary/10"
                        @click="addOptionAfter()"
                    >
                        <Plus class="h-3.5 w-3.5" />
                        Nueva opción
                    </button>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-5">
                <SwitchField class="gap-3" v-model="localForm.is_required" label="Obligatorio" />
                <SwitchField class="gap-3" v-model="localForm.status" label="Activo" />
            </div>
            <DialogFooter class="justify-end gap-2">
                <Button variant="outline" @click="handleClose">Cancelar</Button>
                <Button variant="default" :disabled="loading || hasDuplicateOptions" @click="handleSubmit">
                    {{ dialogType === 'create' ? 'Guardar' : 'Actualizar' }}
                </Button>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
</template>
