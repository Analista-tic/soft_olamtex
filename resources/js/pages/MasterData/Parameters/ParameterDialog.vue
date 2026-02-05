<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Dialog, DialogFooter } from '@/components/ui/dialog';
import FormHeader from '@/components/FormHeader.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import InputField from '@/components/InputField.vue';
import TextareaField from '@/components/TextareaField.vue';
import SwitchField from '@/components/SwitchField.vue';
import { Plus } from 'lucide-vue-next';

export type ParameterDialogModel = {
    id?: number | string;
    key?: string;
    label: string;
    description?: string;
    value: unknown;
    status?: boolean;
};

const props = defineProps<{
    open: boolean;
    dialogType: 'create' | 'edit' | 'view';
    form: ParameterDialogModel | null;
    loading?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'submit', value: ParameterDialogModel): void;
    (e: 'cancel'): void;
}>();

const defaultForm = {
    label: '',
    description: '',
    status: true,
    value: [],
} as ParameterDialogModel;

const localForm = ref({ ...defaultForm });
const valueList = ref<string[]>([]);
const newValueInput = ref('');
const hasValues = computed(() => valueList.value.length > 0);
const hasDuplicateOptions = computed(() => false);
const isViewMode = computed(() => props.dialogType === 'view');

watch(
    () => props.form,
    (value) => {
        if (!value) {
            localForm.value = { ...defaultForm };
            valueList.value = [];
            newValueInput.value = '';
            return;
        }

        localForm.value = {
            label: value.label ?? '',
            description: value.description ?? '',
            status: value.status ?? true,
            value: value.value ?? [],
        };
        valueList.value = parseValueList(value.value);
        newValueInput.value = '';
    },
    { immediate: true },
);
watch(
    () => props.open,
    (isOpen) => {
        if (isOpen && props.dialogType === 'create' && !props.form) {
            localForm.value = { ...defaultForm };
        }
    },
);

function parseValueList(value: unknown): string[] {
    if (!value) {
        return [];
    }

    if (Array.isArray(value)) {
        return value.map((entry) => displayEntry(entry)).filter((v): v is string => typeof v === 'string' && v.length > 0);
    }

    if (typeof value === 'string') {
        const trimmed = value.trim();
        if (trimmed.startsWith('[')) {
            try {
                const parsed = JSON.parse(trimmed);
                if (Array.isArray(parsed)) {
                    return parsed.map((entry) => displayEntry(entry)).filter((v): v is string => typeof v === 'string' && v.length > 0);
                }
            } catch {
                // ignore
            }
        }
        return [trimmed];
    }

    return [displayEntry(value)];
}

function displayEntry(entry: unknown) {
    if (entry === null || entry === undefined) {
        return '-';
    }
    if (typeof entry === 'object') {
        return (entry as Record<string, unknown>).name?.toString()
            ?? (entry as Record<string, unknown>).label?.toString()
            ?? ((entry as Record<string, unknown>).id ? String((entry as Record<string, unknown>).id) : undefined)
            ?? JSON.stringify(entry);
    }
    return String(entry);
}

function addValueEntry() {
    const trimmed = newValueInput.value.trim();
    if (!trimmed) {
        return;
    }

    valueList.value = [...valueList.value, trimmed];
    newValueInput.value = '';
}

function removeValueEntry(index: number) {
    valueList.value = valueList.value.filter((_, i) => i !== index);
}

function buildPayloadValue() {
    return valueList.value.map((entry) => ({ name: entry }));
}

function handleClose() {
    emit('update:open', false);
    emit('cancel');
}

function handleSubmit() {
    emit('submit', {
        ...localForm.value,
        value: buildPayloadValue(),
    });
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <FormDialogContent class="flex flex-col max-h-[85vh] max-w-[550px] px-2 py-2">
            <FormHeader
                :title="dialogType === 'create'
                    ? 'Crear parámetro'
                    : dialogType === 'edit'
                    ? 'Editar parámetro'
                    : 'Ver parámetro'"
                :description="dialogType === 'create'
                    ? 'Define un valor global que el sistema podrá reutilizar en distintos módulos.'
                    : dialogType === 'edit'
                    ? 'Actualiza las propiedades del parámetro seleccionado.'
                    : 'Visualiza los valores almacenados del parámetro sin editar.'"
            />
            <div class="flex-1 min-h-0 overflow-y-auto space-y-6 pr-1 pt-4">
                <div class="pt-2 space-y-4">
                    <InputField
                        v-model="localForm.label"
                        label="Nombre"
                        placeholder="Nombre visible"
                        required
                        :disabled="isViewMode"
                    />
                    <div class="space-y-2">
                        <p class="text-sm font-medium">
                            Valores
                            <span class="text-destructive">*</span>
                        </p>
                            <div v-if="!isViewMode" class="flex gap-2">
                                <InputField
                                    v-model="newValueInput"
                                    label=""
                                    placeholder="Agregar valor"
                                    @keydown.enter.prevent="addValueEntry()"
                                />
                                <button
                                    type="button"
                                    class="flex h-10 w-10 items-center justify-center rounded-full border border-border text-slate-500 transition hover:border-primary hover:bg-primary hover:text-white"
                                    @click="addValueEntry"
                                >
                                    <Plus class="h-4 w-4" />
                                </button>
                            </div>
                        <div v-if="valueList.length" class="flex flex-wrap gap-2">
                            <span
                                v-for="(item, index) in valueList"
                                :key="`value-chip-${index}`"
                                class="inline-flex items-center gap-2 rounded-full border border-border bg-white px-3 py-1 text-xs font-semibold text-slate-600"
                            >
                                {{ item }}
                                <button
                                    v-if="!isViewMode"
                                    type="button"
                                    class="text-slate-400 hover:text-slate-600"
                                    @click="removeValueEntry(index)"
                                    aria-label="Eliminar valor"
                                >
                                    ×
                                </button>
                            </span>
                        </div>
                        <p v-else class="text-xs text-slate-500">Sin valores registrados.</p>
                    </div>
                    <TextareaField
                        v-model="localForm.description"
                        label="Descripción"
                        placeholder="Opcional"
                        :rows="3"
                        :disabled="isViewMode"
                    />
                    <div class="flex flex-wrap gap-4">
                        <div class="flex w-full items-center gap-3 text-sm text-slate-600">
                            <span>Activo</span>
                            <SwitchField v-model="localForm.status!" :disabled="isViewMode" />
                        </div>
                    </div>
                </div>
            </div>
                <DialogFooter class="justify-end gap-2 mt-6">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-full border border-primary/40 bg-white px-5 py-2 text-sm font-semibold text-primary transition hover:bg-primary/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary"
                        @click="handleClose"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        :disabled="loading || hasDuplicateOptions"
                        class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary disabled:opacity-50 disabled:cursor-not-allowed"
                        @click="handleSubmit"
                    >
                        {{ dialogType === 'create' ? 'Guardar' : 'Actualizar' }}
                    </button>
                </DialogFooter>
        </FormDialogContent>
    </Dialog>
</template>
