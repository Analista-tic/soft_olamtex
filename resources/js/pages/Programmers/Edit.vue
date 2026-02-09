<script setup lang="ts">

// Importación de componentes reutilizables y dependencias necesarias.
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogFooter, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import FormDialogContent from '@/components/FormDialogContent.vue';
import type { OptionParams, ProductSheet, Programmer } from '@/domain/product-sheet';
import axios from 'axios';
import { ref, computed } from 'vue';
import EcommerceSizesDialog from './EcommerceSizesDialog.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';
import Label from '@/components/ui/label/Label.vue';
import { TooltipProvider } from '@/components/ui/tooltip';

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
const dialogMode = ref<'edit' | 'view'>('edit');
const programmer = ref<Programmer>({
    id: 0,
    product_sheet_id: null,
    ecommerce_units: null, // Cambiado a null para cumplir con el tipo
    // Agrega otras propiedades necesarias con valores predeterminados
} as Programmer); // Asegúrate de que Programmer tenga todas las propiedades necesarias
const isLoading = ref(false); // Estado de carga (true mientras se guarda)
const errorMessage = ref<string | null>(null); // Mensaje de error para mostrar al usuario

const isReadOnly = computed(() => dialogMode.value === 'view');
const dialogTitle = computed(() =>
    isReadOnly.value ? 'Ver Lote Programado' : 'Editar Lote Programado'
);
const dialogDescription = computed(() =>
    isReadOnly.value
        ? 'Aqui puedes ver los detalles del programador seleccionado.'
        : 'Aqui puedes editar los detalles del programador seleccionado.'
);

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
        
        // Si es string JSON, parsearlo
        if (typeof ecommerceData === 'string') {
            programmer.value.ecommerce_units = JSON.parse(ecommerceData || '{}');
        } else if (ecommerceData && typeof ecommerceData === 'object') {
            // Si ya es un objeto, usarlo directamente
            programmer.value.ecommerce_units = ecommerceData;
        } else {
            // Si es null o undefined, inicializar como objeto vacío
            programmer.value.ecommerce_units = {};
        }
    } catch (error) {
        console.error('Error al parsear ecommerce_units:', error);
        programmer.value.ecommerce_units = {};
    }
}

/**
 * Función para abrir el diálogo y cargar los datos del programador seleccionado.
 */
