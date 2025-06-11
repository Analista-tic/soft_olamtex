<script setup lang="ts">
import { ref, watch, onMounted } from 'vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHeader, TableRow } from '@/components/ui/table';
import DeleteButton from '@/components/common/DeleteButton.vue';
import type { ExtraFields } from '@/types';
import axios from 'axios';

const props = defineProps<{
    extraFieldNames: string[],
    extraFields: ExtraFields
}>();
const emit = defineEmits(['update:extraFields']);

const unidadesMedida = [
    'cm', 'mm', 'm', 'in', 'ft', 'yd', 'kg', 'g', 'lb', 'oz', 'l', 'ml', 'unidad'
];

const opcionesCampos: Record<string, string[]> = {
    // Estos campos serán llenados dinámicamente
    'Base tela': [],
    'Base hilaza': [],
    'Marca': [],
    'Mundo': [],
    'Sección': [],
    'Grupo': [],
    // Campos estáticos
    'Líneas': ['Línea 1', 'Línea 2'],
    'Tipo puntera': ['Tipo 1', 'Tipo 2'],
    'Talla': ['XS', 'S', 'M', 'L', 'XL'],
};

const selectedExtraField = ref('');
const extraFieldValue = ref('');
const medidaValor = ref('');
const medidaUnidad = ref(unidadesMedida[0]);

const localExtraFields = ref<ExtraFields>({ ...props.extraFields });

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
});

function addExtraField() {
    const field = selectedExtraField.value;
    if (!field) return;

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
    } else if (opcionesCampos[field]) {
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
            opcionesCampos[cat.campo] = res.data.data.map((item: any) => item.name);
        } catch (e) {
            console.log(e)
            opcionesCampos[cat.campo] = [];
        }
    }
});
</script>

<template>
    <div>
        <label class="mb-2 block font-semibold">Campos Adicionales</label>
        <div class="flex flex-col items-end gap-2 md:flex-row">
            <div class="flex-1">
                <SelectField
                    v-model="selectedExtraField"
                    label="Campo"
                    :options="extraFieldNames.filter(f => !localExtraFields[f]).map(f => ({ id: f, name: f }))"
                    placeholder="Selecciona un campo"
                    :disabled="extraFieldNames.filter(f => !localExtraFields[f]).length === 0"
                />
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
                    v-else-if="selectedExtraField && opcionesCampos[selectedExtraField]"
                    label="Valor"
                    v-model="extraFieldValue"
                    :options="opcionesCampos[selectedExtraField].map(o => ({ id: o, name: o }))"
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
                    <TableRow v-for="(value, field) in localExtraFields" :key="field" class="transition hover:bg-gray-50">
                        <TableCell class="px-4 py-2 font-medium text-gray-800">{{ field }}</TableCell>
                        <TableCell class="px-4 py-2 text-gray-600">{{ value }}</TableCell>
                        <TableCell class="px-4 py-2">
                            <DeleteButton class="ml-2" @click="removeExtraField(String(field))" title="Quitar" />
                        </TableCell>
                    </TableRow>
                    <TableRow v-if="Object.keys(localExtraFields).length === 0">
                        <TableCell colspan="3" class="px-4 py-2 text-center text-gray-400">
                            No hay campos adicionales agregados.
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </div>
</template>
