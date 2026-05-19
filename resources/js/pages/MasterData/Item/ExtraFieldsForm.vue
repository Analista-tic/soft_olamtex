<script setup lang="ts">
import DeleteButton from '@/components/common/DeleteButton.vue';
import DatePickerField from '@/components/DatePickerField.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import TextareaField from '@/components/TextareaField.vue';
import { Button } from '@/components/ui/button';
import type { AdditionalField, ExtraFields } from '@/domain/MasterData';
import type { Option } from '@/domain/shared/types';
import { Plus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        extraFieldNames: string[];
        extraFields: ExtraFields;
        readonly?: boolean;
        showLabel?: boolean;
        fieldOptions?: Record<string, string[]>;
        canCreateAdditionalField?: boolean;
        // Metadata de campos adicionales (vista de AdditionalField)
        additionalFieldsMeta?: AdditionalField[];
    }>(),
    {
        readonly: false,
        showLabel: true,
        fieldOptions: () => ({}),
        canCreateAdditionalField: true,
        additionalFieldsMeta: () => [],
    },
);
const emit = defineEmits<{
    (e: 'update:extraFields', value: ExtraFields): void;
    (e: 'create-additional-field'): void;
}>();

const selectedExtraField = ref('');
const extraFieldValue = ref('');

const localExtraFields = ref<ExtraFields>({ ...props.extraFields });
const extraFieldEntries = computed(() => Object.entries(localExtraFields.value));
const hasExtraFields = computed(() => extraFieldEntries.value.length > 0);

// Construir lista de campos disponibles (nombres simples + metadatos)
const availableFieldNames = computed(() => {
    const names = new Set<string>();
    (props.extraFieldNames ?? []).forEach((n) => names.add(n));
    (props.additionalFieldsMeta ?? []).forEach((m) => names.add(m.name));
    // mantener orden: primero extraFieldNames, luego metadatos adicionales
    const ordered: string[] = [];
    (props.extraFieldNames ?? []).forEach((n) => {
        if (names.has(n)) {
            ordered.push(n);
            names.delete(n);
        }
    });
    (props.additionalFieldsMeta ?? []).forEach((m) => {
        if (names.has(m.name)) {
            ordered.push(m.name);
            names.delete(m.name);
        }
    });
    Array.from(names).forEach((n) => ordered.push(n));
    return ordered;
});

const addableFieldNames = computed(() => availableFieldNames.value.filter((name) => !localExtraFields.value[name]));

const fieldSelectOptions = computed(() => addableFieldNames.value.map((name) => ({ id: name, name })));

// dynamicFieldOptions incluye catÃ¡logos, props.fieldOptions y opciones desde metadatos
const dynamicFieldOptions = computed(() => {
    const base = { ...(props.fieldOptions ?? {}) } as Record<string, string[]>;
    for (const meta of props.additionalFieldsMeta ?? []) {
        if (Array.isArray(meta.options) && meta.options.length) {
            base[meta.name] = meta.options as string[];
        }
    }
    return base;
});

const additionalFieldsMetaMap = computed(() => {
    const map: Record<string, AdditionalField> = {};
    (props.additionalFieldsMeta ?? []).forEach((meta) => {
        if (meta?.name) {
            map[meta.name] = meta;
        }
    });
    return map;
});

const formatFieldOptions = (field: string): Option[] =>
    (dynamicFieldOptions.value[field] ?? []).map((option) => ({
        id: option,
        name: option,
    }));

const resolveFieldInputType = (field: string): 'text' | 'number' | 'date' => {
    const meta = additionalFieldsMetaMap.value[field];
    if (meta?.field_type === 'date') return 'date';
    if (meta?.field_type === 'number' || meta?.field_type === 'decimal') return 'number';
    return 'text';
};

function shouldUseSelect(field: string): boolean {
    const meta = additionalFieldsMetaMap.value[field];
    return meta?.field_type === 'select';
}

function shouldUseBoolean(field: string): boolean {
    const meta = additionalFieldsMetaMap.value[field];
    return meta?.field_type === 'boolean';
}

function shouldUseTextarea(field: string): boolean {
    const meta = additionalFieldsMetaMap.value[field];
    return meta?.field_type === 'textarea';
}

