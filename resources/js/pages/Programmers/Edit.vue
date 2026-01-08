<script setup lang="ts">

// Importación de componentes reutilizables y dependencias necesarias.
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import type { OptionParams, ProductSheet, Programmer } from '@/domain/product-sheet';
import axios from 'axios';
import { ref, computed } from 'vue';
import EcommerceSizesDialog from './EcommerceSizesDialog.vue';

/**
 * Definición de las propiedades recibidas desde el componente padre.
 */
const props = defineProps<{
    destinations: OptionParams[]; // Lista de destinos con id y nombre
    productSheets: ProductSheet[]; // Lista de fichas de producto
}>();

// Desestructuración de las propiedades para evitar advertencias del linter
const { destinations, productSheets } = props;

/**
 * Definición de eventos emitidos hacia el componente padre.
 */
const emits = defineEmits<{
    (event: 'programmerUpdated'): void; // Evento emitido cuando se actualiza un programador
}>();

/**
 * Variables reactivas para el estado del componente.
 */
const isDialogOpen = ref(false); // Estado del diálogo (abierto/cerrado)
const programmer = ref<Programmer>({
    id: 0,
    product_sheet_id: null,
    ecommerce_units: null, // Cambiado a null para cumplir con el tipo
    // Agrega otras propiedades necesarias con valores predeterminados
} as Programmer); // Asegúrate de que Programmer tenga todas las propiedades necesarias
const isLoading = ref(false); // Estado de carga (true mientras se guarda)
const errorMessage = ref<string | null>(null); // Mensaje de error para mostrar al usuario

/**
 * Propiedad computada para manejar el valor predeterminado de `programmer.units`
 */
const programmerUnits = computed({
    get: () => programmer.value.units ?? 0,
    set: (value: number) => {
        programmer.value.units = value;
    },
});

/**
 * Función para obtener el nombre de la referencia basado en el ID de la ficha de producto.
 */
function getReferenceName(productSheetId: number | null): string {
    const productSheet = props.productSheets.find((sheet) => sheet.id === productSheetId);
    return productSheet ? productSheet.reference : 'Referencia';
}

/**
 * Inicializa las tallas de e-commerce al abrir el diálogo.
 */
function initializeEcommerceSizes() {
    try {
        const ecommerceData = programmer.value.ecommerce_units;
        const parsedData = typeof ecommerceData === 'string' ? JSON.parse(ecommerceData || '{}') : ecommerceData || {};
        const sizes = parsedData.sizes || {};
        programmer.value.ecommerce_units = Object.fromEntries(
            Object.entries(sizes).map(([key, value]) => [key, Number(value) || 0])
        );
    } catch (error) {
        console.error('Error al parsear ecommerce_units:', error);
        programmer.value.ecommerce_units = null; // Fallback a null para cumplir con el tipo
    }
}

/**
 * Función para abrir el diálogo y cargar los datos del programador seleccionado.
 */
function openDialog(selectedProgrammer: Programmer) {
    programmer.value = {
        ...selectedProgrammer,
        ecommerce_units: selectedProgrammer.ecommerce_units || '{}', // Asegurarse de que sea un string válido
    };
    initializeEcommerceSizes(); // Inicializar las tallas de e-commerce
    isDialogOpen.value = true; // Abrir el diálogo
    errorMessage.value = null; // Limpiar cualquier mensaje de error previo
    console.log('Programador cargado para edición:', programmer.value); // Depuración
}

/**
 * Exponer la función `openDialog` para que pueda ser llamada desde el componente padre.
 */
defineExpose({
    openDialog,
});

/**
 * Validar los campos requeridos antes de guardar los cambios.
 */
function validateFields(): boolean {
    const requiredFields = [
        { field: programmer.value?.product_sheet_id, message: 'La referencia es obligatoria.' },
        { field: programmer.value?.season, message: 'La temporada es obligatoria.' },
        { field: programmer.value?.priority, message: 'La prioridad es obligatoria.' },
        { field: programmer.value?.destination_id, message: 'El destino es obligatorio.' },
        { field: programmer.value?.units && programmer.value.units > 0, message: 'Las unidades deben ser un número mayor a 0.' },
    ];

    for (const { field, message } of requiredFields) {
        if (!field) {
            errorMessage.value = message;
            return false;
        }
    }

    errorMessage.value = null; // Limpiar mensaje de error si todo es válido
    return true;
}

/**
 * Función para guardar los cambios realizados en el programador.
 */
