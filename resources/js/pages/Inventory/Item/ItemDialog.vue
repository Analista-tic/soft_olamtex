<script setup lang="ts">
// Importación de componentes y utilidades
import InputField from '@/components/InputField.vue';
import InputIntegerField from '@/components/InputIntegerField.vue';
import SelectField from '@/components/SelectField.vue';
import SwitchField from '@/components/SwitchField.vue';
import TextareaField from '@/components/TextareaField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import Label from '@/components/ui/label/Label.vue';
import ExtraFieldsForm from '@/pages/Inventory/Item/ExtraFieldsForm.vue';
import type { Item, CategorySubGroup, Color, ExtraFields, Warehouse } from '@/types';
import axios from 'axios';
import { Image, Plus, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, onBeforeUnmount, ref, watch } from 'vue';
import AlertBox from '@/components/AlertBox.vue';
import { formatRequiredFieldsMessage } from '@/lib/alert-format';

// Props y eventos
const props = defineProps<{
    isOpen: boolean;
    dialogType: 'create' | 'edit' | 'view';
    form: Partial<Item>;
}>();
const emit = defineEmits(['close', 'refresh', 'saved', 'error']);

// Estado reactivo principal
const localForm = ref<Partial<Item>>({
    ...props.form,
    reference: props.form.reference || '',
});
const warehouses = ref<Warehouse[]>([]);
const categorySubGroups = ref<CategorySubGroup[]>([]);
const colors = ref<Color[]>([]);
const imageFile = ref<File | null>(null);
const errors = ref<Record<string, string[]>>({});
const extraFieldNames = [
    'Base tela', 'Base hilaza', 'Campo adicional', 'Marca', 'Mundo', 'Líneas', 'Tipo puntera', 'Talla', 'Medida'
];
const extraFields = ref<ExtraFields>({});
const nameField = ref('');
const imagePreview = ref<string | null>(null);
const fileInputRef = ref<HTMLInputElement | null>(null);
const warehouseStocks = ref<{ warehouse_id: number; name: string; quantity: number; stock_min: number; stock_max: number }[]>([]);
const taxes = ref<any[]>([]);

// Unidades de medida
const unitMeasures = [
    { id: 'cm', name: 'Centímetro (cm)' },
    { id: 'mm', name: 'Milímetro (mm)' },
    { id: 'm', name: 'Metro (m)' },
    { id: 'in', name: 'Pulgada (in)' },
    { id: 'ft', name: 'Pie (ft)' },
    { id: 'yd', name: 'Yarda (yd)' },
    { id: 'kg', name: 'Kilogramo (kg)' },
    { id: 'g', name: 'Gramo (g)' },
    { id: 'lb', name: 'Libra (lb)' },
    { id: 'oz', name: 'Onza (oz)' },
    { id: 'l', name: 'Litro (l)' },
    { id: 'ml', name: 'Mililitro (ml)' },
    { id: 'unidad', name: 'Unidad' }
];
const unitMeasure = ref(props.form.unit_measure ?? unitMeasures[0].id);
const alertMessage = ref<string>('');
const alertType = ref<'success' | 'error' | 'info' | 'warning'>('error');
const isViewMode = computed(() => props.dialogType === 'view');

// Carga de catálogos y datos iniciales
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
async function fetchTaxes() {
    const { data } = await axios.get('/impuestos/data', { params: { per_page: 100, status: true } });
    taxes.value = (data.data ?? []).filter((t: any) => t.status);
}

// Opciones para campos adicionales (reactivo y dinámico)
const extraFieldOptions = ref<Record<string, { id: string; name: string }[]>>({
    'Base tela': [],
    'Base hilaza': [],
    'Marca': [],
    'Mundo': [],
    'Sección': [],
    'Grupo': [],
    'Líneas': [],
    'Tipo puntera': [],
    'Talla': [],
});