function shouldUseDate(field: string): boolean {
    const meta = additionalFieldsMetaMap.value[field];
    return meta?.field_type === 'date';
}

function resolveInputTypeByMeta(meta?: AdditionalField): 'text' | 'number' | 'date' {
    if (!meta) return 'text';
    if (meta.field_type === 'date') return 'date';
    if (meta.field_type === 'number' || meta.field_type === 'decimal') return 'number';
    return 'text';
}

// FunciÃ³n para inicializar campos obligatorios
function initializeRequiredFields() {
    const requiredFields = (props.additionalFieldsMeta ?? []).filter((f) => f.is_required);
    let hasChanges = false;
    const updated = { ...localExtraFields.value };

    requiredFields.forEach((field) => {
        if (!updated[field.name]) {
            // Agregar campo con valor por defecto o vacío
            updated[field.name] = field.default_value !== undefined && field.default_value !== null ? String(field.default_value) : '';
            hasChanges = true;
        }
    });

    if (hasChanges) {
        localExtraFields.value = updated;
    }
}

// Mantener sincronizaciÃ³n solo cuando cambian las props externamente
watch(
    () => props.extraFields,
    (val) => {
        if (JSON.stringify(val) !== JSON.stringify(localExtraFields.value)) {
            localExtraFields.value = { ...val };
            initializeRequiredFields();
        }
    },
);

// Inicializar campos obligatorios cuando cambien los metadatos
watch(
    () => props.additionalFieldsMeta,
    () => {
        initializeRequiredFields();
    },
    { immediate: true, deep: true },
);

// Emitir cambios solo cuando localExtraFields cambia realmente
watch(
    localExtraFields,
    (val) => {
        emit('update:extraFields', { ...val });
    },
    { deep: true },
);

watch(selectedExtraField, () => {
    extraFieldValue.value = '';
    // precargar default_value desde metadatos si existe
    const meta = (props.additionalFieldsMeta ?? []).find((m) => m.name === selectedExtraField.value);
    if (meta && meta.default_value !== undefined && meta.default_value !== null) {
        extraFieldValue.value = String(meta.default_value ?? '');
    }
});

function addExtraField() {
    const field = selectedExtraField.value;
    if (!field) return;

    const value = extraFieldValue.value.trim?.() ?? extraFieldValue.value;
    if (!value || localExtraFields.value[field]) return;

    localExtraFields.value[field] = value;
    selectedExtraField.value = '';
    extraFieldValue.value = '';
}

function removeExtraField(field: string) {
    // No permitir eliminar campos obligatorios
    const meta = additionalFieldsMetaMap.value[field];
    if (meta?.is_required) {
        return;
    }

    if (localExtraFields.value[field] !== undefined) {
        const updated = { ...localExtraFields.value };
        delete updated[field];
        localExtraFields.value = updated;
    }
}

function isAddDisabled() {
    const field = selectedExtraField.value;
    if (!field) return true;
    if (localExtraFields.value[field]) return true;
    if (field === 'Campo adicional') {
        return !extraFieldValue.value.trim();
    }
    return !extraFieldValue.value.trim();
}
</script>

