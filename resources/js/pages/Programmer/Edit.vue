<script setup lang="ts">
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { OptionParams, ProductSheet, Programmer } from '@/types';
import axios from 'axios';
import { defineEmits, defineExpose, ref } from 'vue';
import EcommerceSizesDialog from './EcommerceSizesDialog.vue';

// Tipado de las propiedades recibidas
const props = defineProps<{
    destinations: OptionParams[]; // Lista de destinos con id y nombre
    productSheets: ProductSheet[]; // Lista de fichas de producto
}>();

// Desestructurar props para evitar advertencias del linter
const { destinations, productSheets } = props;

// Definición de eventos emitidos
const emits = defineEmits<{
    (event: 'programmerUpdated'): void;
}>();

// Estado del diálogo
const isDialogOpen = ref(false);

// Programador que se está editando
const programmer = ref<Programmer>({} as Programmer);

// Estado de carga
const isLoading = ref(false);

// Mensaje de error
const errorMessage = ref<string | null>(null);

// Obtener el nombre de la referencia basado en el ID
function getReferenceName(productSheetId: number | null): string {
    const productSheet = props.productSheets.find((sheet) => sheet.id === productSheetId);
    return productSheet ? productSheet.reference : 'Referencia';
}

// Método para abrir el diálogo y cargar los datos del programador
function openDialog(selectedProgrammer: Programmer) {
    programmer.value = {
        ...selectedProgrammer,
        ecommerce_units: selectedProgrammer.ecommerce_units || '{}', // Asegurarse de que sea un string válido
    };
    isDialogOpen.value = true; // Abrir el diálogo
    errorMessage.value = null; // Limpiar mensaje de error
}

// Exponer la función `openDialog` para que pueda ser llamada desde el componente padre
defineExpose({
    openDialog,
});

// Validar los campos requeridos
function validateFields(): boolean {
    if (!programmer.value?.product_sheet_id) {
        errorMessage.value = 'La referencia es obligatoria.';
        return false;
    }
    if (!programmer.value?.season) {
        errorMessage.value = 'La temporada es obligatoria.';
        return false;
    }
    if (!programmer.value?.priority) {
        errorMessage.value = 'La prioridad es obligatoria.';
        return false;
    }
    if (!programmer.value?.destination_id) {
        errorMessage.value = 'El destino es obligatorio.';
        return false;
    }
    if (!programmer.value?.units || programmer.value.units <= 0) {
        errorMessage.value = 'Las unidades deben ser un número mayor a 0.';
        return false;
    }
    errorMessage.value = null;
    return true;
}

// Función para guardar los cambios
async function saveChanges() {
    if (!validateFields()) return;

    if (!programmer.value?.id) return;

    isLoading.value = true;
    try {
        // Convertir ecommerce_units a string antes de enviar
        const payload = {
            ...programmer.value,
            ecommerce_units: programmer.value.ecommerce_units, // Ya es un string
        };

        console.log('Payload enviado:', payload);
        await axios.put(`/comercial-diseno/programador/${programmer.value.id}`, payload);
        emits('programmerUpdated'); // Emitir el evento para notificar al componente padre
        isDialogOpen.value = false; // Cerrar el diálogo
    } catch (error) {
        console.error('Error al actualizar el programador:', error.response?.data || error);
        errorMessage.value = 'Hubo un error al actualizar el programador. Por favor, inténtalo de nuevo.';
    } finally {
        isLoading.value = false;
    }
}
</script>

<template>
    <Dialog 
        v-model:open="isDialogOpen" 
        aria-labelledby="dialog-title" 
        aria-describedby="dialog-description"
    >
        <DialogContent>
            <DialogHeader>
                <!-- Título del diálogo -->
                <DialogTitle id="dialog-title" class="sr-only">Editar Programador</DialogTitle>
            </DialogHeader>
            <!-- Descripción del diálogo -->
            <p id="dialog-description" class="text-sm text-gray-500">
                Aquí puedes editar los detalles del programador seleccionado.
            </p>
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
                <!-- Campo de temporada -->
                <div>
                    <SelectField
                        id="season"
                        v-model="programmer.season"
                        :options="[
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
                        ]"
                        label="Temporada"
                        required
                        class="mt-1 w-full"
                        aria-required="true"
                    />
                </div>

                <!-- Campo de prioridad -->
                <div>
                    <SelectField
                        id="priority"
                        v-model="programmer.priority"
                        :options="[
                            { id: 'A', name: 'A' },
                            { id: 'B', name: 'B' },
                            { id: 'C', name: 'C' },
                            { id: 'D', name: 'D' },
                        ]"
                        label="Prioridad"
                        required
                        class="mt-1 w-full"
                        aria-required="true"
                    />
                </div>

                <!-- Campo de destino -->
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

                <!-- Campo de unidades -->
                <div>
                    <InputField 
                        type="number" 
                        v-model="programmer.units" 
                        label="Unidades" 
                        placeholder="Unds. a programar" 
                        :class="{ 'border-red-500': !programmer.units }" 
                    />
                </div>

                <!-- Campo de cantidades de e-commerce -->
                <div>
                    <label class="block text-sm font-medium">E-commerce</label>
                    <EcommerceSizesDialog
                        :productSheet="{
                            sizes: typeof programmer.ecommerce_units === 'string' 
                                ? programmer.ecommerce_units 
                                : JSON.stringify(programmer.ecommerce_units || {}), // Asegurarse de que sea un string
                            reference: getReferenceName(programmer.product_sheet_id)
                        }"
                        @sizesUpdated="(updatedData) => {
                            programmer.ecommerce_units = JSON.stringify(updatedData.sizes || {}); // Guardar como texto
                        }"
                    />
                </div>
            </div>
            <DialogFooter>
                <Button variant="outline" @click="isDialogOpen = false">Cancelar</Button>
                <Button :disabled="isLoading" @click="saveChanges">
                    {{ isLoading ? 'Guardando...' : 'Guardar' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
