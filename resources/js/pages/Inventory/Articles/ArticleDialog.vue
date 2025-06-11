<script setup lang="ts">
import ExtraFieldsForm from '@/pages/Inventory/Articles/ExtraFieldsForm.vue';
import InputField from '@/components/InputField.vue';
import InputIntegerField from '@/components/InputIntegerField.vue';
import SelectField from '@/components/SelectField.vue';
import SwitchField from '@/components/SwitchField.vue';
import TextareaField from '@/components/TextareaField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import Label from '@/components/ui/label/Label.vue';
import type { Article, CategorySubGroup, Color, ExtraFields, Warehouse } from '@/types';
import axios from 'axios';
import { Image } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    isOpen: boolean;
    dialogType: 'create' | 'edit';
    form: Partial<Article>;
}>();

const emit = defineEmits(['close', 'refresh']);

const localForm = ref<Partial<Article>>({ 
    ...props.form,
    reference: props.form.reference || '' // Asegura que reference esté inicializado
});

const warehouses = ref<Warehouse[]>([]);
const categorySubGroups = ref<CategorySubGroup[]>([]);
const colors = ref<Color[]>([]);

const imageFile = ref<File | null>(null);
const errors = ref<Record<string, string[]>>({});

const extraFieldNames = ['Base tela', 'Base hilaza', 'Campo adicional', 'Marca', 'Mundo', 'Líneas', 'Tipo puntera', 'Talla', 'Medida'];
const extraFields = ref<ExtraFields>({});

const nameField = ref('');
const imagePreview = ref<string | null>(null);
const fileInputRef = ref<HTMLInputElement | null>(null);

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
        localForm.value = { 
            ...newVal,
            reference: newVal.reference || '' // Asegura que reference esté definido
        };
        fillExtraFields(newVal.extra_fields);
        imageFile.value = null;
        updateNameField();
    },
    { deep: true }
);

watch(
    () => props.isOpen,
    (val) => {
        dialogOpen.value = val;
        if (val) {
            localForm.value = { 
                ...props.form,
                reference: props.form.reference || '' 
            };
            fillExtraFields(props.form.extra_fields);
            imageFile.value = null;
            resetErrors();
            updateNameField();
        }
    }
);

function resetErrors() {
    errors.value = {};
}

async function handleSubmit() {
    resetErrors();
    const extraFieldsToSend: Record<string, string> = {};
    extraFieldNames.forEach((key) => {
        if (extraFields.value[key] && String(extraFields.value[key]).trim() !== '') {
            extraFieldsToSend[key] = String(extraFields.value[key]);
        }
    });
    const orderedExtraFields = Object.fromEntries(Object.entries(extraFieldsToSend).sort());

    updateNameField(orderedExtraFields);

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
    const method = props.dialogType === 'create' ? 'POST' : 'POST';

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
    }
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

watch(imageFile, (file) => {
    if (file) {
        const reader = new FileReader();
        reader.onload = (e) => {
            imagePreview.value = e.target?.result as string;
        };
        reader.readAsDataURL(file);
    } else {
        if (typeof localForm.value.img === 'string' && localForm.value.img.trim() !== '') {
            imagePreview.value = `/storage/${localForm.value.img.replace(/^\/?storage\//, '')}`;
        } else {
            imagePreview.value = null;
        }
    }
});

watch(
    () => localForm.value.img,
    (img) => {
        if (!imageFile.value) {
            if (typeof img === 'string' && img.trim() !== '') {
                imagePreview.value = `/storage/${img.replace(/^\/?storage\//, '')}`;
            } else {
                imagePreview.value = null;
            }
        }
    }
);

watch(
    [
        () => localForm.value.category_sub_group_id,
        () => localForm.value.color_id,
        () => extraFieldNames.map((key) => extraFields.value[key]).join('|'),
    ],
    () => updateNameField(),
    { immediate: true }
);

function triggerFileInput() {
    fileInputRef.value?.click();
}

function handleFileChange(e: Event) {
    const input = e.target as HTMLInputElement;
    const files = input.files;
    imageFile.value = files && files.length > 0 ? files.item(0) : null;
}
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

            <form @submit.prevent="handleSubmit" class="space-y-2">
                <div class="grid grid-cols-1 gap-2 md:grid-cols-4">
                    <div class="flex flex-col gap-4 space-y-2 md:col-span-3">
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
                            <InputIntegerField v-model="localForm.impuesto" label="Impuesto" type="number" />
                            <InputIntegerField v-model="localForm.stock_min" label="Stock Mínimo" type="integer" />
                            <InputIntegerField v-model="localForm.stock_max" label="Stock Máximo" type="integer" />
                        </div>
                    </div>
                    <!-- Previsualización de imagen con dropzone -->
                    <div class="flex flex-col items-center justify-center px-2 md:row-span-3">
                        <div class="flex w-full flex-col items-center">
                            <Label class="mb-1 block text-sm font-medium">Imagen</Label>
                            <input ref="fileInputRef" type="file" accept="image/*" class="hidden" @change="handleFileChange" />
                            <div
                                class="relative flex h-52 w-full cursor-pointer flex-col items-stretch justify-center overflow-hidden rounded border-2 border-dashed bg-gray-50 transition hover:border-blue-400"
                                @click="triggerFileInput"
                                style="min-width: 0; position: relative"
                            >
                                <template v-if="imagePreview">
                                    <div class="relative h-52 w-full">
                                        <img
                                            :src="imagePreview"
                                            alt="Previsualización"
                                            class="h-57 w-full rounded object-contain"
                                            style="display: block; position: absolute; top: 0; left: 0; right: 0; margin: 0; z-index: 10"
                                        />
                                    </div>
                                    <div class="absolute bottom-0 left-0 right-0 z-20 bg-black/50 py-1 text-center text-xs text-white">
                                        Cambiar imagen
                                    </div>
                                </template>
                                <template v-else>
                                    <div class="z-10 flex h-full w-full flex-col items-center justify-center">
                                        <Image class="mb-2 h-12 w-12 text-gray-400" />
                                        <span class="text-sm text-gray-500">Haz clic para subir imagen</span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <div class="md:col-span-2">
                        <InputField 
                            v-model="localForm.bar_code" 
                            label="Código de Barras" 
                        />
                    </div>
                    <div class="md:col-span-1">
                        <InputField 
                            v-model="localForm.reference" 
                            label="Referencia" 
                            required
                            :disabled="false"
                        />
                    </div>
                    <div class="flex items-center md:col-span-1">
                        <SwitchField v-model="negativeMovementBool" label="Movimiento Negativo" />
                    </div>
                </div>

                <!-- Descripción -->
                <div class="">
                    <TextareaField v-model="localForm.description" label="Descripción" />
                </div>

                <!-- Campos adicionales -->
                <ExtraFieldsForm :extra-field-names="extraFieldNames" v-model:extra-fields="extraFields" />

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