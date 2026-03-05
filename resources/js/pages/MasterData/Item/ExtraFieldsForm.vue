<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import DeleteButton from '@/components/common/DeleteButton.vue';
import type { ExtraFields, AdditionalField } from '@/domain/MasterData';
import type { Option } from '@/domain/shared/types';
import { Plus } from 'lucide-vue-next';

const props = withDefaults(defineProps<{
    extraFieldNames: string[],
    extraFields: ExtraFields,
    readonly?: boolean;
    showLabel?: boolean;
    fieldOptions?: Record<string, string[]>;
    // Metadata de campos adicionales (vista de AdditionalField)
    additionalFieldsMeta?: AdditionalField[];
}>(), {
    readonly: false,
    showLabel: true,
    fieldOptions: () => ({}),
    additionalFieldsMeta: () => [],
});
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

const addableFieldNames = computed(() =>
    availableFieldNames.value.filter((name) => !localExtraFields.value[name]),
);

const fieldSelectOptions = computed(() =>
    addableFieldNames.value.map((name) => ({ id: name, name })),
);

// dynamicFieldOptions incluye catálogos, props.fieldOptions y opciones desde metadatos
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

// Función para inicializar campos obligatorios
function initializeRequiredFields() {
    const requiredFields = (props.additionalFieldsMeta ?? []).filter(f => f.is_required);
    let hasChanges = false;
    const updated = { ...localExtraFields.value };
    
    requiredFields.forEach(field => {
        if (!updated[field.name]) {
            // Agregar campo con valor por defecto o vacío
            updated[field.name] = field.default_value !== undefined && field.default_value !== null 
                ? String(field.default_value) 
                : '';
            hasChanges = true;
        }
    });
    
    if (hasChanges) {
        localExtraFields.value = updated;
    }
}

// Mantener sincronización solo cuando cambian las props externamente
watch(
    () => props.extraFields,
    (val) => {
        if (JSON.stringify(val) !== JSON.stringify(localExtraFields.value)) {
            localExtraFields.value = { ...val };
            initializeRequiredFields();
        }
    }
);

// Inicializar campos obligatorios cuando cambien los metadatos
watch(
    () => props.additionalFieldsMeta,
    () => {
        initializeRequiredFields();
    },
    { immediate: true, deep: true }
);

// Emitir cambios solo cuando localExtraFields cambia realmente
watch(
    localExtraFields,
    (val) => {
        emit('update:extraFields', { ...val });
    },
    { deep: true }
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
    if (field === 'Campo adicional') {
        if (extraFieldValue.value.trim()) {
            localExtraFields.value['Campo adicional'] = extraFieldValue.value.trim();
            selectedExtraField.value = '';
        }
    } else {
        // verificar metadata del campo
        const meta = (props.additionalFieldsMeta ?? []).find((m) => m.name === field);
        if (meta) {
            // para campos select, el valor viene de extraFieldValue
            if (meta.field_type === 'select') {
                if (extraFieldValue.value && !localExtraFields.value[field]) {
                    localExtraFields.value[field] = extraFieldValue.value;
                    selectedExtraField.value = '';
                }
            } else if (meta.field_type === 'number') {
                if (extraFieldValue.value && !localExtraFields.value[field]) {
                    localExtraFields.value[field] = extraFieldValue.value;
                    selectedExtraField.value = '';
                }
            } else if (meta.field_type === 'date') {
                if (extraFieldValue.value && !localExtraFields.value[field]) {
                    localExtraFields.value[field] = extraFieldValue.value;
                    selectedExtraField.value = '';
                }
            } else {
                if (extraFieldValue.value.trim() && !localExtraFields.value[field]) {
                    localExtraFields.value[field] = extraFieldValue.value.trim();
                    selectedExtraField.value = '';
                }
            }
        } else {
            // campo genérico o catálogo
            if (extraFieldValue.value.trim() && !localExtraFields.value[field]) {
                localExtraFields.value[field] = extraFieldValue.value.trim();
                selectedExtraField.value = '';
            }
        }
    }
    // Limpiar valores auxiliares
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
        <label
            v-if="props.showLabel"
            class="mb-2 block font-semibold"
        >
            Campos Adicionales
        </label>

        <div
            v-if="!props.readonly"
            class="flex flex-col items-end gap-2 md:flex-row"
        >
            <div class="flex-1">
                <SelectField
                    v-model="selectedExtraField"
                    label="Campo"
                    :options="fieldSelectOptions"
                    placeholder="Selecciona un campo"
                    :disabled="props.readonly"
                >
                    <template #header>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            class="w-full justify-center gap-1 text-xs text-muted-foreground border-border shadow-none hover:bg-muted/70"
                            @click.stop.prevent="emit('create-additional-field')"
                        >
                            <Plus class="h-4 w-4" />
                            Nuevo campo adicional
                        </Button>
                    </template>
                </SelectField>
            </div>
            <div class="flex-1">
                <InputField
                    v-if="selectedExtraField === 'Campo adicional'"
                    label="Valor"
                    type="text"
                    v-model="extraFieldValue"
                    :disabled="!selectedExtraField"
                    placeholder="Ingrese valor"
                />
                <SelectField
                    v-else-if="selectedExtraField && dynamicFieldOptions[selectedExtraField]"
                    label="Valor"
                    v-model="extraFieldValue"
                    :options="(dynamicFieldOptions[selectedExtraField] ?? []).map(o => ({ id: o, name: o }))"
                    :disabled="!selectedExtraField"
                    placeholder="Seleccione valor"
                />
                <InputField
                    v-else-if="selectedExtraField"
                    label="Valor"
                    type="text"
                    v-model="extraFieldValue"
                    :disabled="!selectedExtraField"
                    placeholder="Ingrese valor"
                />
            </div>
            <Button
                type="button"
                variant="default"
                class="h-9"
                :disabled="isAddDisabled()"
                @click="addExtraField"
            >
                Añadir
            </Button>
        </div>

        <div class="mt-4 space-y-3">
            <div
                v-for="([field]) in extraFieldEntries"
                :key="field"
                class="flex flex-col gap-3 rounded-2xl border border-border bg-card/80 px-4 py-4 shadow-sm sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex-1 space-y-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        {{ field }}
                        <span
                            v-if="additionalFieldsMetaMap[field]?.is_required"
                            class="text-red-500"
                        >
                            *
                        </span>
                    </p>
                    <div>
                        <SelectField
                            v-if="formatFieldOptions(field).length"
                            v-model="localExtraFields[field]"
                            label=""
                            :options="formatFieldOptions(field)"
                            option-label="name"
                            option-value="id"
                            placeholder="Selecciona un valor"
                            :disabled="props.readonly"
                            :searchable="false"
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
                    class="pt-4 ml-auto text-gray-400 transition hover:text-red-500"
                    @click="removeExtraField(field)"
                    title="Quitar"
                />
            </div>
            <div
                v-if="!hasExtraFields"
                class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-3 text-center text-sm font-medium text-slate-500"
            >
                No hay campos adicionales agregados.
            </div>
        </div>
    </div>
</template>

