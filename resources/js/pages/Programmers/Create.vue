<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import Label from '@/components/ui/label/Label.vue';
import { TooltipProvider } from '@/components/ui/tooltip';
import { useAlertStack } from '@/composables/useAlert';
import { normalizeSizes, sizesToLabel, type ProductSheet, type Programmer } from '@/domain/product-sheet';
import type { Option } from '@/domain/shared/types';
import axios from 'axios';
import { PlusCircle } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import CreateProductSheetDialog from '../ProductSheets/ProductSheetDialog.vue';
import EcommerceSizesDialog from './EcommerceSizesDialog.vue';

const emit = defineEmits(['programmerCreated']);

const props = withDefaults(defineProps<{
    productSheets?: ProductSheet[];
    destinations?: Option[];
    sections?: Option[];
    groups?: Option[];
    brands?: Option[];
    worlds?: Option[];
    fabricBases?: Option[];
    yarnBases?: Option[];
    months?: Option[];
    priorities?: Option[];
}>(), {
    productSheets: () => [],
    destinations: () => [],
    sections: () => [],
    groups: () => [],
    brands: () => [],
    worlds: () => [],
    fabricBases: () => [],
    yarnBases: () => [],
    months: () => [],
    priorities: () => [],
});

// Constants
type AlertType = 'success' | 'error' | 'info' | 'warning';

const MONTHS: Option[] = [
    { id: 1, name: 'ENERO' },
    { id: 2, name: 'FEBRERO' },
    { id: 3, name: 'MARZO' },
    { id: 4, name: 'ABRIL' },
    { id: 5, name: 'MAYO' },
    { id: 6, name: 'JUNIO' },
    { id: 7, name: 'JULIO' },
    { id: 8, name: 'AGOSTO' },
    { id: 9, name: 'SEPTIEMBRE' },
    { id: 10, name: 'OCTUBRE' },
    { id: 11, name: 'NOVIEMBRE' },
    { id: 12, name: 'DICIEMBRE' },
];

const PRIORITIES: Option[] = [
    { id: 'A', name: 'A' },
    { id: 'B', name: 'B' },
    { id: 'C', name: 'C' },
    { id: 'D', name: 'D' },
];

const ALERT_TITLES: Record<AlertType, string> = {
    warning: 'Advertencia',
    success: 'Éxito',
    info: 'Información',
    error: 'Error',
};

const monthOptions = computed<Option[]>(() => {
    return Array.isArray(props.months) && props.months.length > 0 ? props.months : MONTHS;
});

const priorityOptions = computed<Option[]>(() => {
    return Array.isArray(props.priorities) && props.priorities.length > 0 ? props.priorities : PRIORITIES;
});

/**
 * Normaliza un valor de option a su name string.
 * Busca primero por id, luego verifica si ya es el name.
 */
function normalizeOptionToName(
    value: string | number | null | undefined,
    options: Option[]
): string | null {
    if (value === null || value === undefined || value === '') return null;

    // Buscar por id
    const byId = options.find((opt) => opt.id === value || String(opt.id) === String(value));
    if (byId) return String(byId.name).toUpperCase();

    // Si el value ya es un name válido
    const byName = options.find((opt) =>
        String(opt.name).toUpperCase() === String(value).toUpperCase()
    );
    if (byName) return String(byName.name).toUpperCase();

    // Fallback: devolver el valor como string en mayúscula si no se encuentra
    return String(value).toUpperCase();
}

function normalizeSeason(value: string | number | null | undefined): string | null {
    return normalizeOptionToName(value, monthOptions.value);
}

function normalizePriority(value: string | number | null | undefined): string | null {
    return normalizeOptionToName(value, priorityOptions.value);
}

// State
const isDialogOpen = ref(false);
const productSheetDialogOpen = ref(false);
type ProgrammerLotForm = Partial<Programmer> & { unitsInput?: string };
const lotsForm = ref<Array<ProgrammerLotForm>>([]);
const formErrors = ref<{ lotIndex: number; fields: string[] }[]>([]);
const dialogInnerRef = ref<HTMLElement | null>(null);
const saveDisabled = computed(() => formErrors.value.length > 0);
const missingFieldsSummary = computed(() => {
    if (formErrors.value.length === 0) return '';
    return formErrors.value.map((error) => `Lote ${error.lotIndex + 1}: ${error.fields.join(', ')}`).join(' • ');
});
const isSubmitting = ref(false);

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

        const normalized = typeof value === 'number' ? value : Number(value.toString().replace(',', '.'));

        // Redondear al entero más cercano y asegurar que sea positivo
        const integerValue = Math.max(0, Math.floor(normalized));
        form.value.lotsCount = Number.isNaN(integerValue) ? null : integerValue;
    },
});

