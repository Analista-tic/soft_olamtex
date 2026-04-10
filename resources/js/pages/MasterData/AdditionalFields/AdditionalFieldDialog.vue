<script setup lang="ts">
import DatePickerField from '@/components/DatePickerField.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import FormHeader from '@/components/FormHeader.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import TextareaField from '@/components/TextareaField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogFooter } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { TooltipProvider } from '@/components/ui/tooltip';
import { mapAdditionalFieldToDialogForm } from '@/domain/MasterData';
import type { AdditionalField, AdditionalFieldDialogFormState, AdditionalFieldPayload, AdditionalFieldType } from '@/domain/MasterData';
import { normalizeTitleCaseWords } from '@/lib/textNormalizer';
import { Plus } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';

const fieldTypeOptions: Array<{ id: AdditionalFieldType; name: string }> = [
    { id: 'text', name: 'Texto' },
    { id: 'number', name: 'Numero' },
    { id: 'decimal', name: 'Numero decimal' },
    { id: 'date', name: 'Fecha' },
    { id: 'boolean', name: 'Si/No' },
    { id: 'select', name: 'Lista de opciones' },
];

const props = defineProps<{
    open: boolean;
    dialogType: 'create' | 'edit';
    form: AdditionalField;
    loading?: boolean;
    deferContent?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'submit', value: AdditionalFieldPayload): void;
    (e: 'cancel'): void;
    (e: 'loading-state', value: boolean): void;
}>();

const defaultForm: AdditionalFieldDialogFormState = {
    name: '',
    field_type: 'text',
    options: [],
    default_value: '',
    description: '',
    is_required: false,
    status: true,
};

const localForm = ref<AdditionalFieldDialogFormState>({
    ...defaultForm,
    ...mapAdditionalFieldToDialogForm(props.form),
});
const isInitializingDialog = ref(false);
const localOptions = ref<string[]>([]);
const newOptionInput = ref('');
const formError = ref('');

function normalizeOptionEntry(value: unknown): string {
    return normalizeTitleCaseWords(value);
}

const hasOptionValues = computed(() => {
    return localOptions.value.some((value) => String(value ?? '').trim() !== '');
});

const hasDuplicateOptions = computed(() => {
    const normalized = localOptions.value.map((value) => normalizeOptionEntry(value).toLocaleLowerCase('es-CO')).filter(Boolean);
    return new Set(normalized).size !== normalized.length;
});
const shouldEmitLoadingState = computed(() => props.open && isInitializingDialog.value);

const isSelectField = computed(() => localForm.value.field_type === 'select');
const defaultValueOptions = computed(() => {
    if (!isSelectField.value) return [];
    return localOptions.value
        .map((value) => String(value ?? '').trim())
        .filter((value) => value !== '')
        .map((value) => ({ id: value, name: value }));
});
const defaultValueInputType = computed((): 'text' | 'number' | 'date' => {
    if (localForm.value.field_type === 'number' || localForm.value.field_type === 'decimal') {
        return 'number';
    }
    if (localForm.value.field_type === 'date') {
        return 'date';
    }
    return 'text';
});

watch(
    [() => props.open, () => props.form],
    ([isOpen, formValue]) => {
        if (!isOpen) return;

        localForm.value = {
            ...defaultForm,
            ...mapAdditionalFieldToDialogForm(formValue),
        };

        const optionsFromProps = Array.isArray(localForm.value.options) ? localForm.value.options : [];
        localOptions.value = optionsFromProps
            .map((option) => normalizeOptionEntry(option))
            .filter((option) => option !== '');
        newOptionInput.value = '';
        formError.value = '';
    },
    { immediate: true },
);

watch(
    () => localForm.value.field_type,
    (value) => {
        if (value !== 'select') {
            localOptions.value = [];
        }
    },
    { immediate: true },
);

watch(
    localOptions,
    (options) => {
        const normalizedOptions = options.map((value) => normalizeOptionEntry(value)).filter((value) => value !== '');
        const hasDiff = normalizedOptions.length !== options.length || normalizedOptions.some((value, index) => value !== options[index]);
        if (hasDiff) {
            localOptions.value = normalizedOptions;
        }
    },
    { deep: true },
);

watch(
    () => props.open,
    async (isOpen) => {
        if (!isOpen) {
            isInitializingDialog.value = false;
            return;
        }

        isInitializingDialog.value = true;
        try {
            await nextTick();
        } finally {
            isInitializingDialog.value = false;
        }
    },
);

watch(
    shouldEmitLoadingState,
    (value) => {
        emit('loading-state', value);
    },
    { immediate: true },
);

function addOptionFromInput() {
    const candidate = normalizeOptionEntry(newOptionInput.value);
    if (!candidate) {
        return;
    }

    if (localOptions.value.includes(candidate)) {
        newOptionInput.value = '';
        return;
    }

    localOptions.value = [...localOptions.value, candidate];
    newOptionInput.value = '';
}

