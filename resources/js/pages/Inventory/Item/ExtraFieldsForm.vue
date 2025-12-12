<script setup lang="ts">
import { ref, watch, onMounted, computed } from 'vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHeader, TableRow } from '@/components/ui/table';
import DeleteButton from '@/components/common/DeleteButton.vue';
import type { ExtraFields, Option, AdditionalField } from '@/types';
import { Plus } from 'lucide-vue-next';
import axios from 'axios';

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

const unidadesMedida = [
    'cm', 'mm', 'm', 'in', 'ft', 'yd', 'kg', 'g', 'lb', 'oz', 'l', 'ml', 'unidad'
];

const catalogFieldOptions = ref<Record<string, string[]>>({});

const selectedExtraField = ref('');
const extraFieldValue = ref('');
const medidaValor = ref('');
const medidaUnidad = ref(unidadesMedida[0]);

const localExtraFields = ref<ExtraFields>({ ...props.extraFields });

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
    const base = { ...catalogFieldOptions.value, ...(props.fieldOptions ?? {}) } as Record<string, string[]>;
    for (const meta of props.additionalFieldsMeta ?? []) {
        if (Array.isArray(meta.options) && meta.options.length) {
            base[meta.name] = meta.options as string[];
        }
    }
    return base;
});

// Mantener sincronización solo cuando cambian las props externamente
watch(
    () => props.extraFields,
    (val) => {
        if (JSON.stringify(val) !== JSON.stringify(localExtraFields.value)) {
            localExtraFields.value = { ...val };
        }
    }
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
    medidaValor.value = '';
    medidaUnidad.value = unidadesMedida[0];
    // precargar default_value desde metadatos si existe
    const meta = (props.additionalFieldsMeta ?? []).find((m) => m.name === selectedExtraField.value);
    if (meta && meta.default_value !== undefined && meta.default_value !== null) {
        extraFieldValue.value = String(meta.default_value ?? '');
    }
});

function addExtraField() {
    const field = selectedExtraField.value;
    if (!field) return;
    // campos especiales
    if (field === 'Medida') {
        if (medidaValor.value.trim() && medidaUnidad.value) {
            localExtraFields.value['Medida'] = `${medidaValor.value} ${medidaUnidad.value}`;
            selectedExtraField.value = '';
        }
    } else if (field === 'Campo adicional') {
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
    medidaValor.value = '';
    medidaUnidad.value = unidadesMedida[0];
}

function removeExtraField(field: string) {
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
    if (field === 'Medida') {
        return !medidaValor.value.trim() || !medidaUnidad.value;
    }
    if (field === 'Campo adicional') {
        return !extraFieldValue.value.trim();
    }
    return !extraFieldValue.value.trim();
}

// Cargar catálogos dinámicamente
onMounted(async () => {
    const catalogos = [
        { campo: 'Sección', url: '/catalogos/sections' },
        { campo: 'Grupo', url: '/catalogos/groups' },
        { campo: 'Marca', url: '/catalogos/brands' },
        { campo: 'Mundo', url: '/catalogos/worlds' },
        { campo: 'Base tela', url: '/catalogos/fabric-bases' },
        { campo: 'Base hilaza', url: '/catalogos/yarn-bases' },
    ];
    for (const cat of catalogos) {
        try {
            const res = await axios.get(cat.url);
            catalogFieldOptions.value[cat.campo] = res.data.data.map((item: any) => item.name);
        } catch (e) {
            console.log(e)
            catalogFieldOptions.value[cat.campo] = [];
        }
    }
});
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
                <div v-else-if="selectedExtraField === 'Medida'" class="flex gap-2">
                    <InputField
                        label="Valor"
                        type="text"
                        v-model="medidaValor"
                        :disabled="!selectedExtraField"
                        placeholder="Ingrese valor"
                    />
                    <SelectField
                        label="Unidad"
                        v-model="medidaUnidad"
                        :options="unidadesMedida.map(u => ({ id: u, name: u }))"
                        :disabled="!selectedExtraField"
                    />
                </div>
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

        <div class="mt-2 overflow-x-auto">
            <Table>
                <TableHeader />
                <TableBody>
                    <TableRow
                        v-for="(value, field) in localExtraFields"
                        :key="field"
                        class="transition hover:bg-gray-50"
                    >
                        <TableCell class="px-4 py-2 font-medium text-gray-800">
                            {{ field }}
                        </TableCell>
                        <TableCell class="px-4 py-2 text-gray-600">
                            {{ value }}
                        </TableCell>
                        <TableCell
                            v-if="!props.readonly"
                            class="px-4 py-2"
                        >
                            <DeleteButton
                                class="ml-2"
                                @click="removeExtraField(String(field))"
                                title="Quitar"
                            />
                        </TableCell>
                    </TableRow>

                    <TableRow v-if="Object.keys(localExtraFields).length === 0">
                        <TableCell
                            :colspan="props.readonly ? 2 : 3"
                            class="px-4 py-2 text-center text-gray-400"
                        >
                            No hay campos adicionales agregados.
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </div>
</template>