function getProductSheetValue(sheet: ProductSheet): number {
    const candidate = (sheet as ProductSheet & { value?: number | string }).value ?? sheet.id;
    return Number(candidate);
}

function getProductSheetLabel(sheet: ProductSheet): string {
    const typedSheet = sheet as ProductSheet & { label?: string; display_reference?: string };
    return String(typedSheet.label ?? typedSheet.display_reference ?? sheet.reference ?? '').trim();
}

function getProductReference(sheet: ProductSheet): string {
    return String(sheet.product_reference ?? '').trim();
}

function hasProductReference(sheet: ProductSheet): boolean {
    return getProductReference(sheet).length > 0;
}

const productSheetOptions = computed(() =>
    productSheetList.value
        .map((sheet) => ({
            id: getProductSheetValue(sheet),
            name: getProductReference(sheet),
        }))
        .filter((option) => option.name !== ''),
);

const selectedProductSheet = computed<ProductSheet | null>(() => {
    if (!form.value.productSheetId) return null;
    return productSheetList.value.find((sheet) => getProductSheetValue(sheet) === Number(form.value.productSheetId)) || null;
});

// Mapa de productSheets por ID para búsqueda rápida
const productSheetsById = computed(() => {
    const map = new Map<number, ProductSheet>();
    for (const sheet of productSheetList.value) {
        map.set(getProductSheetValue(sheet), sheet);
    }
    return map;
});

// Helper para obtener la referencia humana (sample_reference o product_reference) dado un ID
function getDisplayReference(productSheetId: number | null | undefined): string {
    if (!productSheetId) return 'Sin referencia';
    const sheet = productSheetsById.value.get(Number(productSheetId));
    if (!sheet) return 'Sin referencia';

    // Si es muestra, usar sample_reference; si es producto, usar product_reference
    // Fallback al label o display_reference existente
    if (sheet.type === 'muestra' && sheet.sample_reference) {
        return sheet.sample_reference;
    }
    if (sheet.type === 'producto' && sheet.product_reference) {
        return sheet.product_reference;
    }
    // Fallback a cualquier referencia disponible
    return sheet.sample_reference || sheet.product_reference || sheet.display_reference || sheet.reference || getProductSheetLabel(sheet) || 'Sin referencia';
}

const destinationOptions = computed<Option[]>(() => props.destinations);

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
        units: null,
        unitsInput: '',
    }));
}

