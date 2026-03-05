<script setup lang="ts">
import { Button } from '@/components/ui/button';
import FormDialogContent from '@/components/FormDialogContent.vue';
import FormHeader from '@/components/FormHeader.vue';
import InputField from '@/components/InputField.vue';
import SwitchField from '@/components/SwitchField.vue';
import TextareaField from '@/components/TextareaField.vue';
import { Dialog, DialogFooter } from '@/components/ui/dialog';
import { Plus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

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
// const hasValues = computed(() => valueList.value.length > 0);
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
        return (
            (entry as Record<string, unknown>).name?.toString() ??
            (entry as Record<string, unknown>).label?.toString() ??
            ((entry as Record<string, unknown>).id ? String((entry as Record<string, unknown>).id) : undefined) ??
            JSON.stringify(entry)
        );
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
        <FormDialogContent class="flex max-h-[95vh] max-w-[550px] flex-col px-2 py-2">
            <FormHeader
                :title="dialogType === 'create' ? 'Crear parámetro' : dialogType === 'edit' ? 'Editar parámetro' : 'Ver parámetro'"
                :description="
                    dialogType === 'create'
                        ? 'Define un valor global que el sistema podrá reutilizar en distintos módulos.'
                        : dialogType === 'edit'
                          ? 'Actualiza las propiedades del parámetro seleccionado.'
                          : 'Visualiza los valores almacenados del parámetro sin editar.'
                "
            />
            <div class="min-h-0 flex-1 space-y-6 overflow-y-auto pr-1 pt-4">
                <div class="space-y-4 pt-2">
                    <InputField v-model="localForm.label" label="Nombre" placeholder="Nombre visible" required :disabled="isViewMode" />
                    <div class="space-y-2">
                        <p class="text-sm font-medium">
                            Valores
                            <span class="text-destructive">*</span>
                        </p>
                        <div v-if="!isViewMode" class="flex gap-2">
                            <InputField v-model="newValueInput" label="" placeholder="Agregar valor" @keydown.enter.prevent="addValueEntry()" />
                            <button
                                type="button"
                                class="flex h-10 w-10 items-center justify-center rounded-full border border-border text-muted-foreground transition hover:border-primary hover:bg-primary hover:text-primary-foreground"
                                @click="addValueEntry"
                            >
                                <Plus class="h-4 w-4" />
                            </button>
                        </div>
                        <div v-if="valueList.length" class="flex flex-wrap gap-2">
                            <!-- <div v-if="!isViewMode" class="flex gap-2">
                                <InputField
                                    v-model="newValueInput"
                                    label=""
                                    placeholder="Agregar valor"
                                    @keydown.enter.prevent="addValueEntry()"
                                />
                                <button
                                    type="button"
                                    class="flex h-10 w-10 items-center justify-center rounded-full border border-border text-muted-foreground transition hover:border-primary hover:bg-primary hover:text-primary-foreground"
                                    @click="addValueEntry"
                                >
                                    <Plus class="h-4 w-4" />
                                </button>
                            </div>
                        <div v-if="hasValues" class="flex flex-wrap gap-2"> -->
                            <span
                                v-for="(item, index) in valueList"
                                :key="`value-chip-${index}`"
                                class="inline-flex items-center gap-2 rounded-full border border-border bg-muted px-3 py-1 text-xs font-semibold text-foreground"
                            >
                                {{ item }}
                                <button
                                    v-if="!isViewMode"
                                    type="button"
                                    class="text-muted-foreground transition-colors hover:text-foreground"
                                    @click="removeValueEntry(index)"
                                    aria-label="Eliminar valor"
                                >
                                    ×
                                </button>
                            </span>
                        </div>
                        <p v-else class="text-xs text-muted-foreground">Sin valores registrados.</p>
                    </div>
                    <TextareaField v-model="localForm.description" label="Descripción" placeholder="Opcional" :rows="3" :disabled="isViewMode" />
                    <div class="flex flex-wrap gap-4">
                        <div class="flex w-full items-center gap-3 text-sm text-foreground/80">
                            <span>Activo</span>
                            <SwitchField v-model="localForm.status!" :disabled="isViewMode" />
                        </div>
                    </div>
                </div>
            </div>
            <DialogFooter class="mt-6 justify-end gap-2">
                <Button variant="outline" size="pill" @click="handleClose">
                    Cancelar
                </Button>
                <button
                    type="button"
                    :disabled="loading || hasDuplicateOptions"
                    class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-5 py-2 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:cursor-not-allowed disabled:opacity-50"
                    @click="handleSubmit"
                >
                    {{ dialogType === 'create' ? 'Guardar' : 'Actualizar' }}
                </button>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
</template>
