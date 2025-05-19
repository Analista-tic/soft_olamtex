<script setup lang="ts">
// Importaciones de componentes y utilidades
import DeleteButton from '@/components/common/DeleteButton.vue';
import InputField from '@/components/InputField.vue';
import inputInteguerField from '@/components/InputIntegerField.vue';
import SelectField from '@/components/SelectField.vue';
import SwitchField from '@/components/SwitchField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import Label from '@/components/ui/label/Label.vue';
import { Table, TableBody, TableCell, TableHeader, TableRow } from '@/components/ui/table';
import type { Article, CategorySubGroup, Color, Warehouse } from '@/types';
import axios from 'axios';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    isOpen: boolean;
    dialogType: 'create' | 'edit';
    form: Partial<Article>;
}>();

const emit = defineEmits(['close', 'refresh']);

const localForm = ref<Partial<Article>>({ ...props.form });

const warehouses = ref<Warehouse[]>([]);
const categorySubGroups = ref<CategorySubGroup[]>([]);
const colors = ref<Color[]>([]);

const imageFile = ref<File | null>(null);
const errors = ref<Record<string, string[]>>({});

const extraFieldNames = ['Base tela', 'Base hilaza', 'Campo adicional', 'Marca', 'Mundo', 'Líneas', 'Tipo puntera', 'Talla', 'Medida'];

const extraFields = ref<Record<string, string>>({});

const selectedExtraField = ref('');
const extraFieldValue = ref('');
const nameField = ref('');

async function fetchWarehouses() {
    try {
        const { data } = await axios.get('/bodegas/data', { params: { per_page: 1000 } });
        warehouses.value = data.data;
    } catch (error) {
        console.error('Error al obtener las bodegas:', error);
    }
}

async function fetchCategorySubGroups() {
    try {
        const { data } = await axios.get('/categorias/subgrupos-data', { params: { per_page: 1000 } });
        categorySubGroups.value = data.data;
    } catch (error) {
        console.error('Error al obtener los subgrupos:', error);
    }
}

async function fetchColors() {
    try {
        const { data } = await axios.get('/colores/data', { params: { per_page: 1000 } });
        colors.value = data.data;
    } catch (error) {
        console.error('Error al obtener los colores:', error);
    }
}

function fillExtraFields(obj: any) {
    extraFields.value = {};
    if (obj && typeof obj === 'object') {
        for (const key of extraFieldNames) {
            if (obj[key] !== undefined && obj[key] !== null && String(obj[key]).trim() !== '') {
                extraFields.value[key] = String(obj[key]);
            }
        }
    }
}

onMounted(() => {
    fetchWarehouses();
    fetchCategorySubGroups();
    fetchColors();
    fillExtraFields(props.form.extra_fields);
});

watch(
    () => props.form,
    (newVal) => {
        localForm.value = { ...newVal };
        fillExtraFields(newVal.extra_fields);
        imageFile.value = null;
        updateNameField();
    },
);

watch(
    () => props.isOpen,
    (val) => {
        dialogOpen.value = val;
        if (val) {
            // Al abrir el diálogo, reinicializa el formulario local y limpia errores/imagen
            localForm.value = { ...props.form };
            fillExtraFields(props.form.extra_fields);
            imageFile.value = null;
            resetErrors();
            updateNameField();
        }
    },
);

// Sincroniza el formulario local y los campos adicionales al abrir el diálogo o cambiar el artículo
watch(
    [() => props.isOpen, () => props.form],
    ([isOpen, form]) => {
        if (isOpen) {
            localForm.value = { ...form };
            fillExtraFields(form.extra_fields);
            imageFile.value = null;
            resetErrors();
            updateNameField();
        }
    },
    { immediate: true },
);

function resetErrors() {
    errors.value = {};
}