function removeOption(index: number) {
    const options = [...localOptions.value];
    options.splice(index, 1);
    localOptions.value = options;
}

function handleClose() {
    emit('update:open', false);
    emit('cancel');
}

function slugify(value: string): string {
    return value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

function buildPayload(preparedOptions: string[]): AdditionalFieldPayload {
    const label = String(localForm.value.name ?? '').trim();
    const existingKey = (props.form as Partial<AdditionalField> & { key?: string })?.key ?? '';
    const fallbackKey = existingKey || label;
    const generatedKey = slugify(fallbackKey || '');

    const payload: AdditionalFieldPayload = {
        id: props.form?.id,
        label,
        field_type: localForm.value.field_type,
        options: preparedOptions.length ? preparedOptions : undefined,
        default_value: localForm.value.default_value ?? undefined,
        is_required: localForm.value.is_required,
        status: localForm.value.status,
        position: localForm.value.position ?? props.form?.position,
    };

    if (generatedKey) {
        payload.key = generatedKey;
    }

    return payload;
}

function handleSubmit() {
    const preparedOptions = isSelectField.value ? localOptions.value.map((value) => normalizeOptionEntry(value)).filter((value) => value) : [];

    if (isSelectField.value && preparedOptions.length === 0) {
        formError.value = 'Debe agregar al menos una opción para los campos de tipo lista.';
        return;
    }

    formError.value = '';

    emit('submit', buildPayload(preparedOptions));
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <FormDialogContent v-if="!props.deferContent" class="flex max-h-[95vh] max-w-2xl flex-col px-2 py-2">
            <TooltipProvider>
                <FormHeader
                    :title="dialogType === 'create' ? 'Crear campo adicional' : 'Editar campo adicional'"
                    :description="
                        dialogType === 'create'
                            ? 'Configura un campo adicional para capturar atributos específicos del ítem en el flujo de registro de productos.'
                            : 'Actualiza las propiedades del campo adicional seleccionado para adaptarlo a los requerimientos del negocio.'
                    "
                />
                <div class="min-h-0 flex-1 space-y-6 overflow-y-auto px-1 pr-1 pt-4">
                    <div class="space-y-6">
                        <!-- Row: Name | Type -->
                        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                            <div class="flex flex-col gap-1">
                                <Label class="text-sm font-medium">
                                    Nombre
                                    <span class="text-red-500">*</span>
                                </Label>
                                <InputField v-model="localForm.name" label="" placeholder="Nombre del campo" required />
                            </div>
                            <div class="flex flex-col gap-1">
                                <div class="flex items-center gap-2">
                                    <Label class="text-sm font-medium">
                                        Tipo de campo
                                        <span class="text-red-500">*</span>
                                    </Label>
                                    <HelpTooltip
                                        text="Selecciona el tipo de dato que debe capturar este campo."
                                        aria-label="Ayuda sobre el tipo de campo"
                                    />
                                </div>
                                <SelectField
                                    v-model="localForm.field_type"
                                    :options="fieldTypeOptions"
                                    option-label="name"
                                    option-value="id"
                                    required
                                    class="md:!px-1"
                                />
                            </div>
                        </div>

                        <!-- Row: Default value -->
                        <div class="flex flex-col gap-2">
                            <div class="flex items-center gap-2">
                                <p class="text-sm font-medium">Valor por defecto</p>
                                <HelpTooltip
                                    text="Indica un valor que pueda usarse automáticamente si es el mismo para la mayoría de ítems."
                                    aria-label="Ayuda sobre el valor por defecto"
                                />
                            </div>
                            <div>
                                <div v-if="isSelectField" class="space-y-3">
                                    <SelectField
                                        v-model="localForm.default_value"
                                        label=""
                                        :options="defaultValueOptions"
                                        :disabled="defaultValueOptions.length === 0"
                                        option-label="name"
                                        option-value="id"
                                        placeholder="Selecciona un valor"
                                    />
                                    <p v-if="defaultValueOptions.length === 0" class="text-xs text-muted-foreground">
                                        Agrega opciones para habilitar un valor por defecto
                                    </p>
                                </div>
                                <DatePickerField
                                    v-else-if="localForm.field_type === 'date'"
                                    v-model="localForm.default_value"
                                    label=""
                                    placeholder-text="Selecciona una fecha"
                                    clearable
                                    show-today
                                />
                                <InputField
                                    v-else
                                    v-model="localForm.default_value"
                                    :type="defaultValueInputType"
                                    label=""
                                    placeholder="Valor por defecto"
                                />
                            </div>
                        </div>

                        <!-- Row: Opciones (solo para select) -->
                        <div v-if="isSelectField" class="space-y-2">
                            <p class="text-sm font-medium">
                                Valores
                                <span class="text-destructive">*</span>
                            </p>
                            <div class="flex gap-2">
                                <InputField
                                    v-model="newOptionInput"
                                    label=""
                                    placeholder="Agregar valor"
                                    @keydown.enter.prevent="addOptionFromInput"
                                />
                                <button
                                    type="button"
                                    class="flex h-10 w-10 items-center justify-center rounded-full border border-border text-muted-foreground transition hover:border-primary hover:bg-primary hover:text-primary-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                                    @click="addOptionFromInput"
                                    aria-label="Agregar valor"
                                >
                                    <Plus class="h-4 w-4" />
                                </button>
                            </div>
                            <div v-if="hasOptionValues" class="flex flex-wrap gap-2">
                                <span
                                    v-for="(option, index) in localOptions"
                                    :key="`option-chip-${index}`"
                                    class="inline-flex items-center gap-2 rounded-full border border-border bg-muted px-3 py-1 text-xs font-semibold text-muted-foreground"
                                >
                                    {{ option }}
                                    <button
                                        v-if="localOptions.length > 1"
                                        type="button"
                                        class="text-muted-foreground hover:text-muted-foreground"
                                        @click="removeOption(index)"
                                        aria-label="Eliminar valor"
                                    >
                                        ×
                                    </button>
                                </span>
                            </div>
                            <p v-else class="text-xs text-muted-foreground">Sin valores registrados.</p>
                            <p v-if="hasDuplicateOptions" class="text-xs text-red-600">Cada opción debe ser única.</p>
                        </div>
                        <p v-if="formError" class="text-sm text-red-600">{{ formError }}</p>

                        <!-- Separator -->
                        <div class="my-2 h-px bg-slate-100" />

                        <!-- Row: Description -->
                        <TextareaField label="Descripción" placeholder="Opcional" :rows="3" v-model="localForm.description" />

                        <!-- Row: Switches -->
                        <div class="grid grid-cols-2 gap-5 pt-2">
                            <div class="flex flex-col gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-medium">Obligatorio</span>
                                    <HelpTooltip
                                        text="Forzar la captura de este dato antes de guardar el ítem."
                                        aria-label="Ayuda sobre obligatorio"
                                    />
                                </div>
                                <div class="switch-inline">
                                    <button
                                        type="button"
                                        role="switch"
                                        :aria-checked="localForm.is_required"
                                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                                        :class="localForm.is_required ? 'bg-primary' : 'bg-input'"
                                        @click="localForm.is_required = !localForm.is_required"
                                    >
                                        <span
                                            class="inline-block h-4 w-4 transform rounded-full bg-white shadow-sm transition-transform"
                                            :class="localForm.is_required ? 'translate-x-6' : 'translate-x-1'"
                                        />
                                    </button>
                                </div>
                            </div>
                            <div class="flex flex-col gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-medium">Activo</span>
                                    <HelpTooltip text="Habilita o pausa el campo sin eliminar su configuración." aria-label="Ayuda sobre activo" />
                                </div>
                                <div class="switch-inline">
                                    <button
                                        type="button"
                                        role="switch"
                                        :aria-checked="localForm.status"
                                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                                        :class="localForm.status ? 'bg-primary' : 'bg-input'"
                                        @click="localForm.status = !localForm.status"
                                    >
                                        <span
                                            class="inline-block h-4 w-4 transform rounded-full bg-white shadow-sm transition-transform"
                                            :class="localForm.status ? 'translate-x-6' : 'translate-x-1'"
                                        />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <DialogFooter class="mt-6 justify-end gap-2">
                    <Button variant="outline" size="pill" @click="handleClose"> Cancelar </Button>
                    <button
                        type="button"
                        :disabled="loading || hasDuplicateOptions"
                        class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-5 py-2 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        @click="handleSubmit"
                    >
                        {{ dialogType === 'create' ? 'Guardar' : 'Actualizar' }}
                    </button>
                </DialogFooter>
            </TooltipProvider>
        </FormDialogContent>
    </Dialog>
</template>

<style scoped>
.help-tooltip-content {
    background-color: #fff;
    border: 1px solid #cbd5f5;
    color: #0f172a;
    padding: 0.4rem 0.85rem;
    font-size: 0.85rem;
    border-radius: 0.75rem;
    box-shadow:
        0 20px 25px -5px rgba(15, 23, 42, 0.2),
        0 8px 10px -6px rgba(15, 23, 42, 0.1);
    position: relative;
}

.help-tooltip-content::after,
.help-tooltip-content::before {
    content: '';
    position: absolute;
    left: 50%;
    transform: translateX(-50%);
    width: 0;
    height: 0;
    border-left: 0.45rem solid transparent;
    border-right: 0.45rem solid transparent;
    bottom: -0.65rem;
}

.help-tooltip-content::after {
    border-top: 0.45rem solid #fff;
}

.help-tooltip-content::before {
    border-top: 0.45rem solid #cbd5f5;
    bottom: -0.72rem;
}

.switch-inline {
    position: relative;
}

.switch-inline label {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}
</style>