async function saveChanges() {
    if (!validateFields()) return; // Validar campos antes de continuar

    if (!programmer.value?.id) {
        errorMessage.value = 'No se encontró el ID del programador.';
        return;
    }

    isLoading.value = true; // Activar el estado de carga
    try {
        // Preparar el payload para la solicitud PUT
        const payload = {
            ...programmer.value,
            ecommerce_units: typeof programmer.value.ecommerce_units === 'string'
                ? programmer.value.ecommerce_units
                : JSON.stringify(programmer.value.ecommerce_units || {}),
        };

        console.log('Payload enviado:', payload); // Depuración

        // Realizar la solicitud PUT al servidor
        const response = await axios.put(`/comercial/programador/${programmer.value.id}`, payload);

        console.log('Respuesta del servidor:', response.data); // Depuración

        emits('programmerUpdated'); // Emitir evento al componente padre
        isDialogOpen.value = false; // Cerrar el diálogo
    } catch (error: unknown) {
        // Manejar errores de Axios
        if (axios.isAxiosError(error)) {
            console.error('Error al actualizar el programador:', error.response?.data || error);

            // Mostrar un mensaje de error detallado al usuario
            errorMessage.value = error.response?.data?.message || 'Hubo un error al actualizar el programador. Por favor, inténtalo de nuevo.';
        } else {
            // Manejar errores desconocidos
            console.error('Error desconocido:', error);
            errorMessage.value = 'Ocurrió un error inesperado. Por favor, inténtalo de nuevo.';
        }
    } finally {
        isLoading.value = false; // Desactivar el estado de carga
    }
}
</script>

<template>
    <!-- Diálogo para editar el programador -->
    <Dialog 
        v-model:open="isDialogOpen" 
        aria-labelledby="dialog-title" 
        aria-describedby="dialog-description"
    >
        <DialogContent class="max-h-[90vh] flex flex-col max-w-2xl">
            <DialogHeader class="flex-shrink-0">
                <DialogTitle>Editar Lote Programado</DialogTitle>
                <DialogDescription> Aquí puedes editar los detalles del programador seleccionado. </DialogDescription>
            </DialogHeader>
            <div class="overflow-y-auto flex-1 pr-2 scroll-smooth" style="scrollbar-width: thin;">
            <div class="space-y-4">
                <!-- Mostrar mensaje de error -->
                <div v-if="errorMessage" class="text-sm text-red-500">
                    {{ errorMessage }}
                </div>

                <!-- Campos del formulario -->
                <div>
                    <SelectField
                        id="reference"
                        v-model="programmer.product_sheet_id"
                        :options="productSheets.map((sheet) => ({ id: sheet.id, name: sheet.reference }))"
                        label="Referencia"
                        required
                        class="mt-1 w-full"
                        aria-required="true"
                    />
                </div>
                <div>
                    <SelectField
                        id="season"
                        v-model="programmer.season"
                        :options="[...Array(12).keys()].map((i) => ({
                            id: new Date(0, i).toLocaleString('es', { month: 'long' }).toUpperCase(),
                            name: new Date(0, i).toLocaleString('es', { month: 'long' }).toUpperCase(),
                        }))"
                        label="Temporada"
                        required
                        class="mt-1 w-full"
                        aria-required="true"
                    />
                </div>
                <div>
                    <SelectField
                        id="priority"
                        v-model="programmer.priority"
                        :options="[ { id: 'A', name: 'A' }, { id: 'B', name: 'B' }, { id: 'C', name: 'C' }, { id: 'D', name: 'D' } ]"
                        label="Prioridad"
                        required
                        class="mt-1 w-full"
                        aria-required="true"
                    />
                </div>
                <div>
                    <SelectField
                        id="destination"
                        v-model="programmer.destination_id"
                        :options="destinations.map((destination) => ({ id: destination.id, name: destination.name }))"
                        label="Destino"
                        required
                        class="mt-1 w-full"
                        aria-required="true"
                    />
                </div>
                <div>
                    <InputField
                        type="number"
                        v-model="programmerUnits"
                        label="Unidades"
                        placeholder="Unds. a programar"
                        :class="{ 'border-red-500': !programmer.units }"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium">E-commerce</label>
                    <EcommerceSizesDialog
                        :productSheet="{
                            sizes: JSON.stringify({ sizes: programmer.ecommerce_units || {}, reference: getReferenceName(programmer.product_sheet_id) }),
                            reference: getReferenceName(programmer.product_sheet_id)
                        }"
                        @sizesUpdated="(sizes) => programmer.ecommerce_units = sizes"
                    />
                </div>
            </div>
            </div>
            <DialogFooter class="flex-shrink-0 border-t pt-4 mt-4 flex flex-col gap-2 md:flex-row md:justify-end">
                <div class="w-full md:w-auto">
                    <button
                        type="button"
                        class="mx-auto flex w-full max-w-[140px] items-center justify-center gap-2 rounded-full border border-primary/40 bg-white px-5 py-2 text-sm font-semibold text-primary transition hover:bg-primary/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary"
                        @click="isDialogOpen = false"
                    >
                        Cancelar
                    </button>
                </div>
                <div class="w-full md:w-auto">
                    <Button
                        type="button"
                        :class="[
                            'mx-auto flex w-full max-w-[140px] items-center justify-center rounded-full px-5 py-2 text-sm font-semibold shadow-sm',
                            isLoading ? 'opacity-60 cursor-not-allowed' : '',
                        ]"
                        :disabled="isLoading"
                        @click="saveChanges"
                    >
                        {{ isLoading ? 'Guardando...' : 'Guardar' }}
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