async function handleSubmit() {
    resetErrors();
    // Solo campos con valor y ordenados
    const extraFieldsToSend: Record<string, string> = {};
    extraFieldNames.forEach((key) => {
        if (extraFields.value[key] && String(extraFields.value[key]).trim() !== '') {
            extraFieldsToSend[key] = String(extraFields.value[key]);
        }
    });
    const orderedExtraFields = Object.fromEntries(Object.entries(extraFieldsToSend).sort());

    updateNameField(orderedExtraFields);

    // Corrige la comparación de negative_movement para evitar comparar tipos incompatibles
    const nm = localForm.value.negative_movement;
    const negativeMovementValue = nm === true || nm === 1 || (typeof nm === 'string' && (nm === '1' || nm === 'true')) ? 1 : 0;

    const formData = new FormData();
    Object.entries({
        ...localForm.value,
        extra_fields: Object.keys(orderedExtraFields).length ? JSON.stringify(orderedExtraFields) : null,
        name: nameField.value,
    }).forEach(([key, value]) => {
        if (key === 'negative_movement') {
            formData.append(key, String(negativeMovementValue));
        } else if (key === 'extra_fields' && value !== null) {
            formData.append(key, value as string);
        } else if (typeof value === 'object' && value !== null) {
            formData.append(key, JSON.stringify(value));
        } else if (value !== undefined && value !== null) {
            formData.append(key, value as any);
        }
    });
    if (imageFile.value) {
        formData.append('image', imageFile.value);
    }

    const url = props.dialogType === 'create' ? '/articulos' : `/articulos/${localForm.value.id}`;
    const method = props.dialogType === 'create' ? 'POST' : 'POST'; // Forzar POST para PUT con FormData

    // Si es edición, agregar _method=PUT para Laravel
    if (props.dialogType === 'edit') {
        formData.append('_method', 'PUT');
    }

    try {
        await axios({
            method,
            url,
            data: formData,
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        emit('refresh');
        emit('close');
    } catch (error: any) {
        errors.value = error.response?.data?.errors || {
            general: [error.response?.data?.message || 'Ocurrió un error inesperado al guardar el artículo.'],
        };
        if (!error.response?.data?.errors) console.error('Error al guardar el artículo:', error);
    }
}

const dialogOpen = ref(props.isOpen);

watch(
    () => props.isOpen,
    (val) => {
        dialogOpen.value = val;
    },
);

function handleClose() {
    dialogOpen.value = false;
    emit('close');
}

const negativeMovementBool = computed({
    get() {
        return localForm.value.negative_movement === 1 || localForm.value.negative_movement === true;
    },
    set(val: boolean) {
        localForm.value.negative_movement = val ? 1 : 0;
    },
});

function addExtraField() {
    if (selectedExtraField.value && extraFieldValue.value.trim() !== '' && !extraFields.value[selectedExtraField.value]) {
        extraFields.value[selectedExtraField.value] = extraFieldValue.value.trim();
        selectedExtraField.value = '';
        extraFieldValue.value = '';
    }
}

function removeExtraField(field: string) {
    delete extraFields.value[field];
}

function updateNameField(orderedFields?: Record<string, string>): void {
    const subGroup = categorySubGroups.value.find((sg) => sg.id === localForm.value.category_sub_group_id);
    const color = colors.value.find((c) => c.id === localForm.value.color_id);
    const extrasObj: Record<string, string> =
        orderedFields ??
        Object.fromEntries(
            extraFieldNames
                .filter((key) => extraFields.value[key] && String(extraFields.value[key]).trim() !== '')
                .sort()
                .map((key) => [key, String(extraFields.value[key])]),
        );
    const extras = Object.values(extrasObj).join(' ');
    nameField.value = [subGroup?.name ?? '', extras, color?.name ?? ''].filter(Boolean).join(' ').replace(/\s+/g, ' ').trim();
    localForm.value.name = nameField.value;
}

watch(
    [
        () => localForm.value.category_sub_group_id,
        () => localForm.value.color_id,
        () => extraFieldNames.map((key) => extraFields.value[key]).join('|'),
    ],
    () => updateNameField(),
    { immediate: true },
);
</script>

<template>
    <Dialog :open="dialogOpen" @update:open="handleClose">
        <DialogContent class="flex h-full max-h-[100dvh] min-h-0 w-full flex-1 flex-col overflow-y-auto p-6 sm:max-w-4xl">
            <DialogHeader class="mb-4">
                <DialogTitle>
                    {{ dialogType === 'create' ? 'Crear Artículo' : 'Editar Artículo' }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        dialogType === 'create' ? 'Complete los campos para crear un nuevo artículo.' : 'Edite los campos del artículo seleccionado.'
                    }}
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="handleSubmit" class="space-y-6">
                <!-- Nombre -->
                <div>
                    <Label>Nombre</Label>
                    <Input v-model="nameField" label="Nombre" readonly class="mt-1" placeholder="Nombre autocompletado" />
                </div>

                <!-- Grupo principal -->
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <SelectField
                        v-model="localForm.warehouse_id"
                        label="Bodega"
                        :options="warehouses.map((w) => ({ id: w.id ?? '', name: w.name }))"
                        required
                    />
                    <SelectField
                        v-model="localForm.category_sub_group_id"
                        label="Subgrupo"
                        :options="categorySubGroups.map((sg) => ({ id: sg.id ?? '', name: sg.name }))"
                        required
                    />
                    <SelectField
                        v-model="localForm.color_id"
                        label="Color"
                        :options="colors.map((c) => ({ id: c.id ?? '', name: c.name }))"
                        required
                    />
                </div>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <inputInteguerField v-model="localForm.impuesto" label="Impuesto" type="number" />
                    <inputInteguerField v-model="localForm.stock_min" label="Stock Mínimo" type="integer" />
                    <inputInteguerField v-model="localForm.stock_max" label="Stock Máximo" type="integer" />
                </div>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div class="md:col-span-2">
                        <InputField v-model="localForm.bar_code" label="Código de Barras" />
                    </div>
                    <div class="flex items-center">
                        <SwitchField v-model="negativeMovementBool" label="Movimiento Negativo" />
                    </div>
                </div>

                <!-- Descripción e Imagen -->
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <InputField v-model="localForm.description" label="Descripción" />
                    <div>
                        <Label class="mb-1 block text-sm font-medium">Imagen</Label>
                        <Input
                            type="file"
                            accept="image/*"
                            @change="
                                (e: Event) => {
                                    const input = e.target as HTMLInputElement;
                                    const files = input.files;
                                    imageFile = files && files.length > 0 ? files.item(0) : null;
                                }
                            "
                        />
                        <div v-if="typeof localForm.img === 'string' && localForm.img" class="mt-2">
                            <img :src="localForm.img" alt="Imagen subida" class="max-h-24 rounded" />
                            <div class="break-all text-xs text-gray-500">{{ localForm.img }}</div>
                        </div>
                    </div>
                </div>

                <!-- Campos adicionales -->
                <div>
                    <label class="mb-2 block font-semibold">Campos Adicionales</label>
                    <div class="flex flex-col items-end gap-2 md:flex-row">
                        <div class="flex-1">
                            <SelectField
                                v-model="selectedExtraField"
                                label="Campo"
                                :options="extraFieldNames.filter((f) => !extraFields[f]).map((f) => ({ id: f, name: f }))"
                                placeholder="Selecciona un campo"
                                :disabled="extraFieldNames.filter((f) => !extraFields[f]).length === 0"
                            />
                        </div>
                        <div class="flex-1">
                            <InputField
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
                            :disabled="!selectedExtraField || !extraFieldValue.trim() || !!extraFields[selectedExtraField]"
                            @click="addExtraField"
                        >
                            Añadir
                        </Button>
                    </div>

                    <div class="mt-2 overflow-x-auto">
                        <Table>
                            <TableHeader>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="(value, field) in extraFields" :key="field" class="transition hover:bg-gray-50">
                                    <TableCell class="px-4 py-2 font-medium text-gray-800">{{ field }}</TableCell>
                                    <TableCell class="px-4 py-2 text-gray-600">{{ value }}</TableCell>
                                    <TableCell class="px-4 py-2">
                                        <DeleteButton class="ml-2" @click="removeExtraField(field)" title="Quitar" />
                                    </TableCell>
                                </TableRow>
                                <TableRow v-if="Object.keys(extraFields).length === 0">
                                    <TableCell colspan="3" class="px-4 py-2 text-center text-gray-400">
                                        No hay campos adicionales agregados.
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </div>

                <!-- Footer -->
                <DialogFooter class="mt-6 flex flex-col-reverse gap-2 md:flex-row md:justify-end">
                    <Button variant="outline" type="button" @click="handleClose" class="w-full md:w-auto">Cancelar</Button>
                    <Button variant="default" type="submit" class="w-full md:w-auto">
                        {{ dialogType === 'create' ? 'Guardar' : 'Actualizar' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
