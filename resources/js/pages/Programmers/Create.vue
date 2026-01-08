<script setup lang="ts">
import EcommerceSizesDialog from './EcommerceSizesDialog.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import type { OptionParams, ProductSheet, Programmer } from '@/domain/product-sheet';
import axios from 'axios';
import { PlusCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AlertBox from '@/components/AlertBox.vue';
import CreateProductSheetDialog from '../ProductSheets/ProductSheetDialog.vue';

const emit = defineEmits(['programmerCreated']);

const props = defineProps<{
    productSheets: ProductSheet[];
    destinations: OptionParams[];
    sections: OptionParams[];
    groups: OptionParams[];
    brands: OptionParams[];
    worlds: OptionParams[];
    fabricBases: OptionParams[];
    yarnBases: OptionParams[];
}>();

const isDialogOpen = ref(false);
const productSheetDialogOpen = ref(false);

// Alert state
const alertMessage = ref('');
const alertType = ref<'success' | 'error' | 'info' | 'warning'>('info');
const showAlert = ref(false);
const dialogInnerRef = ref<HTMLElement | null>(null);

function setAlert(message: string, type: 'success' | 'error' | 'info' | 'warning' = 'info') {
    alertMessage.value = message;
    alertType.value = type;
    showAlert.value = true;
    if (dialogInnerRef.value && typeof dialogInnerRef.value.scrollTo === 'function') {
        dialogInnerRef.value.scrollTo({ top: 0, behavior: 'smooth' });
    } else {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

function clearAlert() {
    alertMessage.value = '';
    showAlert.value = false;
}

const lotsForm = ref<Array<Partial<Programmer>>>([]);

const form = ref<{
    lotsCount: number | null | undefined;
    productSheetId: number | null; // Asegurar que sea del tipo correcto
}>({
    lotsCount: null,
    productSheetId: null,
});

const lotsCountModel = computed({
    get() {
        return form.value.lotsCount ?? '';
    },
    set(value: string | number) {
        if (value === '' || value === null || value === undefined) {
            form.value.lotsCount = null;
            return;
        }

        const normalized =
            typeof value === 'number' ? value : Number(value.toString().replace(',', '.'));
        form.value.lotsCount = Number.isNaN(normalized) ? null : normalized;
    },
});

// Asegurar que las opciones de `SelectField` sean válidas
const productSheetOptions = computed(() =>
    props.productSheets.map(sheet => ({ id: sheet.id, name: sheet.reference }))
);

const months = [
    { id: 'ENERO', name: 'ENERO' },
    { id: 'FEBRERO', name: 'FEBRERO' },
    { id: 'MARZO', name: 'MARZO' },
    { id: 'ABRIL', name: 'ABRIL' },
    { id: 'MAYO', name: 'MAYO' },
    { id: 'JUNIO', name: 'JUNIO' },
    { id: 'JULIO', name: 'JULIO' },
    { id: 'AGOSTO', name: 'AGOSTO' },
    { id: 'SEPTIEMBRE', name: 'SEPTIEMBRE' },
    { id: 'OCTUBRE', name: 'OCTUBRE' },
    { id: 'NOVIEMBRE', name: 'NOVIEMBRE' },
    { id: 'DICIEMBRE', name: 'DICIEMBRE' },
];

const priorities = [
    { id: 'A', name: 'A' },
    { id: 'B', name: 'B' },
    { id: 'C', name: 'C' },
    { id: 'D', name: 'D' },
];

const selectedProductSheet = computed(() => {
    return props.productSheets.find(sheet => sheet.id === form.value.productSheetId) || null;
});

function generateLots() {
    if (!form.value.lotsCount || form.value.lotsCount <= 0) {
        setAlert('Por favor, ingresa una cantidad válida de lotes.', 'warning');
        return;
    }

    if (!form.value.productSheetId) {
        setAlert('Por favor, selecciona una referencia de ficha de producto.', 'warning');
        return;
    }

    lotsForm.value = Array.from({ length: form.value.lotsCount }, (_, index) => ({
        lot: `MF${index + 1}`,
        product_sheet_id: form.value.productSheetId,
        season: '',
        destination_id: null,
        priority: '',
        status: 'incomplete',
        isEcommerce: false,
        ecommerce_units: null,
        units: undefined,
    }));
}

const formErrors = ref<string[]>([]);

function validateForm() {
    formErrors.value = [];

    lotsForm.value.forEach((lot, index) => {
        if (!lot.product_sheet_id) formErrors.value.push(`El ID de Ficha de Producto es requerido para el lote ${index + 1}.`);
        if (!lot.season) formErrors.value.push(`La Temporada es requerida para el lote ${index + 1}.`);
        if (!lot.destination_id) formErrors.value.push(`El Destino es requerido para el lote ${index + 1}.`);
        if (!lot.priority) formErrors.value.push(`La Prioridad es requerida para el lote ${index + 1}.`);
        if (!lot.units || lot.units <= 0) formErrors.value.push(`Las unidades deben ser mayores a 0 para el lote ${index + 1}.`);
    });
}

function createProgrammer() {
    validateForm();

    if (formErrors.value.length > 0) {
        setAlert('Por favor, corrige los errores antes de continuar.', 'warning');
        return;
    }

    const lotsToSend = lotsForm.value.map(lot => ({
        product_sheet_id: lot.product_sheet_id,
        season: lot.season,
        destination_id: lot.destination_id || null,
        priority: lot.priority,
        units: lot.units,
        ecommerce_units: lot.ecommerce_units || null,
        status: lot.status,
    }));

    axios
        .post('/comercial/programador', { lots: lotsToSend })
        .then(() => {
            setAlert('Programadores creados exitosamente.', 'success');
            emit('programmerCreated');
            resetForm(); // Llamar a resetForm para limpiar el formulario
            isDialogOpen.value = false; // Cerrar el diálogo
        })
        .catch(error => {
            console.error('Error al crear los programadores:', error);
            setAlert('Hubo un error al crear los programadores.', 'error');
        });
}

function resetForm() {
    form.value = {
        lotsCount: null,
        productSheetId: null,
    }; // Reiniciar los valores del formulario
    lotsForm.value = []; // Limpiar los lotes generados
    formErrors.value = []; // Limpiar los errores del formulario
    productSheetDialogOpen.value = false;
}

function closeDialog() {
    resetForm();
    isDialogOpen.value = false;
}

const productSheets = ref<ProductSheet[]>(props.productSheets);

function addProductSheet(response: { success: boolean; message: string; data: ProductSheet } | undefined) {
    if (!response || !response.success || !response.data || !response.data.id) {
        console.error('El ProductSheet creado no es válido:', response);
        setAlert('Hubo un problema al crear la ficha de producto. Intenta nuevamente.', 'error');
        return;
    }

    const newProductSheet = response.data;
    productSheets.value = [...productSheets.value, newProductSheet];
    form.value.productSheetId = newProductSheet.id;
}

// Función para obtener las claves con valores `true` en `sizes`
function getTrueSizes(sizes: string | undefined): string {
    if (!sizes) return ''; // Manejar el caso en que sizes sea undefined
    let parsedSizes: Record<string, boolean>;
    try {
        parsedSizes = JSON.parse(sizes); // Convertir el string en JSON
    } catch (error) {
        console.error('Error al parsear sizes:', error);
        return '';
    }
    return Object.entries(parsedSizes)
        .filter(([, value]) => value === true) // Filtrar solo los valores `true`
        .map(([key]) => key) // Obtener solo las claves
        .join(', '); // Unirlas con comas
}
</script>

<template>
    <Dialog @open="resetForm" v-model:open="isDialogOpen">
        <DialogTrigger as-child>
            <Button
                variant="default"
                size="sm"
                class="flex items-center gap-2 rounded-full px-5 py-2 text-sm font-semibold shadow-sm h-10"
            >
                <PlusCircle class="h-4 w-4" />
                Nuevo Programador
            </Button>
        </DialogTrigger>
        <DialogContent class="sm:max-w-6xl max-h-[90vh] flex flex-col">
            <DialogHeader class="flex-shrink-0">
                <DialogTitle>Crear Nuevo Programador</DialogTitle>
                <DialogDescription> Completa los campos para crear un programador. Haz clic en guardar cuando termines. </DialogDescription>
            </DialogHeader>
            
            <div ref="dialogInnerRef" class="overflow-y-auto flex-1 pr-2 scroll-smooth" style="scrollbar-width: thin;">
                <transition name="fade">
                    <div v-if="showAlert && alertMessage" class="mb-4">
                        <AlertBox
                            :type="alertType"
                            :title="alertType === 'warning' ? 'Advertencia' : alertType === 'success' ? 'Éxito' : alertType === 'info' ? 'Información' : 'Error'"
                            :message="alertMessage"
                            class="pointer-events-auto"
                            @close="clearAlert"
                        />
                    </div>
                </transition>

                <div class="grid grid-cols-2 gap-4 gap-y-1 pt-5">
                <div class="col-span-2">
                    <div class="flex space-x-4">
                        <!-- Campo para la referencia seleccionada -->
                        <SelectField
                            v-model="form.productSheetId"
                            label="Referencia Seleccionada"
                            :options="productSheetOptions"
                            required
                        />
                        <!-- Campo para la cantidad de lotes -->
                        <InputField
                            v-model="lotsCountModel"
                            label="Cantidad de Lotes"
                            placeholder="Numero de lotes"
                            type="number"
                            required
                            @update:modelValue="generateLots"
                        />
                        <div class="flex flex-col items-end gap-2">
                            <Button
                                size="sm"
                                variant="outline"
                                class="px-4 rounded-full"
                                type="button"
                                @click="productSheetDialogOpen = true"
                            >
                                Crear ficha de producto
                            </Button>
                            <CreateProductSheetDialog
                                mode="create"
                                :sections="props.sections"
                                :groups="props.groups"
                                :brands="props.brands"
                                :worlds="props.worlds"
                                :fabricBases="props.fabricBases"
                                :yarnBases="props.yarnBases"
                                :open="productSheetDialogOpen"
                                @update:open="(value) => (productSheetDialogOpen = value)"
                                @productSheetSaved="addProductSheet"
                            />
                        </div>
                    </div>
                    <!-- Tabla informativa para mostrar detalles del ProductSheet seleccionado -->
                    <div v-if="selectedProductSheet" class="mt-4">
                        <h3 class="text-lg font-bold mb-2">Detalles de la Ficha de Producto</h3>
                        <table class="w-full text-sm text-left border border-gray-300 rounded-lg overflow-hidden">
                            <tbody>
                                <tr>
                                    <td class="px-4 py-2 font-bold">Referencia:</td>
                                    <td class="px-4 py-2 border border-l-0">{{ selectedProductSheet.reference || 'Sin referencia' }}</td>
                                    <td class="px-4 py-2 font-bold">Sección:</td>
                                    <td class="px-4 py-2 border border-l-0">{{ selectedProductSheet.section?.name || 'Sin sección' }}</td>
                                    <td class="px-4 py-2 font-bold">Grupo:</td>
                                    <td class="px-4 py-2 border border-l-0">{{ selectedProductSheet.group?.name || 'Sin grupo' }}</td>
                                    <td class="px-4 py-2 font-bold">Marca:</td>
                                    <td class="px-4 py-2 border border-l-0">{{ selectedProductSheet.brand?.name || 'Sin marca' }}</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-1 font-bold border border-r-0">Mundo:</td>
                                    <td class="px-4 py-1 border border-l-0">{{ selectedProductSheet.world?.name || 'Sin mundo' }}</td>
                                    <td class="px-4 py-1 font-bold border border-r-0">Base:</td>
                                    <td class="px-4 py-1 border border-l-0">{{ selectedProductSheet.fabric_base?.name || 'Sin base' }}</td>
                                    <td class="px-4 py-1 font-bold border border-r-0">Precio Facturación:</td>
                                    <td class="px-4 py-1 border border-l-0">{{ selectedProductSheet.billing_price ?? 'N/A' }}</td>
                                    <td class="px-4 py-1 font-bold border border-r-0">Precio Venta:</td>
                                    <td class="px-4 py-1 border border-l-0">{{ selectedProductSheet.selling_price ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-5 font-bold border">Tallas:</td>
                                    <td class="px-8 py-5 border" colspan="3">{{ getTrueSizes(selectedProductSheet.sizes) || 'Sin tallas' }}</td>
                                    <td class="px-4 py-5 font-bold border">Descripción:</td>
                                    <td class="px-4 py-5 border" colspan="3">{{ selectedProductSheet.description || 'Sin descripción' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div v-if="formErrors.length > 0" class="bg-red-100 text-red-700 p-4 rounded mb-4">
                <ul>
                    <li v-for="(error, index) in formErrors" :key="index">{{ error }}</li>
                </ul>
            </div>

            <div v-if="lotsForm.length > 0" class="mt-2">
                <h3 class="text-base font-bold mb-2">Lotes Generados:</h3>
                <table class="w-full text-sm border border-gray-300 rounded-lg overflow-hidden shadow-sm text-center">
                    <thead>
                        <tr>
                            <th class="px-3 py-2 border">Lote</th>
                            <th class="px-3 py-2 border">Referencia</th>
                            <th class="px-3 py-2 border">Temporada</th>
                            <th class="px-3 py-2 border">Destino</th>
                            <th class="px-3 py-2 border">Prioridad</th>
                            <th class="px-3 py-2 border">E-commerce?</th>
                            <th class="px-3 py-2 border">Cant. Almacenes</th>
                            <th v-if="lotsForm.some(lot => lot.isEcommerce)" class="px-3 py-2 border">Cant. E-commerce</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(lot, index) in lotsForm" :key="index">
                            <td class="px-3 py-2 border">MF300{{ index + 1 }}</td>
                            <td class="px-3 py-2 border">{{ lot.product_sheet_id }}</td>
                            <td class="px-3 py-2 border">
                                <SelectField
                                    v-model="lot.season"
                                    :options="months"
                                    label=""
                                    :class="{ 'border-red-500': !lot.season }"
                                />
                            </td>
                            <td class="px-3 py-2 border">
                                <SelectField
                                    v-model="lot.destination_id"
                                    :options="props.destinations.map(destination => ({ id: destination.id, name: destination.name }))"
                                    label=""
                                    :class="{ 'border-red-500': !lot.destination_id }"
                                />
                            </td>
                            <td class="px-3 py-2 border">
                                <SelectField
                                    v-model="lot.priority"
                                    :options="priorities"
                                    label=""
                                    :class="{ 'border-red-500': !lot.priority }"
                                />
                            </td>
                            <td class="px-3 py-2 border">
                                <input 
                                    type="checkbox" 
                                    v-model="lot.isEcommerce" 
                                    class="form-checkbox h-5 w-5 text-blue-600"
                                />
                            </td>
                            <td class="px-3 py-2 border">
                                <InputField
                                    v-model="lot.units as number | undefined"
                                    label=""
                                    placeholder="Unds. a programar"
                                    :class="{ 'border-red-500': !lot.units }"
                                />
                            </td>
                            <td v-if="lot.isEcommerce" class="px-3 py-2 border">
                                <EcommerceSizesDialog
                                    :productSheet="{ sizes: selectedProductSheet?.sizes || '{}', reference: selectedProductSheet?.reference || '' }"
                                    @sizesUpdated="(sizes) => lot.ecommerce_units = sizes"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <DialogFooter class="mt-6 flex flex-col gap-2 md:flex-row md:justify-end">
                <div class="w-full md:w-auto">
                    <button
                        type="button"
                        class="mx-auto flex w-full max-w-[140px] items-center justify-center gap-2 rounded-full border border-primary/40 bg-white px-5 py-2 text-sm font-semibold text-primary transition hover:bg-primary/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary"
                        @click="closeDialog"
                    >
                        Cancelar
                    </button>
                </div>
                <div class="w-full md:w-auto">
                    <Button
                        size="sm"
                        type="button"
                        class="mx-auto flex w-full max-w-[140px] items-center justify-center rounded-full px-5 py-2 text-sm font-semibold shadow-sm"
                        :disabled="formErrors.length > 0"
                        @click="createProgrammer"
                    >
                        Guardar
                    </Button>
                </div>
            </DialogFooter>
            </div>
        </DialogContent>
    </Dialog>
</template>