<template>
    <div>
        <label v-if="props.showLabel" class="mb-2 block font-semibold"> Campos Adicionales </label>

        <div v-if="!props.readonly" class="flex flex-col items-end gap-2 md:flex-row">
            <div class="flex-1">
                <SelectField
                    v-model="selectedExtraField"
                    label="Campo"
                    :options="fieldSelectOptions"
                    placeholder="Selecciona un campo"
                    :disabled="props.readonly"
                >
                    <template v-if="props.canCreateAdditionalField" #header>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            class="w-full justify-center gap-1 border-border text-xs text-muted-foreground shadow-none hover:bg-muted/70"
                            @click.stop.prevent="emit('create-additional-field')"
                        >
                            <Plus class="h-4 w-4" />
                            Nuevo campo adicional
                        </Button>
                    </template>
                </SelectField>
            </div>
            <div class="flex-1">
                <template v-if="selectedExtraField">
                    <SelectField
                        v-if="additionalFieldsMetaMap[selectedExtraField]?.field_type === 'select'"
                        label="Valor"
                        v-model="extraFieldValue"
                        :options="(dynamicFieldOptions[selectedExtraField] ?? []).map((o) => ({ id: o, name: o }))"
                        placeholder="Seleccione valor"
                    />
                    <SelectField
                        v-else-if="additionalFieldsMetaMap[selectedExtraField]?.field_type === 'boolean'"
                        label="Valor"
                        v-model="extraFieldValue"
                        :options="[
                            { id: 'true', name: 'SÃ­' },
                            { id: 'false', name: 'No' },
                        ]"
                        option-label="name"
                        option-value="id"
                        placeholder="Selecciona"
                        :searchable="false"
                    />
                    <DatePickerField
                        v-else-if="additionalFieldsMetaMap[selectedExtraField]?.field_type === 'date'"
                        label="Valor"
                        v-model="extraFieldValue"
                        placeholder-text="Selecciona una fecha"
                        clearable
                    />
                    <TextareaField
                        v-else-if="additionalFieldsMetaMap[selectedExtraField]?.field_type === 'textarea'"
                        label="Valor"
                        v-model="extraFieldValue"
                        placeholder="Ingresa un valor"
                        :rows="3"
                    />
                    <InputField
                        v-else
                        label="Valor"
                        :type="resolveInputTypeByMeta(additionalFieldsMetaMap[selectedExtraField])"
                        v-model="extraFieldValue"
                        placeholder="Ingresa un valor"
                    />
                </template>
                <InputField
                    v-else
                    label="Valor"
                    type="text"
                    v-model="extraFieldValue"
                    :disabled="true"
                    placeholder="Selecciona un campo primero"
                />
            </div>
            <Button
                type="button"
                variant="outline"
                size="pill"
                class="h-9 border-primary/40 bg-background px-4 text-xs font-semibold text-primary hover:bg-primary/10 hover:text-primary"
                :disabled="isAddDisabled()"
                @click="addExtraField"
            >
                Añadir
            </Button>
        </div>

        <div class="mt-4 space-y-3">
            <div
                v-for="[field] in extraFieldEntries"
                :key="field"
                class="flex flex-col gap-3 rounded-2xl border border-border bg-card/80 px-4 py-4 shadow-sm sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex-1 space-y-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        {{ field }}
                        <span v-if="additionalFieldsMetaMap[field]?.is_required" class="text-red-500"> * </span>
                    </p>
                    <div>
                        <SelectField
                            v-if="shouldUseSelect(field)"
                            v-model="localExtraFields[field]"
                            label=""
                            :options="formatFieldOptions(field)"
                            option-label="name"
                            option-value="id"
                            placeholder="Selecciona un valor"
                            :disabled="props.readonly"
                            :searchable="false"
                        />
                        <SelectField
                            v-else-if="shouldUseBoolean(field)"
                            v-model="localExtraFields[field]"
                            label=""
                            :options="[
                                { id: 'true', name: 'SÃ­' },
                                { id: 'false', name: 'No' },
                            ]"
                            option-label="name"
                            option-value="id"
                            placeholder="Selecciona"
                            :disabled="props.readonly"
                            :searchable="false"
                        />
                        <DatePickerField
                            v-else-if="shouldUseDate(field)"
                            v-model="localExtraFields[field]"
                            label=""
                            placeholder-text="Selecciona una fecha"
                            :disabled="props.readonly"
                            clearable
                        />
                        <TextareaField
                            v-else-if="shouldUseTextarea(field)"
                            v-model="localExtraFields[field]"
                            label=""
                            placeholder="Ingresa un valor"
                            :readonly="props.readonly"
                            :rows="3"
                        />
                        <InputField
                            v-else
                            v-model="localExtraFields[field]"
                            label=""
                            placeholder="Ingresa un valor"
                            :readonly="props.readonly"
                            :type="resolveFieldInputType(field)"
                        />
                    </div>
                </div>
                <DeleteButton
                    v-if="!props.readonly && !additionalFieldsMetaMap[field]?.is_required"
                    class="ml-auto pt-4 text-gray-400 transition hover:text-red-500"
                    @click="removeExtraField(field)"
                    title="Quitar"
                />
            </div>
            <div
                v-if="!hasExtraFields"
                class="rounded-2xl border border-dashed border-border bg-muted px-4 py-3 text-center text-sm font-medium text-muted-foreground"
            >
                No hay campos adicionales agregados.
            </div>
        </div>
    </div>
</template>
