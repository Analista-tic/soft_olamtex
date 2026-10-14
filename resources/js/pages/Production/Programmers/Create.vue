<script setup lang="ts">
import axios from 'axios';
import { PlusCircle } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

import EcommerceSizesDialog from './EcommerceSizesDialog.vue';

import AlertStack from '@/components/common/AlertStack.vue';
import DatePickerField from '@/components/DatePickerField.vue';
import FormLoadingOverlay from '@/components/FormLoadingOverlay.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import Label from '@/components/ui/label/Label.vue';
import { TooltipProvider } from '@/components/ui/tooltip';

import FormDialogContent from '@/components/FormDialogContent.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';

import type { ProductSheet, Programmer } from '@/domain/Production';
import { calculateEstimatedDeliveryDateByPriority } from '@/domain/Production';
import { normalizeSizes, sizesToLabel } from '@/domain/Production';
import type { Option } from '@/domain/shared/types';

import { useAlertStack } from '@/composables/useAlert';

const emit = defineEmits<{
    (e: 'programmerCreated', message?: string): void;
}>();

const props = withDefaults(
    defineProps<{
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
    }>(),
    {
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
    },
);

type AlertType = 'success' | 'error' | 'info' | 'warning';

/**
 * Formulario de lote de programación.
 * Solo incluye campos usados en la creación, sin extender Programmer completo.
 */
type ProgrammerLotForm = {
    product_sheet_id: number | null;
    season: string | null;
    dispatch_month: string | null;
    destination_id: number | null;
    priority: string | null;
    estimated_delivery_date: string | null;
    notes: string;
    units: number | null;
    ecommerce_units: Record<string, number> | string | null;
    status: 'complete' | 'incomplete';
    isEcommerce: boolean;
    /** Input original para no "pelear" con el usuario mientras tipea */
    unitsInput?: string;
};

