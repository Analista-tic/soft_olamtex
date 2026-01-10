<script setup lang="ts">
import EcommerceSizesDialog from './EcommerceSizesDialog.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import type { OptionParams, ProductSheet, Programmer } from '@/domain/product-sheet';
import axios from 'axios';
import { PlusCircle } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import AlertStack from '@/components/common/AlertStack.vue';
import CreateProductSheetDialog from '../ProductSheets/ProductSheetDialog.vue';
import { useAlertStack } from '@/composables/useAlert';

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

// Constants
type AlertType = 'success' | 'error' | 'info' | 'warning';

const MONTHS = [
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
] as const;

const PRIORITIES = [
    { id: 'A', name: 'A' },
    { id: 'B', name: 'B' },
    { id: 'C', name: 'C' },
    { id: 'D', name: 'D' },
] as const;

const ALERT_TITLES: Record<AlertType, string> = {
    warning: 'Advertencia',
    success: 'Éxito',
    info: 'Información',
    error: 'Error',
};

// State
const isDialogOpen = ref(false);
const productSheetDialogOpen = ref(false);
const lotsForm = ref<Array<Partial<Programmer>>>([]);
const formErrors = ref<{ lotIndex: number; fields: string[] }[]>([]);
const dialogInnerRef = ref<HTMLElement | null>(null);

// Alert state
const { successMessage, errorMessage, errorType, showSuccess, showError, clearAlerts, clearSuccess, clearError } = useAlertStack();
const errorTitle = computed(() => ALERT_TITLES[errorType.value]);

