<script setup lang="ts">
import { Button } from '@/components/ui/button';
import FormDialogContent from '@/components/FormDialogContent.vue';
import FormHeader from '@/components/FormHeader.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import SwitchField from '@/components/SwitchField.vue';
import TextareaField from '@/components/TextareaField.vue';
import { Dialog, DialogFooter } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { TooltipProvider } from '@/components/ui/tooltip';
import type { AdditionalField, AdditionalFieldPayload, AdditionalFieldType } from '@/domain/MasterData';
import { Plus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

type AdditionalFieldForm = Omit<AdditionalField, 'default_value'> & {
    default_value: string;
};

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
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'submit', value: AdditionalFieldPayload): void;
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
    ...(props.form ?? ({} as Partial<AdditionalFieldForm>)),
    default_value: String((props.form as Partial<AdditionalField>)?.default_value ?? ''),
});
const localOptions = ref<string[]>([]);
const newOptionInput = ref('');

function normalizeOptionEntry(value: unknown): string {
    return String(value ?? '')
        .trim()
        .toUpperCase();
}

const hasOptionValues = computed(() => {
    return localOptions.value.some((value) => String(value ?? '').trim() !== '');
});

const hasDuplicateOptions = computed(() => {
    const normalized = localOptions.value.map((value) => normalizeOptionEntry(value)).filter(Boolean);
    return new Set(normalized).size !== normalized.length;
});

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
    () => props.form,
    (value) => {
        localForm.value = {
            ...defaultForm,
            ...(value ?? {}),
            options: value?.options ?? [],
            default_value: value?.default_value ?? '',
        };
        const optionsFromProps = Array.isArray(value?.options) ? (value?.options ?? []) : [];
        localOptions.value = optionsFromProps.map((option) => normalizeOptionEntry(option)).filter((option) => option !== '');
        newOptionInput.value = '';
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
    const preparedOptions = isSelectField.value
        ? localOptions.value
              .map((value) =>
                  String(value ?? '')
                      .trim()
                      .toUpperCase(),
              )
              .filter((value) => value)
        : [];

    emit('submit', buildPayload(preparedOptions));
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <FormDialogContent class="flex max-h-[85vh] max-w-[550px] flex-col px-2 py-2">
            <TooltipProvider>
                <FormHeader
                    :title="dialogType === 'create' ? 'Crear campo adicional' : 'Editar campo adicional'"
                    :description="
                        dialogType === 'create'
                            ? 'Configura un campo adicional para capturar atributos específicos del ítem en el flujo de registro de productos.'
                            : 'Actualiza las propiedades del campo adicional seleccionado para adaptarlo a los requerimientos del negocio.'
                    "
                />
                <div class="min-h-0 flex-1 space-y-6 overflow-y-auto pr-1 pt-4">
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
                                    <p v-if="defaultValueOptions.length === 0" class="text-xs text-slate-500">
                                        Agrega opciones para habilitar un valor por defecto
                                    </p>
                                </div>
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
                                    class="flex h-10 w-10 items-center justify-center rounded-full border border-border text-slate-500 transition hover:border-primary hover:bg-primary hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
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
                                        class="text-slate-400 hover:text-slate-600"
                                        @click="removeOption(index)"
                                        aria-label="Eliminar valor"
                                    >
                                        ×
                                    </button>
                                </span>
                            </div>
                            <p v-else class="text-xs text-slate-500">Sin valores registrados.</p>
                            <p v-if="hasDuplicateOptions" class="text-xs text-red-600">Cada opción debe ser única.</p>
                        </div>

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
                                    <SwitchField v-model="localForm.is_required" />
                                </div>
                            </div>
                            <div class="flex flex-col gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-medium">Activo</span>
                                    <HelpTooltip text="Habilita o pausa el campo sin eliminar su configuración." aria-label="Ayuda sobre activo" />
                                </div>
                                <div class="switch-inline">
                                    <SwitchField v-model="localForm.status" />
                                </div>
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
                        class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
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