const MONTHS_FALLBACK: Option[] = [
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

const PRIORITIES_FALLBACK: Option[] = [
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

const baseMonthOptions = computed<Option[]>(() => (Array.isArray(props.months) && props.months.length > 0 ? props.months : MONTHS_FALLBACK));

const monthOptions = computed<Option[]>(() => {
    const currentYear = new Date().getFullYear();
    const years = [currentYear, currentYear + 1];

    return years.flatMap((year) =>
        baseMonthOptions.value.map((month) => {
            const monthName = String(month.name).toUpperCase();
            return {
                id: `${monthName} ${year}`,
                name: `${monthName} ${year}`,
            } as Option;
        }),
    );
});

const priorityOptions = computed<Option[]>(() =>
    Array.isArray(props.priorities) && props.priorities.length > 0 ? props.priorities : PRIORITIES_FALLBACK,
);

const destinationOptions = computed<Option[]>(() =>
    [...(props.destinations ?? [])].sort((a, b) => String(a.name).localeCompare(String(b.name), 'es', { sensitivity: 'base' })),
);

/** Normaliza un option “value” a su name (string) */
function normalizeOptionToName(value: string | number | null | undefined, options: Option[]): string | null {
    if (value === null || value === undefined || value === '') return null;

    const byId = options.find((opt) => opt.id === value || String(opt.id) === String(value));
    if (byId) return String(byId.name).toUpperCase();

    const byName = options.find((opt) => String(opt.name).toUpperCase() === String(value).toUpperCase());
    if (byName) return String(byName.name).toUpperCase();

    return String(value).toUpperCase();
}

const normalizeSeason = (value: string | number | null | undefined) => normalizeOptionToName(value, monthOptions.value);

const normalizePriority = (value: string | number | null | undefined) => normalizeOptionToName(value, priorityOptions.value);

const { successMessage, errorMessage, errorType, showSuccess, showError, clearAlerts, clearSuccess, clearError } = useAlertStack();

const errorTitle = computed(() => ALERT_TITLES[errorType.value]);

function scrollToTop() {
    if (dialogInnerRef.value?.scrollTo) {
        dialogInnerRef.value.scrollTo({ top: 0, behavior: 'smooth' });
        return;
    }
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function setAlert(message: string, type: AlertType = 'info') {
    if (type === 'success') showSuccess(message);
    else showError(message, type);

    scrollToTop();
}

function clearAlert() {
    clearAlerts();
}

const isDialogOpen = ref(false);
const productSheetDialogOpen = ref(false);

const form = ref<{ lotsCount: number | null; productSheetId: number | null }>({
    lotsCount: null,
    productSheetId: null,
});

const lotsForm = ref<ProgrammerLotForm[]>([]);
const nextOpCodes = ref<string[]>([]);
const formErrors = ref<Array<{ lotIndex: number; fields: string[] }>>([]); 
const dialogInnerRef = ref<HTMLElement | null>(null);

const isSubmitting = ref(false);
const isFormInitializing = ref(false);
const saveDisabled = computed(() => formErrors.value.length > 0);
const canRenderForm = computed(() => !isFormInitializing.value);

const allProductSheets = ref<ProductSheet[]>(Array.isArray(props.productSheets) ? [...props.productSheets] : []);

function getProductSheetValue(sheet: ProductSheet): number {
    const candidate = (sheet as ProductSheet & { value?: number | string }).value ?? sheet.id;
    return Number(candidate);
}

function getProductSheetLabel(sheet: ProductSheet | null | undefined): string {
    if (!sheet) return '';
    return getProductSheetStructuredLabel(sheet);
}

function getSampleReference(sheet: ProductSheet): string {
    return String(sheet.sample_reference ?? '')
        .trim()
        .toLocaleUpperCase('es-CO');
}

function getProductReference(sheet: ProductSheet): string {
    return String(sheet.product_reference ?? '')
        .trim()
        .toLocaleUpperCase('es-CO');
}

function getProductReference2(sheet: ProductSheet): string {
    return String(sheet.product_reference_2 ?? '')
        .trim()
        .toLocaleUpperCase('es-CO');
}

function hasAnyMainReference(sheet: ProductSheet): boolean {
    return getSampleReference(sheet).length > 0 || getProductReference(sheet).length > 0;
}

function buildProductSheetSelectLabel(sheet: ProductSheet): string {
    const sample = getSampleReference(sheet);
    const product = getProductReference(sheet);
    const prod2 = getProductReference2(sheet);
    const rawVersion = (sheet as ProductSheet & { version?: number | string | null }).version;
    const hasVersion = rawVersion !== null && rawVersion !== undefined && String(rawVersion).trim() !== '';

    const labelParts: string[] = [];
    if (sample) labelParts.push(sample);
    if (product) labelParts.push(product);
    if (prod2) labelParts.push(prod2);
    if (hasVersion) labelParts.push(`v${String(rawVersion).trim()}`);

    return labelParts.length > 0 ? labelParts.join(' / ') : 'Sin referencia';
}

function getProductSheetStructuredLabel(sheet: ProductSheet): string {
    const sample =
        String(sheet.sample_reference ?? '')
            .trim()
            .toUpperCase() || '-';
    const product =
        String(sheet.product_reference ?? '')
            .trim()
            .toUpperCase() || '-';
    const prod2 = String((sheet as ProductSheet & { product_reference_2?: string }).product_reference_2 ?? '')
        .trim()
        .toUpperCase();
    const version = Number((sheet as ProductSheet & { version?: number }).version ?? 1);
    let label = `${sample} / ${product}`;
    if (prod2) label += ` / ${prod2}`;
    label += ` / v${version}`;
    return label;
}

function isProductSheetComplete(sheet: ProductSheet): boolean {
    return (sheet as ProductSheet & { status?: string }).status === 'complete';
}

const productSheetList = computed(() => allProductSheets.value.filter((s) => hasAnyMainReference(s) && isProductSheetComplete(s)));

const productSheetOptions = computed<Option[]>(() =>
    productSheetList.value
        .map((sheet) => ({
            id: getProductSheetValue(sheet),
            name: buildProductSheetSelectLabel(sheet),
        }))
        .filter((opt) => opt.name !== '')
        .sort((a, b) => a.name.localeCompare(b.name, 'es', { sensitivity: 'base' })),
);

const productSheetsById = computed(() => {
    const map = new Map<number, ProductSheet>();
    for (const sheet of productSheetList.value) map.set(getProductSheetValue(sheet), sheet);
    return map;
});

const selectedProductSheet = computed<ProductSheet | null>(() => {
    if (!form.value.productSheetId) return null;
    return productSheetList.value.find((sheet) => getProductSheetValue(sheet) === Number(form.value.productSheetId)) ?? null;
});

function getDisplayReference(productSheetId: number | null | undefined): string {
    if (!productSheetId) return 'Sin referencia';
    const sheet = productSheetsById.value.get(Number(productSheetId));
    if (!sheet) return 'Sin referencia';

    // Si es muestra, usar sample_reference; si es producto, usar product_reference
    if (sheet.type === 'muestra' && sheet.sample_reference) return sheet.sample_reference;
    if (sheet.type === 'producto' && sheet.product_reference) return sheet.product_reference;

    return (
        sheet.sample_reference ||
        sheet.product_reference ||
        (sheet as any).display_reference ||
        sheet.reference ||
        getProductSheetLabel(sheet) ||
        'Sin referencia'
    );
}

const lotsCountModel = computed<string | number>({
    get() {
        return form.value.lotsCount ?? '';
    },
    set(value) {
        if (value === '' || value === null || value === undefined) {
            form.value.lotsCount = null;
            return;
        }

        const numeric = typeof value === 'number' ? value : Number(String(value).replace(',', '.'));
        const integerValue = Math.max(0, Math.floor(numeric));
        form.value.lotsCount = Number.isNaN(integerValue) ? null : integerValue;
    },
});

function handleLotsCountInput(event: Event) {
    const input = event.target as HTMLInputElement;
    input.value = input.value.replace(/[^0-9]/g, '');
}

function updateEstimatedDeliveryDate(lotIndex: number) {
    const lot = lotsForm.value[lotIndex];
    if (!lot) return;

    if (!lot.priority) {
        return;
    }

    if (lot.season && lot.priority) {
        const normalizedPriority = normalizePriority(lot.priority);
        const calculated = calculateEstimatedDeliveryDateByPriority(lot.season, normalizedPriority);
        if (calculated) {
            lot.estimated_delivery_date = calculated;
        }
    }
}

async function fetchNextOpCodes(count: number, prefix: string) {
    try {
        const response = await axios.get('/comercial/programmers/siguiente-codigo', {
            params: { prefix },
        });
        const first = response?.data?.data?.next_code as string | undefined;
        if (!first) {
            nextOpCodes.value = [];
            return;
        }
        // Parse the numeric part and build the preview array
        const match = first.match(/(\D+)(\d+)$/);
        if (!match) {
            nextOpCodes.value = Array(count).fill(first);
            return;
        }
        const pfx = match[1];
        const start = parseInt(match[2], 10);
        nextOpCodes.value = Array.from({ length: count }, (_, i) => `${pfx}${String(start + i).padStart(match[2].length, '0')}`);
    } catch {
        nextOpCodes.value = [];
    }
}

function generateLots() {
    if (!form.value.lotsCount || form.value.lotsCount <= 0) {
        setAlert('Por favor, ingresa una cantidad válida de lotes.', 'warning');
        lotsForm.value = [];
        return;
    }

    if (!form.value.productSheetId) {
        setAlert('Por favor, selecciona una referencia de ficha de producto.', 'warning');
        lotsForm.value = [];
        return;
    }

    formErrors.value = [];
    clearAlert();

    lotsForm.value = Array.from({ length: form.value.lotsCount }, () => ({
        product_sheet_id: form.value.productSheetId,
        season: '',
        dispatch_month: '',
        destination_id: null,
        priority: '',
        estimated_delivery_date: null,
        notes: '',
        status: 'incomplete',
        isEcommerce: false,
        ecommerce_units: null,
        units: null,
        unitsInput: '',
    }));

    const sheet = selectedProductSheet.value;
    const isProduct = sheet?.type === 'producto';
    const prefix = isProduct ? 'MF' : 'MU';
    void fetchNextOpCodes(form.value.lotsCount, prefix);
}

const errorSummary = computed(() => {
    if (formErrors.value.length === 0) return null;

    const totalLots = formErrors.value.length;
    const totalErrors = formErrors.value.reduce((sum, lot) => sum + lot.fields.length, 0);

    return {
        totalLots,
        totalErrors,
        message: `${totalLots} ${totalLots === 1 ? 'lote tiene' : 'lotes tienen'} campos incompletos (${totalErrors} ${
            totalErrors === 1 ? 'campo' : 'campos'
        } en total)`,
    };
});

function parseUnitsInput(value: string | number | null | undefined): number | null {
    if (value === null || value === undefined) return null;

    const raw = typeof value === 'string' ? value.trim() : String(value);
    if (raw === '') return null;

    const numeric = Number(raw.replace(/[^0-9.]/g, ''));
    if (!Number.isFinite(numeric)) return null;

    const positiveInt = Math.max(1, Math.floor(numeric));
    return Number.isFinite(positiveInt) ? positiveInt : null;
}

function getLotErrors(lot: Partial<Programmer>): string[] {
    const errors: string[] = [];
    if (!lot.product_sheet_id) errors.push('Referencia');
    if (!lot.season) errors.push('Temporada');
    if (!lot.destination_id) errors.push('Destino');

    const units = lot.units;
    const isValidUnits = units !== null && units !== undefined && !Number.isNaN(Number(units)) && Number(units) > 0;

    if (!isValidUnits) errors.push('Unidades');

    return errors;
}

function validateForm() {
    formErrors.value = lotsForm.value
        .map((lot, index) => {
            const fields = getLotErrors(lot);
            return fields.length > 0 ? { lotIndex: index, fields } : null;
        })
        .filter((e): e is { lotIndex: number; fields: string[] } => Boolean(e));
}

function validateLotField(lotIndex: number) {
    const lot = lotsForm.value[lotIndex];
    if (!lot) return;

    const idx = formErrors.value.findIndex((e) => e.lotIndex === lotIndex);
    const fields = getLotErrors(lot);

    if (fields.length > 0) {
        if (idx >= 0) formErrors.value[idx].fields = fields;
        else formErrors.value.push({ lotIndex, fields });
        return;
    }

    if (idx >= 0) formErrors.value.splice(idx, 1);
}

function resolveEcommerceValues(value?: string | Record<string, number> | null): Record<string, number> | null {
    if (!value) return null;

    if (typeof value === 'string') {
        try {
            const parsed = JSON.parse(value);
            if (parsed && typeof parsed === 'object') return parsed as Record<string, number>;
        } catch {
            return null;
        }
    }

    if (typeof value === 'object') return value as Record<string, number>;
    return null;
}

function getDialogProductSheet(sheet: ProductSheet | null): { sizes: string | Record<string, boolean> | null; reference: string } {
    return {
        sizes: normalizeSizes(sheet?.sizes),
        reference: sheet ? getProductSheetLabel(sheet) : '',
    };
}

function buildLotsPayload() {
    return lotsForm.value.map((lot) => {
        // ecommerce_units: solo enviar si ecommerce y hay tallas
        let ecommerceUnitsValue: Record<string, number> | null = null;

        if (lot.isEcommerce && lot.ecommerce_units) {
            const asObj = resolveEcommerceValues(lot.ecommerce_units);
            if (asObj && Object.keys(asObj).length > 0) {
                ecommerceUnitsValue = asObj;
            }
        }

        const unitsValue = lot.units ? Number(lot.units) : null;

        const estimatedDate =
            typeof lot.estimated_delivery_date === 'string' && lot.estimated_delivery_date.trim() !== '' ? lot.estimated_delivery_date.trim() : null;

        return {
            product_sheet_id: lot.product_sheet_id,
            season: normalizeSeason(lot.season),
            dispatch_month: normalizeSeason(lot.dispatch_month) ?? normalizeSeason(lot.season),
            destination_id: lot.destination_id || null,
            priority: normalizePriority(lot.priority),
            estimated_delivery_date: estimatedDate,
            notes: typeof lot.notes === 'string' ? lot.notes.trim() : null,
            units: unitsValue,
            ecommerce_units: ecommerceUnitsValue,
            status: lot.status,
        };
    });
}

function getRequestId() {
    return typeof globalThis.crypto?.randomUUID === 'function'
        ? globalThis.crypto.randomUUID()
        : `${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

async function createProgrammer() {
    if (isSubmitting.value) return;

    if (lotsForm.value.length === 0) {
        setAlert('Debes generar al menos un lote antes de guardar.', 'warning');
        return;
    }

    validateForm();

    if (formErrors.value.length > 0) {
        setAlert(`Por favor, completa todos los campos requeridos. ${errorSummary.value?.message ?? ''}`.trim(), 'warning');
        return;
    }

    const requestId = getRequestId();
    isSubmitting.value = true;

    try {
        const response = await axios.post('/comercial/programador', { lots: buildLotsPayload() }, { headers: { 'X-Request-Id': requestId } });

        const successText =
            typeof response?.data?.message === 'string' && response.data.message.trim() !== ''
                ? response.data.message.trim()
                : 'Programadores creados exitosamente.';

        emit('programmerCreated', successText);
        closeDialog();
    } catch (error: unknown) {
        console.error('Error al crear los programadores:', error);

        let errorText = 'Hubo un error al crear los programadores.';
        if (axios.isAxiosError(error) && error.response?.data?.message) {
            errorText = error.response.data.message;
        }

        setAlert(errorText, 'error');
    } finally {
        isSubmitting.value = false;
    }
}

async function resetForm() {
    isFormInitializing.value = true;
    try {
        form.value = { lotsCount: null, productSheetId: null };
        lotsForm.value = [];
        nextOpCodes.value = [];
        formErrors.value = [];
        productSheetDialogOpen.value = false;
        dialogInnerRef.value = null;
        clearAlert();
    } finally {
        isFormInitializing.value = false;
    }
}

function closeDialog() {
    resetForm();
    isDialogOpen.value = false;
}

watch(
    () => form.value.productSheetId,
    (newId) => {
        if (!newId) {
            lotsForm.value = [];
            formErrors.value = [];
            return;
        }

        if (lotsForm.value.length > 0) {
            lotsForm.value = lotsForm.value.map((lot) => ({
                ...lot,
                product_sheet_id: newId,
            }));
        }
    },
);
</script>

<template>
    <Dialog @open="resetForm" v-model:open="isDialogOpen">
        <DialogTrigger as-child>
            <Button variant="default" size="sm" class="flex h-10 items-center gap-2 rounded-full px-5 py-2 text-sm font-semibold shadow-sm">
                <PlusCircle class="h-4 w-4" />
                Nuevo Programador
            </Button>
        </DialogTrigger>

        <FormDialogContent class="flex w-[calc(100vw-2rem)] max-w-[1800px] flex-col overflow-hidden p-0">
            <DialogHeader class="flex-shrink-0 border-b px-6 pb-4 pt-6">
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
                <div ref="dialogInnerRef" class="relative min-h-0 flex-1 overflow-y-auto scroll-smooth px-6 py-4" style="scrollbar-width: thin">
                    <FormLoadingOverlay :active="isFormInitializing" message="Preparando formulario..." :delay-ms="100" />

                    <div v-if="canRenderForm" class="grid grid-cols-1 gap-4">
                        <section class="rounded-xl border border-border/70 bg-muted/20 p-4">
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                            <!-- Referencia -->
                            <div class="flex flex-col gap-1.5">
                                <div class="flex items-center gap-2">
                                    <Label class="text-sm font-medium"> Referencia Seleccionada<span class="text-red-500">*</span> </Label>
                                    <HelpTooltip
                                        text="Selecciona la sábana de producto que deseas programar para producción."
                                        aria-label="Ayuda sobre referencia"
                                    />
                                </div>
                                <SelectField
                                    v-model="form.productSheetId"
                                    label=""
                                    :options="productSheetOptions"
                                    :searchable="true"
                                    placeholder="Seleccionar referencia..."
                                    search-placeholder="Buscar por RefMuestra / RefProducto / Marca / RefExportación..."
                                    required
                                />
                            </div>

                            <!-- Cantidad lotes -->
                            <div class="flex flex-col gap-1.5">
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
                                    placeholder="Número de lotes"
                                    type="number"
                                    min="1"
                                    step="1"
                                    required
                                    @input="handleLotsCountInput"
                                    @update:modelValue="generateLots"
                                />
                            </div>
                            </div>
                        </section>

                        <!-- Detalles product sheet -->
                        <section v-if="selectedProductSheet" class="rounded-xl border border-border/70 bg-background p-4">
                            <h3 class="mb-2 text-base font-bold">Detalles de la Ficha de Producto</h3>

                            <div class="overflow-x-auto rounded-lg border border-border/70">
                                <table class="w-full min-w-[980px] text-left text-sm">
                                    <tbody>
                                        <tr>
                                            <td class="bg-muted/40 px-3 py-1.5 font-semibold">Referencia:</td>
                                            <td class="border border-l-0 border-border/60 px-3 py-1.5">
                                                {{ getProductSheetLabel(selectedProductSheet) || 'Sin referencia' }}
                                            </td>
                                            <td class="bg-muted/40 px-3 py-1.5 font-semibold">Sección:</td>
                                            <td class="border border-l-0 border-border/60 px-3 py-1.5">
                                                {{ selectedProductSheet.section?.name || 'Sin sección' }}
                                            </td>
                                            <td class="bg-muted/40 px-3 py-1.5 font-semibold">Grupo:</td>
                                            <td class="border border-l-0 border-border/60 px-3 py-1.5">
                                                {{ selectedProductSheet.group?.name || 'Sin grupo' }}
                                            </td>
                                            <td class="bg-muted/40 px-3 py-1.5 font-semibold">Marca:</td>
                                            <td class="border border-l-0 border-border/60 px-3 py-1.5">
                                                {{ selectedProductSheet.brand?.name || 'Sin marca' }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="border border-r-0 border-border/60 bg-muted/40 px-3 py-1.5 font-semibold">Mundo:</td>
                                            <td class="border border-l-0 border-border/60 px-3 py-1.5">
                                                {{ selectedProductSheet.world?.name || 'Sin mundo' }}
                                            </td>
                                            <td class="border border-r-0 border-border/60 bg-muted/40 px-3 py-1.5 font-semibold">Base:</td>
                                            <td class="border border-l-0 border-border/60 px-3 py-1.5">
                                                {{ selectedProductSheet.fabric_base?.name || 'Sin base' }}
                                            </td>
                                            <td class="border border-r-0 border-border/60 bg-muted/40 px-3 py-1.5 font-semibold">Precio Facturación:</td>
                                            <td class="border border-l-0 border-border/60 px-3 py-1.5">
                                                {{ selectedProductSheet.billing_price ?? 'N/A' }}
                                            </td>
                                            <td class="border border-r-0 border-border/60 bg-muted/40 px-3 py-1.5 font-semibold">Precio Venta:</td>
                                            <td class="border border-l-0 border-border/60 px-3 py-1.5">
                                                {{ selectedProductSheet.selling_price ?? 'N/A' }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="border border-border/60 bg-muted/40 px-3 py-2 font-semibold">Tallas:</td>
                                            <td class="border border-border/60 px-3 py-2" colspan="3">
                                                {{ sizesToLabel(selectedProductSheet?.sizes) }}
                                            </td>
                                            <td class="border border-border/60 bg-muted/40 px-3 py-2 font-semibold">Descripción:</td>
                                            <td class="border border-border/60 px-3 py-2" colspan="3">
                                                {{ selectedProductSheet.description || 'Sin descripción' }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </div>

                    <!-- Lotes -->
                    <section v-if="lotsForm.length > 0" class="mt-4 rounded-xl border border-border/70 bg-background p-4">
                        <h3 class="mb-2 text-base font-bold">Lotes Generados:</h3>

                        <div class="overflow-x-auto overflow-y-hidden rounded-lg border border-border/70 shadow-sm">
                            <table class="w-full min-w-[1500px] text-center text-sm">
                                <thead>
                                    <tr class="bg-muted/50">
                                        <th class="border border-border/60 bg-muted/50 px-3 py-2 font-semibold">Lote</th>
                                        <th class="border border-border/60 bg-muted/50 px-3 py-2 font-semibold">Referencia</th>
                                        <th class="border border-border/60 bg-muted/50 px-3 py-2 font-semibold">Temporada (Mes Año)</th>
                                        <th class="border border-border/60 bg-muted/50 px-3 py-2 font-semibold">Mes despacho (Mes Año)</th>
                                        <th class="border border-border/60 bg-muted/50 px-3 py-2 font-semibold">Destino</th>
                                        <th class="border border-border/60 bg-muted/50 px-3 py-2 font-semibold">Prioridad</th>
                                        <th class="border border-border/60 bg-muted/50 px-3 py-2 font-semibold">Fecha entrega estimada</th>
                                        <th class="border border-border/60 bg-muted/50 px-3 py-2 font-semibold">Nota</th>
                                        <th class="border border-border/60 bg-muted/50 px-3 py-2 font-semibold">E-commerce (opcional)</th>
                                        <th class="border border-border/60 bg-muted/50 px-3 py-2 font-semibold">Unidades</th>
                                        <th v-if="lotsForm.some((lot) => lot.isEcommerce)" class="border border-border/60 bg-muted/50 px-3 py-2 font-semibold">
                                            Cant. E-commerce
                                        </th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <tr v-for="(lot, index) in lotsForm" :key="index" class="hover:bg-muted/30">
                                        <td class="whitespace-nowrap border border-border/60 px-3 py-2 font-mono text-sm text-muted-foreground">
                                            {{ nextOpCodes[index] ?? '—' }}
                                        </td>

                                        <td class="border border-border/60 px-3 py-2">
                                            {{ getDisplayReference(lot.product_sheet_id) }}
                                        </td>

                                        <td class="border border-border/60 px-3 py-2" style="min-width: 220px">
                                            <SelectField
                                                v-model="lot.season"
                                                :options="monthOptions"
                                                label=""
                                                :aria-label="`Temporada para lote ${index + 1}`"
                                                :class="{ 'border-red-500': !lot.season }"
                                                @update:modelValue="
                                                    (value) => {
                                                        lot.season = value !== null && value !== undefined ? String(value) : null;
                                                        validateLotField(index);
                                                        updateEstimatedDeliveryDate(index);
                                                    }
                                                "
                                            />
                                        </td>

                                        <td class="border border-border/60 px-3 py-2" style="min-width: 220px">
                                            <SelectField
                                                v-model="lot.dispatch_month"
                                                :options="monthOptions"
                                                label=""
                                                :aria-label="`Mes despacho para lote ${index + 1}`"
                                                @update:modelValue="
                                                    (value) => {
                                                        lot.dispatch_month = value !== null && value !== undefined ? String(value) : null;
                                                        validateLotField(index);
                                                    }
                                                "
                                            />
                                        </td>

                                        <td class="border border-border/60 px-3 py-2" style="min-width: 260px">
                                            <SelectField
                                                v-model="lot.destination_id"
                                                :options="destinationOptions"
                                                label=""
                                                :aria-label="`Destino para lote ${index + 1}`"
                                                :class="{ 'border-red-500': !lot.destination_id }"
                                                @update:modelValue="validateLotField(index)"
                                            />
                                        </td>

                                        <td class="border border-border/60 px-3 py-2" style="min-width: 150px">
                                            <SelectField
                                                v-model="lot.priority"
                                                :options="priorityOptions"
                                                label=""
                                                :aria-label="`Prioridad para lote ${index + 1}`"
                                                @update:modelValue="
                                                    (value) => {
                                                        lot.priority = value !== null && value !== undefined ? String(value) : null;
                                                        validateLotField(index);
                                                        updateEstimatedDeliveryDate(index);
                                                    }
                                                "
                                            />
                                        </td>

                                        <td class="border border-border/60 px-3 py-2" style="min-width: 210px">
                                            <DatePickerField
                                                v-model="lot.estimated_delivery_date"
                                                label=""
                                                :clearable="true"
                                                :show-today="true"
                                            />
                                        </td>

                                        <td class="border border-border/60 px-3 py-2" style="min-width: 260px">
                                            <InputField data-no-uppercase
                                                v-model="lot.notes"
                                                label=""
                                                placeholder="Nota opcional"
                                                :aria-label="`Nota para lote ${index + 1}`"
                                            />
                                        </td>

                                        <td class="border border-border/60 px-3 py-2 text-center">
                                            <input
                                                type="checkbox"
                                                v-model="lot.isEcommerce"
                                                :aria-label="`Habilitar e-commerce para lote ${index + 1}`"
                                                class="h-5 w-5 cursor-pointer rounded border-border text-primary focus:ring-primary"
                                                @change="
                                                    () => {
                                                        if (!lot.isEcommerce) lot.ecommerce_units = null;
                                                    }
                                                "
                                            />
                                        </td>

                                        <td class="border border-border/60 px-3 py-2" style="min-width: 180px">
                                            <InputField
                                                :model-value="lot.unitsInput ?? ''"
                                                inputmode="numeric"
                                                @update:modelValue="
                                                    (value) => {
                                                        const raw = typeof value === 'string' ? value : String(value);
                                                        lot.unitsInput = raw;
                                                        lot.units = parseUnitsInput(raw);
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

                                        <td v-if="lotsForm.some((item) => item.isEcommerce)" class="border border-border/60 px-3 py-2 text-center">
                                            <EcommerceSizesDialog
                                                v-if="lot.isEcommerce"
                                                :productSheet="getDialogProductSheet(selectedProductSheet)"
                                                :existingValues="resolveEcommerceValues(lot.ecommerce_units)"
                                                @sizesUpdated="
                                                    (data) => {
                                                        if (data?.sizes && Object.keys(data.sizes).length > 0) {
                                                            const sanitized = Object.fromEntries(
                                                                Object.entries(data.sizes).map(([size, value]) => [
                                                                    size,
                                                                    typeof value === 'number' ? Math.max(0, value) : 0,
                                                                ]),
                                                            );
                                                            lot.ecommerce_units = sanitized;
                                                        } else {
                                                            lot.ecommerce_units = {};
                                                        }
                                                    }
                                                "
                                            />
                                            <span v-else class="text-xs text-muted-foreground">—</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </TooltipProvider>

            <div class="flex flex-shrink-0 flex-col items-end gap-2 border-t bg-background px-6 py-4">
                <div class="flex w-full max-w-lg items-center justify-end gap-3">
                    <button
                        type="button"
                        class="flex items-center justify-center gap-2 rounded-full border border-primary/40 bg-white px-6 py-2 text-sm font-semibold text-primary transition hover:bg-primary/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                        @click="closeDialog"
                    >
                        Cancelar
                    </button>

                    <Button
                        type="button"
                        class="flex items-center justify-center rounded-full px-6 py-2 text-sm font-semibold shadow-sm"
                        :disabled="saveDisabled || isSubmitting"
                        @click="createProgrammer"
                    >
                        {{ isSubmitting ? 'Guardando...' : 'Guardar' }}
                    </Button>
                </div>
            </div>
        </FormDialogContent>
    </Dialog>
</template>