// Alert functions
function setAlert(message: string, type: AlertType = 'info'): void {
    if (type === 'success') {
        showSuccess(message);
    } else {
        showError(message, type);
    }
    
    if (dialogInnerRef.value?.scrollTo) {
        dialogInnerRef.value.scrollTo({ top: 0, behavior: 'smooth' });
    } else {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

function clearAlert(): void {
    clearAlerts();
}

// Form state
const form = ref<{
    lotsCount: number | null | undefined;
    productSheetId: number | null; // Asegurar que sea del tipo correcto
}>({
    lotsCount: null,
    productSheetId: null,
});

const lotsCountModel = computed<string | number>({
    get(): string | number {
        return form.value.lotsCount ?? '';
    },
    set(value: string | number): void {
        if (value === '' || value === null || value === undefined) {
            form.value.lotsCount = null;
            return;
        }

        const normalized =
            typeof value === 'number' ? value : Number(value.toString().replace(',', '.'));
        
        // Redondear al entero más cercano y asegurar que sea positivo
        const integerValue = Math.max(0, Math.floor(normalized));
        form.value.lotsCount = Number.isNaN(integerValue) ? null : integerValue;
    },
});

const productSheetOptions = computed(() =>
    productSheets.value.map(sheet => ({ id: sheet.id, name: sheet.reference }))
);

const selectedProductSheet = computed(() => {
    return productSheets.value.find(sheet => sheet.id === form.value.productSheetId) || null;
});

const destinationOptions = computed(() =>
    props.destinations.map(destination => ({ id: destination.id, name: destination.name }))
);

function generateLots(): void {
    // Validar cantidad de lotes
    if (!form.value.lotsCount || form.value.lotsCount <= 0) {
        setAlert('Por favor, ingresa una cantidad válida de lotes.', 'warning');
        lotsForm.value = [];
        return;
    }

    // Validar que exista una ficha de producto seleccionada
    if (!form.value.productSheetId) {
        setAlert('Por favor, selecciona una referencia de ficha de producto.', 'warning');
        lotsForm.value = [];
        return;
    }

    // Limpiar errores previos al regenerar lotes
    formErrors.value = [];
    clearAlert();

    // Generar los lotes con valores iniciales
    lotsForm.value = Array.from({ length: form.value.lotsCount }, () => ({
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

const errorSummary = computed(() => {
    if (formErrors.value.length === 0) return null;
    
    const totalLots = formErrors.value.length;
    const totalErrors = formErrors.value.reduce((sum, lot) => sum + lot.fields.length, 0);
    
    return {
        totalLots,
        totalErrors,
        message: `${totalLots} ${totalLots === 1 ? 'lote tiene' : 'lotes tienen'} campos incompletos (${totalErrors} ${totalErrors === 1 ? 'campo' : 'campos'} en total)`
    };
});

function validateForm(): void {
    formErrors.value = lotsForm.value
        .map((lot, index) => {
            const fields = getLotErrors(lot);
            return fields.length > 0 ? { lotIndex: index, fields } : null;
        })
        .filter((error): error is { lotIndex: number; fields: string[] } => Boolean(error));
}

function createProgrammer(): void {
    // Validar que existan lotes
    if (lotsForm.value.length === 0) {
        setAlert('Debes generar al menos un lote antes de guardar.', 'warning');
        return;
    }

    // Validar todos los campos
    validateForm();

    if (formErrors.value.length > 0) {
        setAlert(`Por favor, completa todos los campos requeridos. ${errorSummary.value?.message}`, 'warning');
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
            resetForm();
            isDialogOpen.value = false;
        })
        .catch((error: unknown) => {
            console.error('Error al crear los programadores:', error);
            
            let errorText = 'Hubo un error al crear los programadores.';
            if (axios.isAxiosError(error) && error.response?.data?.message) {
                errorText = error.response.data.message;
            }
            
            setAlert(errorText, 'error');
        });
}

function resetForm(): void {
    form.value = {
        lotsCount: null,
        productSheetId: null,
    };
    lotsForm.value = [];
    formErrors.value = [];
    productSheetDialogOpen.value = false;
    dialogInnerRef.value = null;
    clearAlert();
}

function closeDialog(): void {
    resetForm();
    isDialogOpen.value = false;
}

const allProductSheets = ref<ProductSheet[]>([...props.productSheets]);

const productSheets = computed(() => allProductSheets.value);

watch(
    () => form.value.productSheetId,
    (newProductSheetId) => {
        if (!newProductSheetId) {
            lotsForm.value = [];
            formErrors.value = [];
            return;
        }
        if (lotsForm.value.length > 0) {
            lotsForm.value = lotsForm.value.map((lot) => ({
                ...lot,
                product_sheet_id: newProductSheetId,
            }));
        }
    },
);

function getLotErrors(lot: Partial<Programmer>): string[] {
    const lotErrors: string[] = [];
    if (!lot.product_sheet_id) lotErrors.push('Referencia');
    if (!lot.season) lotErrors.push('Temporada');
    if (!lot.destination_id) lotErrors.push('Destino');
    if (!lot.priority) lotErrors.push('Prioridad');
    if (!lot.units || lot.units <= 0) lotErrors.push('Unidades');
    return lotErrors;
}

// Validación en tiempo real al cambiar valores de los lotes
function validateLotField(lotIndex: number): void {
    const lot = lotsForm.value[lotIndex];
    if (!lot) return;

    const existingErrorIndex = formErrors.value.findIndex(e => e.lotIndex === lotIndex);
    const lotErrors = getLotErrors(lot);

    if (lotErrors.length > 0) {
        if (existingErrorIndex >= 0) {
            formErrors.value[existingErrorIndex].fields = lotErrors;
        } else {
            formErrors.value.push({ lotIndex, fields: lotErrors });
        }
    } else if (existingErrorIndex >= 0) {
        formErrors.value.splice(existingErrorIndex, 1);
    }
}

function addProductSheet(response: { success: boolean; message: string; data: ProductSheet } | undefined): void {
    if (!response?.success || !response?.data?.id) {
        console.error('El ProductSheet creado no es válido:', response);
        setAlert('Hubo un problema al crear la ficha de producto. Intenta nuevamente.', 'error');
        return;
    }

    const newProductSheet = response.data;
    allProductSheets.value = [...allProductSheets.value, newProductSheet];
    form.value.productSheetId = newProductSheet.id;
}

// Función para obtener las claves con valores `true` en `sizes`
function getTrueSizes(sizes: string | Record<string, boolean> | undefined | null): string {
    if (!sizes) return '';
    
    try {
        // Si sizes ya es un objeto, usarlo directamente
        const parsedSizes: Record<string, boolean> = typeof sizes === 'string' ? JSON.parse(sizes) : sizes;
        return Object.entries(parsedSizes)
            .filter(([, value]) => value === true)
            .map(([key]) => key)
            .join(', ');
    } catch (error) {
        console.error('Error al parsear sizes:', error);
        return '';
    }
}

// Función para filtrar solo números enteros en el campo de lotes
function handleLotsCountInput(event: Event): void {
    const input = event.target as HTMLInputElement;
    // Eliminar cualquier carácter que no sea un dígito
    input.value = input.value.replace(/[^0-9]/g, '');
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
        <DialogContent class="sm:max-w-[90vw] h-[90vh] flex flex-col p-0 gap-0">
            <DialogHeader class="flex-shrink-0 px-6 pt-6 pb-4">
                <DialogTitle>Crear Nuevo Programador</DialogTitle>
                <DialogDescription> Completa los campos para crear un programador. Haz clic en guardar cuando termines. </DialogDescription>
            </DialogHeader>
            <AlertStack
                :success-message="successMessage"
                :error-message="errorMessage"
                :error-type="errorType"
                :success-title="ALERT_TITLES.success"
                :error-title="errorTitle"
                @close-success="clearSuccess"
                @close-error="clearError"
            />

            <div ref="dialogInnerRef" class="overflow-y-auto flex-1 px-6 pb-24 scroll-smooth" style="scrollbar-width: thin;">
                <div class="grid grid-cols-1 gap-4 pt-4">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]">
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
                            min="1"
                            step="1"
                            required
                            @input="handleLotsCountInput"
                            @update:modelValue="generateLots"
                        />
                        <div class="flex flex-col gap-2">
                            <span class="text-sm font-medium leading-tight text-foreground">Accion</span>
                            <Button
                                variant="outline"
                                class="h-10 rounded-full px-4 text-sm font-semibold"
                                type="button"
                                @click="productSheetDialogOpen = true"
                            >
                                Crear Sabana de Productos
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
                    <div v-if="selectedProductSheet" class="mb-4">
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
                
                <div v-if="formErrors.length > 0" class="bg-amber-50 border-l-4 border-amber-400 p-4 rounded mb-4 mt-4">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-amber-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-amber-800">Campos incompletos</h3>
                            <div class="mt-2 text-sm text-amber-700">
                                <p>{{ errorSummary?.message }}</p>
                                <details class="mt-2">
                                    <summary class="cursor-pointer font-medium hover:text-amber-900">Ver detalles</summary>
                                    <ul class="mt-2 space-y-1 list-disc list-inside">
                                        <li v-for="(error, index) in formErrors" :key="index">
                                            Lote {{ error.lotIndex + 1 }}: {{ error.fields.join(', ') }}
                                        </li>
                                    </ul>
                                </details>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="lotsForm.length > 0" class="mt-4">
                    <h3 class="text-base font-bold mb-2">Lotes Generados:</h3>
                    <table class="w-full text-sm border border-gray-300 rounded-lg overflow-visible shadow-sm text-center">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-4 py-3 border font-semibold">Lote</th>
                            <th class="px-4 py-3 border font-semibold">Referencia</th>
                            <th class="px-4 py-3 border font-semibold">Temporada</th>
                            <th class="px-4 py-3 border font-semibold">Destino</th>
                            <th class="px-4 py-3 border font-semibold">Prioridad</th>
                            <th class="px-4 py-3 border font-semibold">E-commerce?</th>
                            <th class="px-4 py-3 border font-semibold">Cant. Almacenes</th>
                            <th v-if="lotsForm.some(lot => lot.isEcommerce)" class="px-4 py-3 border font-semibold">Cant. E-commerce</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(lot, index) in lotsForm" :key="index" class="hover:bg-gray-50">
                            <td class="px-4 py-4 border whitespace-nowrap">MF300{{ index + 1 }}</td>
                            <td class="px-4 py-4 border">{{ lot.product_sheet_id }}</td>
                            <td class="px-4 py-4 border" style="min-width: 180px;">
                                <SelectField
                                    v-model="lot.season"
                                    :options="MONTHS"
                                    label=""
                                    :aria-label="`Temporada para lote ${index + 1}`"
                                    :class="{ 'border-red-500': !lot.season }"
                                    @update:modelValue="validateLotField(index)"
                                />
                            </td>
                            <td class="px-4 py-4 border" style="min-width: 200px;">
                                <SelectField
                                    v-model="lot.destination_id"
                                    :options="destinationOptions"
                                    label=""
                                    :aria-label="`Destino para lote ${index + 1}`"
                                    :class="{ 'border-red-500': !lot.destination_id }"
                                    @update:modelValue="validateLotField(index)"
                                />
                            </td>
                            <td class="px-4 py-4 border" style="min-width: 150px;">
                                <SelectField
                                    v-model="lot.priority"
                                    :options="PRIORITIES"
                                    label=""
                                    :aria-label="`Prioridad para lote ${index + 1}`"
                                    :class="{ 'border-red-500': !lot.priority }"
                                    @update:modelValue="validateLotField(index)"
                                />
                            </td>
                            <td class="px-4 py-4 border text-center">
                                <input 
                                    type="checkbox" 
                                    v-model="lot.isEcommerce" 
                                    :aria-label="`Habilitar e-commerce para lote ${index + 1}`"
                                    class="form-checkbox h-5 w-5 text-blue-600 cursor-pointer"
                                />
                            </td>
                            <td class="px-4 py-4 border" style="min-width: 180px;">
                                <InputField
                                    v-model="lot.units as number | undefined"
                                    label=""
                                    placeholder="Unds. a programar"
                                    type="number"
                                    min="1"
                                    :aria-label="`Unidades para lote ${index + 1}`"
                                    :class="{ 'border-red-500': !lot.units || lot.units <= 0 }"
                                    @update:modelValue="validateLotField(index)"
                                />
                            </td>
                            <td v-if="lot.isEcommerce" class="px-4 py-4 border text-center">
                                <EcommerceSizesDialog
                                    :productSheet="{ sizes: selectedProductSheet?.sizes || '{}', reference: selectedProductSheet?.reference || '' }"
                                    @sizesUpdated="(sizes) => lot.ecommerce_units = sizes"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            </div>

            <!-- Botones flotantes en la esquina inferior derecha -->
            <div class="absolute bottom-0 right-0 left-0 bg-white border-t shadow-lg px-6 py-4 flex items-center justify-end gap-3 rounded-b-[24px]">
                <button
                    type="button"
                    class="flex items-center justify-center gap-2 rounded-full border border-primary/40 bg-white px-6 py-2.5 text-sm font-semibold text-primary transition hover:bg-primary/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary"
                    @click="closeDialog"
                >
                    Cancelar
                </button>
                <Button
                    type="button"
                    class="flex items-center justify-center rounded-full px-6 py-2.5 text-sm font-semibold shadow-sm"
                    :disabled="formErrors.length > 0"
                    @click="createProgrammer"
                >
                    Guardar
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