function openDialog(selectedProgrammer: Programmer, mode: 'edit' | 'view' = 'edit') {
    dialogMode.value = mode;
    programmer.value = { ...selectedProgrammer };
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

function resolveEcommerceValues(value?: string | Record<string, number> | null): Record<string, number> | null {
    if (!value) {
        return null;
    }

    if (typeof value === 'string') {
        try {
            const parsed = JSON.parse(value);
            if (parsed && typeof parsed === 'object') {
                return parsed as Record<string, number>;
            }
        } catch {
            return null;
        }
    }

    if (typeof value === 'object') {
        return value;
    }

    return null;
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
        <FormDialogContent class="flex flex-col max-h-[95vh] max-w-[75vh] px-2 py-2">
            <TooltipProvider>
            <DialogHeader class="flex-shrink-0">
                <DialogTitle>{{ dialogTitle }}</DialogTitle>
                <DialogDescription>{{ dialogDescription }}</DialogDescription>
            </DialogHeader>
            <div class="overflow-y-auto flex-1 pr-2 scroll-smooth" style="scrollbar-width: thin;">
            <div class="space-y-4">
                <!-- Mostrar mensaje de error -->
                <div v-if="errorMessage" class="text-sm text-red-500">
                    {{ errorMessage }}
                </div>

                <!-- Campos del formulario -->
                <div class="flex flex-col gap-1">
                    <div class="flex items-center gap-2">
                        <Label class="text-sm font-medium">
                            Referencia<span class="text-red-500">*</span>
                        </Label>
                        <HelpTooltip
                            text="Sábana de producto asignada a este lote de producción."
                            aria-label="Ayuda sobre referencia"
                        />
                    </div>
                    <SelectField
                        id="reference"
                        v-model="programmer.product_sheet_id"
                        :options="productSheets.map((sheet) => ({ id: sheet.id, name: sheet.reference }))"
                        label=""
                        required
                        :disabled="isReadOnly"
                        class="mt-1 w-full"
                        aria-required="true"
                    />
                </div>
                <div class="flex flex-col gap-1">
                    <div class="flex items-center gap-2">
                        <Label class="text-sm font-medium">
                            Temporada<span class="text-red-500">*</span>
                        </Label>
                        <HelpTooltip
                            text="Temporada o mes en el que se programará la producción de este lote."
                            aria-label="Ayuda sobre temporada"
                        />
                    </div>
                    <SelectField
                        id="season"
                        v-model="programmer.season"
                        :options="[...Array(12).keys()].map((i) => ({
                            id: new Date(0, i).toLocaleString('es', { month: 'long' }).toUpperCase(),
                            name: new Date(0, i).toLocaleString('es', { month: 'long' }).toUpperCase(),
                        }))"
                        label=""
                        required
                        :disabled="isReadOnly"
                        class="mt-1 w-full"
                        aria-required="true"
                    />
                </div>
                <div class="flex flex-col gap-1">
                    <div class="flex items-center gap-2">
                        <Label class="text-sm font-medium">
                            Prioridad<span class="text-red-500">*</span>
                        </Label>
                        <HelpTooltip
                            text="Nivel de urgencia del lote: A (máxima), B (alta), C (media), D (baja)."
                            aria-label="Ayuda sobre prioridad"
                        />
                    </div>
                    <SelectField
                        id="priority"
                        v-model="programmer.priority"
                        :options="[ { id: 'A', name: 'A' }, { id: 'B', name: 'B' }, { id: 'C', name: 'C' }, { id: 'D', name: 'D' } ]"
                        label=""
                        required
                        :disabled="isReadOnly"
                        class="mt-1 w-full"
                        aria-required="true"
                    />
                </div>
                <div class="flex flex-col gap-1">
                    <div class="flex items-center gap-2">
                        <Label class="text-sm font-medium">
                            Destino<span class="text-red-500">*</span>
                        </Label>
                        <HelpTooltip
                            text="Canal o mercado destino donde se distribuirá el producto (tiendas, distribuidores, etc.)."
                            aria-label="Ayuda sobre destino"
                        />
                    </div>
                    <SelectField
                        id="destination"
                        v-model="programmer.destination_id"
                        :options="destinations.map((destination) => ({ id: destination.id, name: destination.name }))"
                        label=""
                        required
                        :disabled="isReadOnly"
                        class="mt-1 w-full"
                        aria-required="true"
                    />
                </div>
                <div class="flex flex-col gap-1">
                    <div class="flex items-center gap-2">
                        <Label class="text-sm font-medium">
                            Unidades<span class="text-red-500">*</span>
                        </Label>
                        <HelpTooltip
                            text="Cantidad total de unidades a programar para producción en este lote."
                            aria-label="Ayuda sobre unidades"
                        />
                    </div>
                    <InputField
                        type="number"
                        v-model="programmerUnits"
                        label=""
                        placeholder="Unds. a programar"
                        :class="{ 'border-red-500': !programmer.units }"
                        :disabled="isReadOnly"
                    />
                </div>
                <div class="flex flex-col gap-1">
                    <div class="flex items-center gap-2">
                        <Label class="text-sm font-medium">
                            E-commerce
                        </Label>
                        <HelpTooltip
                            text="Distribución de unidades específica para ventas en línea. Puedes definir cantidades por talla."
                            aria-label="Ayuda sobre e-commerce"
                        />
                    </div>
    <EcommerceSizesDialog
        :productSheet="{
            sizes: productSheets.find(ps => ps.id === programmer.product_sheet_id)?.sizes || '{}',
            reference: getReferenceName(programmer.product_sheet_id)
        }"
        :existingValues="resolveEcommerceValues(programmer.ecommerce_units)"
        :readonly="isReadOnly"
        @sizesUpdated="(data) => {
            if (!isReadOnly) {
                programmer.ecommerce_units = data.sizes;
            }
        }"
    />
                </div>
            </div>
            </div>
            </TooltipProvider>
            <DialogFooter class="flex-shrink-0 border-t pt-4 mt-4 flex flex-col gap-2 md:flex-row md:justify-end">
                <div class="w-full md:w-auto">
                    <button
                        type="button"
                        class="mx-auto flex w-full max-w-[140px] items-center justify-center gap-2 rounded-full border border-primary/40 bg-white px-5 py-2 text-sm font-semibold text-primary transition hover:bg-primary/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary"
                        @click="isDialogOpen = false"
                    >
                        {{ isReadOnly ? 'Cerrar' : 'Cancelar' }}
                    </button>
                </div>
                <div v-if="!isReadOnly" class="w-full md:w-auto">
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
        </FormDialogContent>
    </Dialog>
</template>