const errorSummary = computed(() => {
    if (formErrors.value.length === 0) return null;

    const totalLots = formErrors.value.length;
    const totalErrors = formErrors.value.reduce((sum, lot) => sum + lot.fields.length, 0);

    return {
        totalLots,
        totalErrors,
        message: `${totalLots} ${totalLots === 1 ? 'lote tiene' : 'lotes tienen'} campos incompletos (${totalErrors} ${totalErrors === 1 ? 'campo' : 'campos'} en total)`,
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
    if (isSubmitting.value) {
        return;
    }

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

    const lotsToSend = lotsForm.value.map((lot) => {
        // Determinar el valor de ecommerce_units
        let ecommerceUnitsValue = null;

        if (lot.isEcommerce && lot.ecommerce_units) {
            // Solo enviar si hay tallas configuradas
            const hasValidSizes = Object.keys(lot.ecommerce_units).length > 0;
            ecommerceUnitsValue = hasValidSizes ? lot.ecommerce_units : null;
        }

        // Asegurar que units sea un número válido
        const unitsValue = lot.units ? Number(lot.units) : null;

        return {
            product_sheet_id: lot.product_sheet_id,
            season: normalizeSeason(lot.season),
            destination_id: lot.destination_id || null,
            priority: normalizePriority(lot.priority),
            units: unitsValue,
            ecommerce_units: ecommerceUnitsValue,
            status: lot.status,
        };
    });

    const requestId =
        typeof globalThis.crypto?.randomUUID === 'function'
            ? globalThis.crypto.randomUUID()
            : `${Date.now()}-${Math.random().toString(16).slice(2)}`;

    isSubmitting.value = true;

    axios
        .post('/comercial/programador', { lots: lotsToSend }, { headers: { 'X-Request-Id': requestId } })
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
        })
        .finally(() => {
            isSubmitting.value = false;
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

function parseUnitsInput(value: string | number | null | undefined): number | null {
    if (value === null || value === undefined) return null;
    const raw = typeof value === 'string' ? value.trim() : String(value);
    if (raw === '') return null;
    const numeric = Number(raw.replace(/[^0-9.]/g, ''));
    if (!Number.isFinite(numeric)) return null;
    const positive = Math.max(1, Math.floor(numeric));
    return Number.isFinite(positive) ? positive : null;
}

function closeDialog(): void {
    resetForm();
    isDialogOpen.value = false;
}

const allProductSheets = ref<ProductSheet[]>(
    Array.isArray(props.productSheets) ? [...props.productSheets] : [],
);

const productSheetList = computed(() => allProductSheets.value.filter(hasProductReference));

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

    // Validación robusta de unidades
    const units = lot.units;
    const isValidUnits = units !== null && units !== undefined && !isNaN(Number(units)) && Number(units) > 0;
    if (!isValidUnits) lotErrors.push('Unidades');

    return lotErrors;
}

// Validación en tiempo real al cambiar valores de los lotes
function validateLotField(lotIndex: number): void {
    const lot = lotsForm.value[lotIndex];
    if (!lot) return;

    const existingErrorIndex = formErrors.value.findIndex((e) => e.lotIndex === lotIndex);
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
        return value as Record<string, number>;
    }

    return null;
}

/** Payload recibido del ProductSheetDialog al guardar */
interface ProductSheetSavedPayload {
    success?: boolean;
    message?: string;
    data?: ProductSheet;
}

function addProductSheet(response: ProductSheetSavedPayload): void {
    if (!response?.success || !response?.data?.id) {
        console.error('El ProductSheet creado no es válido:', response);
        setAlert('Hubo un problema al crear la ficha de producto. Intenta nuevamente.', 'error');
        return;
    }

    const newProductSheet = response.data;

    if (!hasProductReference(newProductSheet)) {
        setAlert('La sábana fue creada sin referencia de producto. No aparece en este selector.', 'info');
        return;
    }

    allProductSheets.value = [...allProductSheets.value, newProductSheet];
    form.value.productSheetId = newProductSheet.id;
}

function getDialogProductSheet(sheet: ProductSheet | null): { sizes: string | Record<string, boolean> | null; reference: string } {
    return {
        sizes: normalizeSizes(sheet?.sizes),
        reference: sheet ? getProductSheetLabel(sheet) : '',
    };
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
            <Button variant="default" size="sm" class="flex h-10 items-center gap-2 rounded-full px-5 py-2 text-sm font-semibold shadow-sm">
                <PlusCircle class="h-4 w-4" />
                Nuevo Programador
            </Button>
        </DialogTrigger>
        <FormDialogContent class="flex max-h-[200vh] max-w-[200vh] flex-col px-2 py-2">
            <DialogHeader class="flex-shrink-0 px-6 pb-4 pt-6">
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

            <TooltipProvider>
                <div ref="dialogInnerRef" class="flex-1 overflow-y-auto scroll-smooth px-6 pb-24" style="scrollbar-width: thin">
                    <div class="grid grid-cols-1 gap-4 pt-4">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]">
                            <!-- Campo para la referencia seleccionada -->
                            <div class="flex flex-col gap-1">
                                <div class="flex items-center gap-2">
                                    <Label class="text-sm font-medium"> Referencia Seleccionada<span class="text-red-500">*</span> </Label>
                                    <HelpTooltip
                                        text="Selecciona la sábana de producto que deseas programar para producción."
                                        aria-label="Ayuda sobre referencia"
                                    />
                                </div>
                                <SelectField v-model="form.productSheetId" label="" :options="productSheetOptions" required />
                            </div>
                            <!-- Campo para la cantidad de lotes -->
                            <div class="flex flex-col gap-1">
                                <div class="flex items-center gap-2">
                                    <Label class="text-sm font-medium"> Cantidad de Lotes<span class="text-red-500">*</span> </Label>
                                    <HelpTooltip
                                        text="Número de lotes de producción que deseas crear para esta referencia."
                                        aria-label="Ayuda sobre cantidad de lotes"
                                    />
                                </div>
                                <InputField
                                    v-model="lotsCountModel"
                                    label=""
                                    placeholder="Numero de lotes"
                                    type="number"
                                    min="1"
                                    step="1"
                                    required
                                    @input="handleLotsCountInput"
                                    @update:modelValue="generateLots"
                                />
                            </div>
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
                            <h3 class="mb-2 text-lg font-bold">Detalles de la Ficha de Producto</h3>
                            <table class="w-full overflow-hidden rounded-lg border border-gray-300 text-left text-sm">
                                <tbody>
                                    <tr>
                                        <td class="px-4 py-2 font-bold">Referencia:</td>
                                        <td class="border border-l-0 px-4 py-2">
                                            {{ getProductSheetLabel(selectedProductSheet) || 'Sin referencia' }}
                                        </td>
                                        <td class="px-4 py-2 font-bold">Sección:</td>
                                        <td class="border border-l-0 px-4 py-2">{{ selectedProductSheet.section?.name || 'Sin sección' }}</td>
                                        <td class="px-4 py-2 font-bold">Grupo:</td>
                                        <td class="border border-l-0 px-4 py-2">{{ selectedProductSheet.group?.name || 'Sin grupo' }}</td>
                                        <td class="px-4 py-2 font-bold">Marca:</td>
                                        <td class="border border-l-0 px-4 py-2">{{ selectedProductSheet.brand?.name || 'Sin marca' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="border border-r-0 px-4 py-1 font-bold">Mundo:</td>
                                        <td class="border border-l-0 px-4 py-1">{{ selectedProductSheet.world?.name || 'Sin mundo' }}</td>
                                        <td class="border border-r-0 px-4 py-1 font-bold">Base:</td>
                                        <td class="border border-l-0 px-4 py-1">{{ selectedProductSheet.fabric_base?.name || 'Sin base' }}</td>
                                        <td class="border border-r-0 px-4 py-1 font-bold">Precio Facturación:</td>
                                        <td class="border border-l-0 px-4 py-1">{{ selectedProductSheet.billing_price ?? 'N/A' }}</td>
                                        <td class="border border-r-0 px-4 py-1 font-bold">Precio Venta:</td>
                                        <td class="border border-l-0 px-4 py-1">{{ selectedProductSheet.selling_price ?? 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="border px-4 py-5 font-bold">Tallas:</td>
                                        <td class="border px-8 py-5" colspan="3">{{ getTrueSizes(selectedProductSheet.sizes) || 'Sin tallas' }}</td>
                                        <td class="border px-4 py-5 font-bold">Descripción:</td>
                                        <td class="border px-4 py-5" colspan="3">{{ selectedProductSheet.description || 'Sin descripción' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div v-if="hasAttemptedSave && formErrors.length > 0" class="mb-4 mt-4 rounded border-l-4 border-amber-400 bg-amber-50 p-4">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-amber-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path
                                        fill-rule="evenodd"
                                        d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-amber-800">Campos incompletos</h3>
                                <div class="mt-2 text-sm text-amber-700">
                                    <p>{{ errorSummary?.message }}</p>
                                    <details class="mt-2">
                                        <summary class="cursor-pointer font-medium hover:text-amber-900">Ver detalles</summary>
                                        <ul class="mt-2 list-inside list-disc space-y-1">
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
                        <h3 class="mb-2 text-base font-bold">Lotes Generados:</h3>
                        <table class="w-full overflow-visible rounded-lg border border-gray-300 text-center text-sm shadow-sm">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="border px-4 py-3 font-semibold">Lote</th>
                                    <th class="border px-4 py-3 font-semibold">Referencia</th>
                                    <th class="border px-4 py-3 font-semibold">Temporada</th>
                                    <th class="border px-4 py-3 font-semibold">Destino</th>
                                    <th class="border px-4 py-3 font-semibold">Prioridad</th>
                                    <th class="border px-4 py-3 font-semibold">E-commerce (opcional)</th>
                                    <th class="border px-4 py-3 font-semibold">Unidades</th>
                                    <th v-if="lotsForm.some((lot) => lot.isEcommerce)" class="border px-4 py-3 font-semibold">Cant. E-commerce</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(lot, index) in lotsForm" :key="index" class="hover:bg-gray-50">
                                    <td class="whitespace-nowrap border px-4 py-4">MF300{{ index + 1 }}</td>
                                    <td class="border px-4 py-4">{{ lot.product_sheet_id }}</td>
                                    <td class="border px-4 py-4" style="min-width: 180px">
                                        <SelectField
                                            v-model="lot.season"
                                            :options="monthOptions"
                                            label=""
                                            :aria-label="`Temporada para lote ${index + 1}`"
                                            :class="{ 'border-red-500': !lot.season }"
                                            @update:modelValue="validateLotField(index)"
                                        />
                                    </td>
                                    <td class="border px-4 py-4" style="min-width: 200px">
                                        <SelectField
                                            v-model="lot.destination_id"
                                            :options="destinationOptions"
                                            label=""
                                            :aria-label="`Destino para lote ${index + 1}`"
                                            :class="{ 'border-red-500': !lot.destination_id }"
                                            @update:modelValue="validateLotField(index)"
                                        />
                                    </td>
                                    <td class="border px-4 py-4" style="min-width: 150px">
                                        <SelectField
                                            v-model="lot.priority"
                                            :options="priorityOptions"
                                            label=""
                                            :aria-label="`Prioridad para lote ${index + 1}`"
                                            :class="{ 'border-red-500': !lot.priority }"
                                            @update:modelValue="validateLotField(index)"
                                        />
                                    </td>
                                    <td class="border px-4 py-4 text-center">
                                        <input
                                            type="checkbox"
                                            v-model="lot.isEcommerce"
                                            :aria-label="`Habilitar e-commerce para lote ${index + 1}`"
                                            class="form-checkbox h-5 w-5 cursor-pointer text-blue-600"
                                            @change="
                                                () => {
                                                    // Si se desmarca e-commerce, limpiar los datos
                                                    if (!lot.isEcommerce) {
                                                        lot.ecommerce_units = null;
                                                    }
                                                }
                                            "
                                        />
                                    </td>
                                    <td class="border px-4 py-4" style="min-width: 180px">
                                        <InputField
                                            :model-value="lot.unitsInput ?? ''"
                                            inputmode="numeric"
                                            @update:modelValue="
                                                (value) => {
                                                    const normalizedRaw = typeof value === 'string' ? value : String(value);
                                                    lot.unitsInput = normalizedRaw;
                                                    lot.units = parseUnitsInput(normalizedRaw);
                                                    validateLotField(index);
                                                }
                                            "
                                            label=""
                                            placeholder="Unidades a programar"
                                            type="number"
                                            min="1"
                                            step="1"
                                            :aria-label="`Unidades para lote ${index + 1}`"
                                            :class="{ 'border-red-500': !lot.units || lot.units <= 0 }"
                                        />
                                    </td>
                                    <td v-if="lot.isEcommerce" class="border px-4 py-4 text-center">
                                        <EcommerceSizesDialog
                                            :productSheet="getDialogProductSheet(selectedProductSheet)"
                                            :existingValues="resolveEcommerceValues(lot.ecommerce_units)"
                                            @sizesUpdated="
                                                (data) => {
                                                    if (data?.sizes && Object.keys(data.sizes).length > 0) {
                                                        const sanitizedSizes = Object.fromEntries(
                                                            Object.entries(data.sizes).map(([size, value]) => [
                                                                size,
                                                                typeof value === 'number' ? Math.max(0, value) : 0,
                                                            ]),
                                                        );
                                                        lot.ecommerce_units = sanitizedSizes;
                                                    } else {
                                                        lot.ecommerce_units = {};
                                                    }
                                                }
                                            "
                                        />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </TooltipProvider>

            <!-- Botones flotantes en la esquina inferior derecha -->
            <div class="absolute bottom-0 left-0 right-0 flex flex-col items-end gap-2 rounded-b-[24px] border-t bg-white px-6 py-4 shadow-lg">
                <div class="flex w-full max-w-lg items-center justify-end gap-3">
                    <button
                        type="button"
                        class="flex items-center justify-center gap-2 rounded-full border border-primary/40 bg-white px-6 py-2.5 text-sm font-semibold text-primary transition hover:bg-primary/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                        @click="closeDialog"
                    >
                        Cancelar
                    </button>
                    <Button
                        type="button"
                        class="flex items-center justify-center rounded-full px-6 py-2.5 text-sm font-semibold shadow-sm"
                        :disabled="saveDisabled || isSubmitting"
                        @click="createProgrammer"
                    >
                        {{ isSubmitting ? 'Guardando...' : 'Guardar' }}
                    </Button>
                </div>
                <p v-if="saveDisabled" class="text-right text-xs text-rose-600">Campos incompletos: {{ missingFieldsSummary }}</p>
            </div>
        </FormDialogContent>
    </Dialog>
</template>