// Carga de catálogos para campos adicionales
async function fetchExtraFieldOptions() {
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
            extraFieldOptions.value[cat.campo] = res.data.data.map((item: any) => ({
                id: item.id?.toString() ?? item.name,
                name: item.name,
            }));
        } catch (e) {
            console.log(`Error al cargar opciones para ${cat.campo}:`, e);
            extraFieldOptions.value[cat.campo] = [];
        }
    }
}

// Rellena los campos extra desde el objeto recibido
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

// Carga inicial de datos al montar el componente
onMounted(async () => {
    await Promise.all([
        fetchWarehouses(),
        fetchCategorySubGroups(),
        fetchColors(),
        fetchTaxes(),
        fetchExtraFieldOptions()
    ]);
    fillExtraFields(props.form.extra_fields);
    if (props.form.id) {
        await fetchWarehouseStocks(props.form.id);
    }
});

// Carga de stocks por bodega para el artículo
async function fetchWarehouseStocks(itemId: number) {
    try {
        const { data } = await axios.get(`/bodegas/${itemId}/warehouses`);
        warehouseStocks.value = data.map((w: any) => ({
            warehouse_id: w.id,
            name: w.name,
            quantity: w.pivot.quantity,
            stock_min: w.pivot.stock_min,
            stock_max: w.pivot.stock_max,
        }));
    } catch (e) {
        console.log(e);
        warehouseStocks.value = [];
    }
}

// Devuelve bodegas disponibles para seleccionar (evita duplicados)
function availableWarehouses(idx?: number) {
    const usedIds = warehouseStocks.value
        .map((ws, i) => (i !== idx ? ws.warehouse_id : null))
        .filter((id): id is number => typeof id === 'number');
    return [...warehouses.value]
        .filter((w) => typeof w.id === 'number' && !usedIds.includes(w.id))
        .map((w) => ({
            id: String(w.id),
            name: w.name
        }));
}

// Agrega/quita bodegas al detalle de inventario
function addWarehouseStock() {
    warehouseStocks.value.push({
        warehouse_id: 0,
        name: '',
        quantity: 0,
        stock_min: 0,
        stock_max: 0,
    });
}
function removeWarehouseStock(idx: number) {
    warehouseStocks.value.splice(idx, 1);
}
function updateWarehouseName(idx: number) {
    const warehouseId = warehouseStocks.value[idx].warehouse_id;
    const warehouse = warehouses.value.find((w) => w.id === warehouseId);
    warehouseStocks.value[idx].name = warehouse ? warehouse.name : '';
}

// Sincronización de props y estado local
watch(
    () => props.form,
    (newVal) => {
        localForm.value = {
            ...newVal,
            reference: newVal.reference || '',
            tax: typeof newVal.tax === 'object' && newVal.tax !== null
                ? newVal.tax
                : null
        };
        fillExtraFields(newVal.extra_fields);
        imageFile.value = null;
        updateNameField();
    },
    { deep: true },
);
watch(
    () => props.form.id,
    async (id) => {
        // Cada vez que cambie el item seleccionado (al abrir el modal de editar/ver), recargamos sus bodegas
        if (id) {
            await fetchWarehouseStocks(Number(id));
        } else {
            warehouseStocks.value = [];
        }
    },
    { immediate: true }
);
watch(
    () => props.isOpen,
    (val) => {
        dialogOpen.value = val;
        if (val) {
            localForm.value = {
                ...props.form,
                reference: props.form.reference || '',
            };
            fillExtraFields(props.form.extra_fields);
            imageFile.value = null;
            resetErrors();
            updateNameField();
            if (props.form.id) {
                void fetchWarehouseStocks(Number(props.form.id));
            } else {
                warehouseStocks.value = [];
            }
        }
    },
);

// Limpia errores de validación
function resetErrors() {
    errors.value = {};
}

