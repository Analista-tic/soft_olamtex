<script setup lang="ts">
import EcommerceSizesDialog from './EcommerceSizesDialog.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { OptionParams, ProductSheet, Programmer } from '@/types';
import axios from 'axios';
import { PlusCircle } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import CreateProductSheetDialog from '../ProductSheets/CreateProductSheetDialog.vue';

const emit = defineEmits(['programmerCreated']);

const props = defineProps<{
    productSheets: ProductSheet[]; // Recibir la lista de ProductSheet
    destinations: OptionParams[];
    sections: OptionParams[];
    groups: OptionParams[];
    brands: OptionParams[];
    worlds: OptionParams[];
    bases: OptionParams[];
}>();

const isDialogOpen = ref(false);

const lotsForm = ref<Array<Partial<Programmer>>>([]); // Array para los lotes

const form = ref<{
    lotsCount: number | null; // Cantidad de lotes
    productSheetId: number | null; // ID de la ficha de producto seleccionada
}>({
    lotsCount: null,
    productSheetId: null,
});

const months: Array<{ id: string; name: string }> = [
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

const priorities: Array<{ id: string; name: string }> = [
    { id: 'A', name: 'A' },
    { id: 'B', name: 'B' },
    { id: 'C', name: 'C' },
    { id: 'D', name: 'D' },
];

const selectedProductSheet = computed<ProductSheet | null>(() => {
    return props.productSheets.find(sheet => sheet.id === form.value.productSheetId) || null;
});

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

// Función para generar la lista de referencias
function generateLots() {
    if (!form.value.lotsCount || form.value.lotsCount <= 0) {
        alert('Por favor, ingresa una cantidad válida de lotes.');
        return;
    }

    if (!form.value.productSheetId) {
        alert('Por favor, selecciona una referencia de ficha de producto.');
        return;
    }

    lotsForm.value = Array.from({ length: form.value.lotsCount }, (_, index) => ({
        lot: `MF${index + 1}`, // Identificador único para el lote
        product_sheet_id: form.value.productSheetId, // Asignar el productSheetId seleccionado
        season: '', // Cambiar null a una cadena vacía
        destination_id: null, // Inicializar destination_id como null
        priority: '', // Cambiar null a una cadena vacía
        status: 'incomplete',
        isEcommerce: false, // Inicializar como falso
        ecommerce_units: null, // Inicializar como null
        units: undefined, // Cantidad de unidades en almacén
    }));
}

const isFormSubmitted = ref(false); // Bandera para saber si el formulario fue enviado

function validateForm() {
    formErrors.value = []; // Reiniciar errores

    if (!isFormSubmitted.value) return; // No validar si el formulario no ha sido enviado

    lotsForm.value.forEach((lot, index) => {
        if (!lot.product_sheet_id) formErrors.value.push(`El ID de Ficha de Producto es requerido para el lote ${index + 1}.`);
        if (!lot.season) formErrors.value.push(`La Temporada es requerida para el lote ${index + 1}.`);
        if (!lot.destination_id) formErrors.value.push(`El Destino es requerido para el lote ${index + 1}.`); // Validar destination_id
        if (!lot.priority) formErrors.value.push(`La Prioridad es requerida para el lote ${index + 1}.`);
        if (!lot.status) formErrors.value.push(`El Estado es requerido para el lote ${index + 1}.`);
        if (lot.isEcommerce && (!lot.ecommerce_units || Object.values(lot.ecommerce_units).reduce((a, b) => a + b, 0) <= 0)) {
            formErrors.value.push(`La cantidad de E-commerce debe ser mayor a 0 para el lote ${index + 1}.`);
        }
    });
}

function createProgrammer() {
    isFormSubmitted.value = true; // Marcar el formulario como enviado
    validateForm();

    if (formErrors.value.length > 0) {
        alert('Por favor, corrige los errores antes de continuar.');
        return;
    }

    // Preparar los lotes para enviarlos al backend
    const lotsToSend = lotsForm.value.map(lot => ({
        product_sheet_id: lot.product_sheet_id, // ID de la ficha de producto
        season: lot.season, // Temporada
        destination_id: lot.destination_id || null, // Asegurar que destination_id sea enviado como un número o null
        priority: lot.priority, // Prioridad
        units: lot.units, // Cantidad de unidades
        ecommerce_units: lot.ecommerce_units || null, // Enviar como array o null
        status: lot.status, // Estado
    }));

    // Enviar los datos al backend
    axios
        .post('/diseno/programador', { lots: lotsToSend }) // Enviar el array de lotes
        .then(() => {
            alert('Programadores creados exitosamente.');
            emit('programmerCreated'); // Emitir evento
            resetForm();
            isDialogOpen.value = false; // Cerrar el diálogo
        })
        .catch(error => {
            console.error('Error al crear los programadores:', error);
            alert('Hubo un error al crear los programadores.');
        });
}

const formErrors = ref<string[]>([]); // Lista de errores del formulario

function resetForm() {
    form.value.lotsCount = null;
    lotsForm.value = [];
    isFormSubmitted.value = false; // Reiniciar la bandera
}

watch(lotsForm, () => {
    validateForm(); // Validar el formulario cada vez que cambie lotsForm
}, { deep: true });

const productSheets = ref<ProductSheet[]>(props.productSheets); // Lista reactiva de ProductSheets

// Función para añadir un nuevo ProductSheet al select
function addProductSheet(response: { success: boolean; message: string; data: ProductSheet } | undefined) {
    if (!response || !response.success || !response.data || !response.data.id) {
        console.error('El ProductSheet creado no es válido:', response);
        alert('Hubo un problema al crear la ficha de producto. Intenta nuevamente.');
        return;
    }

    const newProductSheet = response.data; // Extraer el ProductSheet real
    productSheets.value = [...productSheets.value, newProductSheet]; // Crear un nuevo array para forzar la reactividad
    form.value.productSheetId = newProductSheet.id; // Seleccionar automáticamente el nuevo ProductSheet
}

</script>

<template>
    <Dialog @open="resetForm" v-model:open="isDialogOpen">
        <DialogTrigger as-child>
            <Button variant="default" size="sm"><PlusCircle /> Crear</Button>
        </DialogTrigger>
        <DialogContent class="sm:max-w-6xl max-h-screen overflow-y-auto">
            <DialogHeader>
                <DialogTitle>Crear Nuevo Programador</DialogTitle>
                <DialogDescription> Completa los campos para crear un programador. Haz clic en guardar cuando termines. </DialogDescription>
            </DialogHeader>

            <div class="grid grid-cols-2 gap-4 gap-y-1">
                <div class="col-span-2">
                    <div class="flex space-x-4">
                        <!-- Campo para la referencia seleccionada -->
                        <SelectField
                            v-model="form.productSheetId"
                            label="Referencia Seleccionada"
                            :options="productSheets.map(sheet => ({ id: sheet.id, name: sheet.reference }))"
                            required
                        />
                        <!-- Campo para la cantidad de lotes -->
                        <InputField 
                            v-model="form.lotsCount" 
                            label="Cantidad de Lotes" 
                            placeholder="Número de lotes" 
                            type="number" 
                            required 
                            @input="generateLots"
                        />
                        <div class="flex items-end">
                            <!-- Crear ficha de producto -->
                            <CreateProductSheetDialog
                                :sections="props.sections"
                                :groups="props.groups"
                                :brands="props.brands"
                                :worlds="props.worlds"
                                :bases="props.bases"
                                @productSheetCreated="addProductSheet"
                            />
                        </div>
                    </div>
                    <!-- Tabla informativa para mostrar detalles del ProductSheet seleccionado -->
                    <div v-if="selectedProductSheet">
                        <table class="bg-gray-100 w-full text-sm mt-2 text-left border border-gray-300 rounded-lg overflow-hidden shadow-sm">
                            <tbody>
                                <tr>
                                    <td class="px-3 py-2 border font-bold">Referencia:</td>
                                    <td class="px-3 py-2 border">{{ selectedProductSheet.reference }}</td>
                                    <td class="px-3 py-2 border font-bold">Sección:</td>
                                    <td class="px-3 py-2 border">{{ selectedProductSheet.section.name }}</td>
                                    <td class="px-3 py-2 border font-bold">Grupo:</td>
                                    <td class="px-3 py-2 border">{{ selectedProductSheet.group.name }}</td>
                                    <td class="px-3 py-2 border font-bold">Marca:</td>
                                    <td class="px-3 py-2 border">{{ selectedProductSheet.brand.name }}</td>
                                    <td class="px-3 py-2 border font-bold">Mundo:</td>
                                    <td class="px-3 py-2 border">{{ selectedProductSheet.world.name }}</td>
                                </tr>
                                <tr>
                                    <td class="px-3 py-2 border font-bold">Base:</td>
                                    <td class="px-3 py-2 border">{{ selectedProductSheet.base.name }}</td>
                                    <td class="px-3 py-2 border font-bold">Tallas:</td>
                                    <td class="px-3 py-2 border" colspan="3">{{ getTrueSizes(selectedProductSheet.sizes) }}</td>
                                    <td class="px-3 py-2 border font-bold">Precio Fact:</td>
                                    <td class="px-3 py-2 border">{{ selectedProductSheet.billing_price }}</td>
                                    <td class="px-3 py-2 border font-bold">Precio Venta:</td>
                                    <td class="px-3 py-2 border">{{ selectedProductSheet.selling_price }}</td>
                                </tr>
                                <tr>
                                    <td class="px-3 py-2 border font-bold">Descripción:</td>
                                    <td class="px-3 py-2 border" colspan="8">{{ selectedProductSheet.description }}</td>
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
                <table class="bg-gray-100 w-full text-sm text-left border border-gray-300 rounded-lg overflow-hidden shadow-sm text-center">
                    <thead>
                        <tr>
                            <th class="px-3 py-2 border">Lote</th>
                            <th class="px-3 py-2 border">Referencia</th>
                            <th class="px-3 py-2 border">Temporada</th>
                            <th class="px-3 py-2 border">Destino</th>
                            <th class="px-3 py-2 border">Prioridad</th>
                            <th class="px-3 py-2 border">E-commerce?</th>
                            <th class="px-3 py-2 border">Cant. Almacenes</th>
                            <!-- Mostrar la columna "Cant. E-commerce" solo si algún lote tiene isEcommerce activado -->
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
                                    v-model="lot.units"
                                    label=""
                                    placeholder="Unds. a programar"
                                    :class="{ 'border-red-500': !lot.units }"
                                />
                            </td>
                            <!-- Mostrar la celda "Cant. E-commerce" solo si isEcommerce está activado -->
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

            <DialogFooter class="mt-6 flex justify-end">
                <Button size="sm" type="submit" :disabled="formErrors.length > 0" @click="createProgrammer">
                    Guardar
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>