const buildErrorMessage = (err: any): string => {
    const resp = err?.response?.data;
    const validation = resp?.errors;
    if (validation && typeof validation === 'object') {
        const joined = Object.values(validation)
            .flat()
            .filter(Boolean)
            .join('. ');
        if (joined.trim()) return joined;
    }
    return resp?.message || 'Ocurrió un error inesperado al guardar el artículo.';
};

// Maneja el envío del formulario
async function handleSubmit() {
    resetErrors();
    alertMessage.value = '';
    alertType.value = 'error';
    const requiredErrors: string[] = [];

    // Validaciones de campos obligatorios
    if (!localForm.value.category_sub_group_id) {
        requiredErrors.push('Subgrupo');
    }
    if (!localForm.value.color_id) {
        requiredErrors.push('Color');
    }
    if (!localForm.value.tax_id && localForm.value.tax_id !== 0) {
        requiredErrors.push('Impuesto');
    }
    if (!unitMeasure.value) {
        requiredErrors.push('Unidad de medida');
    }
    if (!localForm.value.reference || String(localForm.value.reference).trim() === '') {
        requiredErrors.push('Referencia');
    }
    if (requiredErrors.length > 0) {
        alertType.value = 'warning';
        alertMessage.value = formatRequiredFieldsMessage(
            'Por favor, completa todos los campos obligatorios',
            requiredErrors,
        );
        const dialogEl = document.querySelector('[role="dialog"]');
        dialogEl?.scrollTo({ top: 0, behavior: 'smooth' });
        return;
    }
    if (hasNegativeStock.value && !negativeMovementBool.value) {
        showNegativeMovementWarning();
        return; // NO se envía el formulario ni se cierra el diálogo
    }

    const extraFieldsToSend: Record<string, string> = {};
    extraFieldNames.forEach((key) => {
        if (extraFields.value[key] && String(extraFields.value[key]).trim() !== '') {
            extraFieldsToSend[key] = String(extraFields.value[key]);
        }
    });
    const orderedExtraFields = Object.fromEntries(Object.entries(extraFieldsToSend).sort());
    updateNameField(orderedExtraFields);

    // Prepara el formulario para enviar
    const cleanForm: any = {
        ...localForm.value,
        category_sub_group_id: Number(localForm.value.category_sub_group_id) || undefined,
        color_id: Number(localForm.value.color_id) || undefined,
        tax_id:
            localForm.value.tax_id !== undefined &&
            localForm.value.tax_id !== null &&
            String(localForm.value.tax_id) !== ''
                ? Number(localForm.value.tax_id)
                : undefined,
        tax: localForm.value.tax && typeof localForm.value.tax === 'object'
            ? Number(localForm.value.tax.percentage)
            : 0,
        negative_movement: localForm.value.negative_movement,
        bar_code: localForm.value.bar_code || '',
        description: (localForm.value.description || '').trim() || 'N/A',
        reference: localForm.value.reference || '',
        extra_fields: Object.keys(orderedExtraFields).length ? JSON.stringify(orderedExtraFields) : null,
        name: nameField.value,
        unit_measure: unitMeasure.value,
        status: typeof localForm.value.status === 'boolean'
            ? (localForm.value.status ? 1 : 0)
            : (localForm.value.status === 1 || localForm.value.status === '1' || localForm.value.status === true ? 1 : 0),
    };

    const formData = new FormData();
    const optionalStringFields = new Set(['bar_code', 'description']);
    Object.entries(cleanForm).forEach(([key, value]) => {
        if (key === 'negative_movement' || key === 'status') {
            formData.append(key, String(value === true || value === 1 || value === '1' || value === 'true' ? 1 : 0));
            return;
        }
        if (typeof value === 'string' && optionalStringFields.has(key) && value.trim() === '') {
            return; // no enviar strings vacíos en campos opcionales
        }
        if (value !== undefined && value !== null) {
            formData.append(key, value as any);
        }
    });
    if (imageFile.value) {
        formData.append('image', imageFile.value);
    }
    formData.append('warehouses', JSON.stringify(warehouseStocks.value.filter((w) => Number(w.warehouse_id)).map((w) => ({
        warehouse_id: Number(w.warehouse_id),
        quantity: Number(w.quantity),
        stock_min: Number(w.stock_min),
        stock_max: Number(w.stock_max),
    }))));

    const url = props.dialogType === 'create' ? '/item' : `/item/${localForm.value.id}`;
    const method = props.dialogType === 'create' ? 'POST' : 'POST';
    if (props.dialogType === 'edit') {
        formData.append('_method', 'PUT');
    }
    if (props.dialogType === 'view') return;

    try {
        await axios({
            method,
            url,
            data: formData,
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        emit('refresh');
        emit('close');
        emit('saved', props.dialogType);
    } catch (error: any) {
        console.error('Error al guardar el item:', error, error?.response?.data);
        const msg = buildErrorMessage(error);
        alertType.value = error?.response?.data?.errors ? 'warning' : 'error';
        alertMessage.value = msg;
        const dialogEl = document.querySelector('[role="dialog"]');
        dialogEl?.scrollTo({ top: 0, behavior: 'smooth' });
        emit('error', msg);
    }
}

// Estado de apertura del diálogo
const dialogOpen = ref(props.isOpen);
watch(
    () => props.isOpen,
    (val) => {
        dialogOpen.value = val;
    },
);

const INVENTORY_ADJUSTMENT_SAVED_EVENT = 'inventory-adjustment-saved';
const handleInventoryAdjustmentSaved = (event: Event) => {
    const evt = event as CustomEvent<{ itemIds?: number[] }>;
    const itemIds = (evt?.detail?.itemIds ?? [])
        .map(id => Number(id))
        .filter((id) => Number.isFinite(id) && id > 0);
    const currentItemId = Number(props.form.id);
    if (!currentItemId || !itemIds.includes(currentItemId)) return;
    if (!dialogOpen.value) return;
    void fetchWarehouseStocks(currentItemId);
};

onMounted(() => {
    if (typeof window === 'undefined') return;
    window.addEventListener(INVENTORY_ADJUSTMENT_SAVED_EVENT, handleInventoryAdjustmentSaved);
});
onBeforeUnmount(() => {
    if (typeof window === 'undefined') return;
    window.removeEventListener(INVENTORY_ADJUSTMENT_SAVED_EVENT, handleInventoryAdjustmentSaved);
});

// Cierre del diálogo
function handleClose() {
    dialogOpen.value = false;
    emit('close');
}

const NEGATIVE_STOCK_WARNING =
    'Este ítem tiene cantidades negativas en una o más bodegas. ' +
    'No puedes desactivar "Movimiento Negativo" mientras exista stock menor que cero.';

function showNegativeMovementWarning() {
  alertType.value = 'warning';
  alertMessage.value = NEGATIVE_STOCK_WARNING;

  const dialogEl = document.querySelector('[role="dialog"]');
  dialogEl?.scrollTo({ top: 0, behavior: 'smooth' });
}

const hasNegativeStock = computed(() =>
  warehouseStocks.value.some((w) => Number(w.quantity) < 0)
);

const negativeMovementBool = computed(() =>
  localForm.value.negative_movement === 1 ||
  localForm.value.negative_movement === true
);

function handleNegativeMovementChange(val: boolean) {
  if (hasNegativeStock.value && !val) {
    showNegativeMovementWarning();
  }

  localForm.value.negative_movement = val ? 1 : 0;
}

// Actualiza el campo nombre automáticamente
function updateNameField(orderedFields?: Record<string, string>): void {
    const subGroup = categorySubGroups.value.find(
        (sg) => String(sg.id) === String(localForm.value.category_sub_group_id)
    );
    const color = colors.value.find(
        (c) => String(c.id) === String(localForm.value.color_id)
    );
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

// Previsualización de imagen
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
    },
);

// Actualiza nombre cuando cambian selects/campos extra
watch(
    [
        () => localForm.value.category_sub_group_id,
        () => localForm.value.color_id,
        () => extraFieldNames.map((key) => extraFields.value[key]).join('|'),
    ],
    () => updateNameField(),
    { immediate: true }
);

// Manejo de input de archivo
function triggerFileInput() {
    fileInputRef.value?.click();
}
function handleFileChange(e: Event) {
    const input = e.target as HTMLInputElement;
    const files = input.files;
    imageFile.value = files && files.length > 0 ? files.item(0) : null;
}

// Actualiza tax_id y tax (objeto) automáticamente al cambiar tax_id
watch(
    () => localForm.value.tax_id,
    (taxId) => {
        const taxObj = taxes.value.find(t => String(t.id) === String(taxId));
        localForm.value.tax = taxObj || null;
    }
);
</script>


<template>
    <Dialog :open="dialogOpen" @update:open="handleClose">
        <DialogContent class="flex h-full max-h-[100dvh] min-h-0 w-full flex-1 flex-col overflow-y-auto p-6 sm:max-w-4xl">
            <transition name="fade">
                <div v-if="alertMessage" class="pointer-events-none absolute left-1/2 top-20 z-20 w-full max-w-xl -translate-x-1/2">
                    <AlertBox
                        :type="alertType"
                        :title="alertType === 'warning' ? 'Advertencia' : 'Error'"
                        :message="alertMessage"
                        class="pointer-events-auto"
                        @close="alertMessage = ''"
                    />
                </div>
            </transition>
            <DialogHeader class="mb-4">
                <DialogTitle>
                {{
                    dialogType === 'create'
                    ? 'Crear ítem'
                    : dialogType === 'edit'
                        ? 'Editar ítem'
                        : 'Detalle del ítem'
                }}
                </DialogTitle>

                <DialogDescription>
                {{
                    dialogType === 'create'
                    ? 'Complete los campos para crear un nuevo artículo.'
                    : dialogType === 'edit'
                        ? 'Edite los campos del artículo seleccionado.'
                        : 'Visualice la información del artículo.'
                }}
                </DialogDescription>

            </DialogHeader>

            <form @submit.prevent="handleSubmit">
                <fieldset :disabled="isViewMode" class="space-y-4">
                    <div class="grid grid-cols-1 gap-2 md:grid-cols-4">
                        <div class="flex flex-col gap-4 md:col-span-3">
                            <div>
                                <Label>Nombre</Label>
                                <Input
                                    v-model="nameField"
                                    label="Nombre"
                                    readonly
                                    class="mt-1"
                                    placeholder="Nombre autocompletado"
                                />
                            </div>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <template v-if="isViewMode">
                                    <div class="flex flex-col gap-1">
                                        <Label>Subgrupo</Label>
                                        <div class="mt-1 rounded-md border bg-muted px-3 py-2 text-sm">
                                            {{
                                                categorySubGroups.find(
                                                    (sg) => String(sg.id) === String(localForm.category_sub_group_id)
                                                )?.name || '-'
                                            }}
                                        </div>
                                    </div>

                                    <div class="flex flex-col gap-1">
                                        <Label>Color</Label>
                                        <div class="mt-1 rounded-md border bg-muted px-3 py-2 text-sm">
                                            {{
                                                colors.find(
                                                    (c) => String(c.id) === String(localForm.color_id)
                                                )?.name || '-'
                                            }}
                                        </div>
                                    </div>

                                    <div class="flex flex-col gap-1">
                                        <Label>Impuesto</Label>
                                        <div class="mt-1 rounded-md border bg-muted px-3 py-2 text-sm">
                                            {{
                                                taxes.find((t) => String(t.id) === String(localForm.tax_id))
                                                    ? `${taxes.find((t) => String(t.id) === String(localForm.tax_id)).name}
                                                    (${Math.trunc(taxes.find((t) => String(t.id) === String(localForm.tax_id)).percentage)}%)`
                                                    : '-'
                                            }}
                                        </div>
                                    </div>
                                </template>

                                <template v-else>
                                    <SelectField
                                        v-model="localForm.category_sub_group_id"
                                        label="Subgrupo"
                                        :options="categorySubGroups.map((sg) => ({ id: String(sg.id ?? ''), name: sg.name }))"
                                        required
                                    />
                                    <SelectField
                                        v-model="localForm.color_id"
                                        label="Color"
                                        :options="colors.map((c) => ({ id: String(c.id ?? ''), name: c.name }))"
                                        required
                                    />
                                    <SelectField
                                        v-model="localForm.tax_id"
                                        label="Impuesto"
                                        :options="taxes.map((t) => ({
                                            id: t.id,
                                            name: `${t.name} (${Math.trunc(t.percentage)}%)`,
                                        }))"
                                        required
                                    />
                                </template>
                            </div>

                            <div
                                class="mt-2 rounded-lg border border-gray-200 bg-white p-3 dark:bg-gray-900"
                            >
                                <div class="mb-2 flex items-center justify-between">
                                    <span class="text-sm font-semibold">Detalle de Inventario</span>
                                    <button
                                        v-if="!isViewMode"
                                        type="button"
                                        class="rounded-full p-1 transition hover:bg-gray-100 dark:hover:bg-gray-800"
                                        @click="addWarehouseStock"
                                        :disabled="availableWarehouses().length === 0"
                                        title="Agregar Bodega"
                                    >
                                        <Plus class="h-5 w-5 text-primary" />
                                    </button>
                                </div>

                                <div
                                    v-for="(ws, idx) in warehouseStocks"
                                    :key="idx"
                                    class="mb-2 rounded-md bg-gray-50 p-2 dark:bg-gray-800"
                                >
                                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-[220px,1fr,1fr,1fr,auto] sm:items-end">
                                        <div class="flex h-full flex-col justify-between gap-1">
                                            <Label class="text-xs">Bodega</Label>
                                            <SelectField
                                                v-model="ws.warehouse_id"
                                                label=""
                                                :options="availableWarehouses(idx).map((w) => ({
                                                    id: String(w.id ?? ''),
                                                    name: w.name,
                                                }))"
                                                @change="updateWarehouseName(idx)"
                                                class="w-full"
                                                placeholder="Bodega"
                                                :searchable="false"
                                            />
                                        </div>
                                        <div class="flex h-full flex-col justify-between gap-1">
                                            <Label class="text-xs">Cantidad</Label>
                                            <InputIntegerField
                                                v-model="ws.quantity"
                                                label=""
                                                type="number"
                                                class="w-full"
                                                placeholder="Cantidad"
                                                :allowNegative="true"  
                                            />
                                        </div>
                                        <div class="flex h-full flex-col justify-between gap-1">
                                            <Label class="text-xs">Cantidad Min</Label>
                                            <InputIntegerField
                                                v-model="ws.stock_min"
                                                label=""
                                                type="number"
                                                class="w-full"
                                                placeholder="Min"
                                            />
                                        </div>
                                        <div class="flex h-full flex-col justify-between gap-1">
                                            <Label class="text-xs">Cantidad Max</Label>
                                            <InputIntegerField
                                                v-model="ws.stock_max"
                                                label=""
                                                type="number"
                                                class="w-full"
                                                placeholder="Max"
                                            />
                                        </div>
                                        <div class="flex items-base justify-end">
                                            <button
                                                v-if="!isViewMode"
                                                type="button"
                                                class="rounded-full p-1 transition hover:bg-red-100 dark:hover:bg-red-900"
                                                @click="removeWarehouseStock(idx)"
                                                title="Quitar"
                                            >
                                                <Trash2 class="h-5 w-5 text-red-500" />
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    v-if="warehouseStocks.length === 0"
                                    class="px-2 py-1 text-xs text-gray-400"
                                >
                                    No hay bodegas asociadas.
                                </div>
                            </div>
                        </div>

                        <!-- Previsualización de imagen con dropzone -->
                        <div class="flex flex-col items-center justify-center px-2 md:row-span-3">
                            <div class="flex w-full flex-col items-center">
                                <Label class="mb-1 block text-sm font-medium">Imagen</Label>

                                <!-- input file (se deshabilita por el fieldset + prop) -->
                                <input
                                    ref="fileInputRef"
                                    type="file"
                                    accept="image/*"
                                    class="hidden"
                                    :disabled="isViewMode"
                                    @change="handleFileChange"
                                />

                                <div
                                    :class="[
                                        'relative flex h-52 w-full flex-col items-stretch justify-center overflow-hidden rounded border-2 border-dashed bg-gray-50 transition',
                                        isViewMode ? 'cursor-default' : 'cursor-pointer hover:border-blue-400',
                                    ]"
                                    @click="!isViewMode && triggerFileInput()"
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
                                        <div
                                            class="absolute bottom-0 left-0 right-0 z-20 bg-black/50 py-1 text-center text-xs text-white"
                                        >
                                            {{ isViewMode ? 'Imagen del artículo' : 'Cambiar imagen' }}
                                        </div>
                                    </template>

                                    <template v-else>
                                        <div
                                            class="z-10 flex h-full w-full flex-col items-center justify-center"
                                        >
                                            <Image class="mb-2 h-12 w-12 text-gray-400" />
                                            <span class="text-sm text-gray-500">
                                                {{ isViewMode ? 'Sin imagen' : 'Haz clic para subir imagen' }}
                                            </span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SEGUNDA SECCIÓN: unidad, código, referencia, movimiento -->
                    <div class="mb-3 grid grid-cols-1 gap-4 md:grid-cols-4">
                        <div class="md:col-span-1">
                            <template v-if="isViewMode">
                                <Label>Unidad de Medida</Label>
                                <div class="mt-1 rounded-md border bg-muted px-3 py-2 text-sm">
                                    {{
                                        unitMeasures.find((u) => u.id === unitMeasure)?.name ||
                                        unitMeasure
                                    }}
                                </div>
                            </template>
                            <template v-else>
                                <SelectField
                                    v-model="unitMeasure"
                                    label="Unidad de Medida"
                                    :options="unitMeasures"
                                    option-label="name"
                                    option-value="id"
                                    required
                                />
                            </template>
                        </div>

                        <div class="md:col-span-1">
                            <InputField v-model="localForm.bar_code" label="Código de Barras" />
                        </div>
                        <div class="md:col-span-1">
                            <InputField
                                v-model="localForm.reference"
                                label="Referencia"
                                required
                            />
                        </div>
                        <div class="flex items-center md:col-span-1">
                            <SwitchField
                            :model-value="negativeMovementBool"
                            @update:modelValue="handleNegativeMovementChange"
                            label="Movimiento Negativo"
                            />
                        </div>
                    </div>

                    <!-- Descripción -->
                    <div>
                        <TextareaField v-model="localForm.description" label="Descripción" />
                    </div>

                    <!-- Campos adicionales -->
                    <ExtraFieldsForm
                        :extra-field-names="extraFieldNames"
                        v-model:extra-fields="extraFields"
                        :extra-field-options="extraFieldOptions"
                        :readonly="isViewMode"
                    />
                </fieldset>

                <!-- FOOTER -->
                <DialogFooter class="mt-6 flex flex-col-reverse gap-2 md:flex-row md:justify-end">
                    <Button
                        variant="outline"
                        type="button"
                        @click="handleClose"
                        class="w-full md:w-auto"
                    >
                        {{ dialogType === 'view' ? 'Cerrar' : 'Cancelar' }}
                    </Button>
                    <Button
                        v-if="dialogType !== 'view'"
                        variant="default"
                        type="submit"
                        class="w-full md:w-auto"
                    >
                        {{ dialogType === 'create' ? 'Guardar' : 'Actualizar' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>










