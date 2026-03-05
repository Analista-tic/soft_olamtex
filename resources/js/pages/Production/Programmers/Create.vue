<script setup lang="ts">

import { computed, ref, watch } from 'vue';
import axios from 'axios';
import { PlusCircle } from 'lucide-vue-next';

import EcommerceSizesDialog from './EcommerceSizesDialog.vue';
import CreateProductSheetDialog from '../ProductSheets/ProductSheetDialog.vue';

import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import AlertStack from '@/components/common/AlertStack.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';
import Label from '@/components/ui/label/Label.vue';
import { TooltipProvider } from '@/components/ui/tooltip';

import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import FormDialogContent from '@/components/FormDialogContent.vue';

import { normalizeSizes, sizesToLabel } from '@/domain/Production';
import type { ProductSheet, Programmer } from '@/domain/Production';
import type { Option } from '@/domain/shared/types';

import { useAlertStack } from '@/composables/useAlert';

const emit = defineEmits<{
    (e: 'programmerCreated'): void;
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

type ProgrammerLotForm = Partial<Programmer> & {
    /** input original (para no “pelear” con el usuario mientras tipea) */
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

const monthOptions = computed<Option[]>(() =>
    Array.isArray(props.months) && props.months.length > 0 ? props.months : MONTHS_FALLBACK,
);

const priorityOptions = computed<Option[]>(() =>
    Array.isArray(props.priorities) && props.priorities.length > 0 ? props.priorities : PRIORITIES_FALLBACK,
);

const destinationOptions = computed<Option[]>(() => props.destinations ?? []);

/** Normaliza un option “value” a su name (string) */
function normalizeOptionToName(
    value: string | number | null | undefined,
    options: Option[],
): string | null {
    if (value === null || value === undefined || value === '') return null;

    const byId = options.find((opt) => opt.id === value || String(opt.id) === String(value));
    if (byId) return String(byId.name).toUpperCase();

    const byName = options.find(
        (opt) => String(opt.name).toUpperCase() === String(value).toUpperCase(),
    );
    if (byName) return String(byName.name).toUpperCase();

    return String(value).toUpperCase();
}

const normalizeSeason = (value: string | number | null | undefined) =>
    normalizeOptionToName(value, monthOptions.value);

const normalizePriority = (value: string | number | null | undefined) =>
    normalizeOptionToName(value, priorityOptions.value);

const {
    successMessage,
    errorMessage,
    errorType,
    showSuccess,
    showError,
    clearAlerts,
    clearSuccess,
    clearError,
} = useAlertStack();

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
const formErrors = ref<Array<{ lotIndex: number; fields: string[] }>>([]);
const dialogInnerRef = ref<HTMLElement | null>(null);

const isSubmitting = ref(false);
const saveDisabled = computed(() => formErrors.value.length > 0);

const allProductSheets = ref<ProductSheet[]>(
    Array.isArray(props.productSheets) ? [...props.productSheets] : [],
);

function getProductSheetValue(sheet: ProductSheet): number {
    const candidate = (sheet as ProductSheet & { value?: number | string }).value ?? sheet.id;
    return Number(candidate);
}

function getProductSheetLabel(sheet: ProductSheet): string {
    const typed = sheet as ProductSheet & { label?: string; display_reference?: string };
    return String(typed.label ?? typed.display_reference ?? sheet.reference ?? '').trim();
}

function getProductReference(sheet: ProductSheet): string {
    return String(sheet.product_reference ?? '').trim();
}

function hasProductReference(sheet: ProductSheet): boolean {
    return getProductReference(sheet).length > 0;
}

const productSheetList = computed(() => allProductSheets.value.filter(hasProductReference));

const productSheetOptions = computed<Option[]>(() =>
    productSheetList.value
        .map((sheet) => ({
            id: getProductSheetValue(sheet),
            name: getProductReference(sheet),
        }))
        .filter((opt) => opt.name !== ''),
);

const productSheetsById = computed(() => {
    const map = new Map<number, ProductSheet>();
    for (const sheet of productSheetList.value) map.set(getProductSheetValue(sheet), sheet);
    return map;
});

const selectedProductSheet = computed<ProductSheet | null>(() => {
    if (!form.value.productSheetId) return null;
    return productSheetList.value.find(
        (sheet) => getProductSheetValue(sheet) === Number(form.value.productSheetId),
    ) ?? null;
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
    if (!lot.priority) errors.push('Prioridad');

    const units = lot.units;
    const isValidUnits =
        units !== null && units !== undefined && !Number.isNaN(Number(units)) && Number(units) > 0;

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

function resolveEcommerceValues(
    value?: string | Record<string, number> | null,
): Record<string, number> | null {
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

interface ProductSheetSavedPayload {
    success?: boolean;
    message?: string;
    data?: ProductSheet;
}

function addProductSheet(response: ProductSheetSavedPayload) {
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
        setAlert(
            `Por favor, completa todos los campos requeridos. ${errorSummary.value?.message ?? ''}`.trim(),
            'warning',
        );
        return;
    }

    const requestId = getRequestId();
    isSubmitting.value = true;

    try {
        await axios.post(
            '/comercial/programador',
            { lots: buildLotsPayload() },
            { headers: { 'X-Request-Id': requestId } },
        );

        setAlert('Programadores creados exitosamente.', 'success');
        emit('programmerCreated');
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

function resetForm() {
    form.value = { lotsCount: null, productSheetId: null };
    lotsForm.value = [];
    formErrors.value = [];
    productSheetDialogOpen.value = false;
    dialogInnerRef.value = null;
    clearAlert();
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
            <Button
                variant="default"
                size="sm"
                class="flex items-center gap-2 rounded-full px-5 py-2 text-sm font-semibold shadow-sm h-10"
            >
                <PlusCircle class="h-4 w-4" />
                Nuevo Programador
            </Button>
        </DialogTrigger>

        <FormDialogContent class="flex flex-col max-h-[200vh] max-w-[200vh] px-2 py-2">
            <DialogHeader class="flex-shrink-0 px-6 pt-6 pb-4">
                <DialogTitle>Crear Nuevo Programador</DialogTitle>
                <DialogDescription>
                    Completa los campos para crear un programador. Haz clic en guardar cuando termines.
                </DialogDescription>
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
                <div
                    ref="dialogInnerRef"
                    class="overflow-y-auto flex-1 px-6 pb-24 scroll-smooth"
                    style="scrollbar-width: thin;"
                >
                    <div class="grid grid-cols-1 gap-4 pt-4">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]">
                            <!-- Referencia -->
                            <div class="flex flex-col gap-1">
                                <div class="flex items-center gap-2">
                                    <Label class="text-sm font-medium">
                                        Referencia Seleccionada<span class="text-destructive">*</span>
                                    </Label>
                                    <HelpTooltip
                                        text="Selecciona la sábana de producto que deseas programar para producción."
                                        aria-label="Ayuda sobre referencia"
                                    />
                                </div>
                                <SelectField
                                    v-model="form.productSheetId"
                                    label=""
                                    :options="productSheetOptions"
                                    required
                                />
                            </div>

                            <!-- Cantidad lotes -->
                            <div class="flex flex-col gap-1">
                                <div class="flex items-center gap-2">
                                    <Label class="text-sm font-medium">
                                        Cantidad de Lotes<span class="text-destructive">*</span>
                                    </Label>
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

                            <!-- Acción -->
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

                        <!-- Detalles product sheet -->
                        <div v-if="selectedProductSheet" class="mb-4">
                            <h3 class="text-lg font-bold mb-2">Detalles de la Ficha de Producto</h3>

                            <table class="w-full text-sm text-left border border-border rounded-lg overflow-hidden">
                                <tbody>
                                    <tr>
                                        <td class="px-4 py-2 font-bold">Referencia:</td>
                                        <td class="px-4 py-2 border border-l-0">
                                            {{ getProductSheetLabel(selectedProductSheet) || 'Sin referencia' }}
                                        </td>
                                        <td class="px-4 py-2 font-bold">Sección:</td>
                                        <td class="px-4 py-2 border border-l-0">
                                            {{ selectedProductSheet.section?.name || 'Sin sección' }}
                                        </td>
                                        <td class="px-4 py-2 font-bold">Grupo:</td>
                                        <td class="px-4 py-2 border border-l-0">
                                            {{ selectedProductSheet.group?.name || 'Sin grupo' }}
                                        </td>
                                        <td class="px-4 py-2 font-bold">Marca:</td>
                                        <td class="px-4 py-2 border border-l-0">
                                            {{ selectedProductSheet.brand?.name || 'Sin marca' }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-1 font-bold border border-r-0">Mundo:</td>
                                        <td class="px-4 py-1 border border-l-0">
                                            {{ selectedProductSheet.world?.name || 'Sin mundo' }}
                                        </td>
                                        <td class="px-4 py-1 font-bold border border-r-0">Base:</td>
                                        <td class="px-4 py-1 border border-l-0">
                                            {{ selectedProductSheet.fabric_base?.name || 'Sin base' }}
                                        </td>
                                        <td class="px-4 py-1 font-bold border border-r-0">Precio Facturación:</td>
                                        <td class="px-4 py-1 border border-l-0">
                                            {{ selectedProductSheet.billing_price ?? 'N/A' }}
                                        </td>
                                        <td class="px-4 py-1 font-bold border border-r-0">Precio Venta:</td>
                                        <td class="px-4 py-1 border border-l-0">
                                            {{ selectedProductSheet.selling_price ?? 'N/A' }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-5 font-bold border">Tallas:</td>
                                        <td class="px-8 py-5 border" colspan="3">
                                            {{ sizesToLabel(selectedProductSheet?.sizes) }}
                                        </td>
                                        <td class="px-4 py-5 font-bold border">Descripción:</td>
                                        <td class="px-4 py-5 border" colspan="3">
                                            {{ selectedProductSheet.description || 'Sin descripción' }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Lotes -->
                    <div v-if="lotsForm.length > 0" class="mt-4">
                        <h3 class="text-base font-bold mb-2">Lotes Generados:</h3>

                        <table class="w-full text-sm border border-border rounded-lg overflow-visible shadow-sm text-center">
                            <thead>
                                <tr class="bg-muted/40">
                                    <th class="px-4 py-3 border border-border font-semibold">Lote</th>
                                    <th class="px-4 py-3 border border-border font-semibold">Referencia</th>
                                    <th class="px-4 py-3 border border-border font-semibold">Temporada</th>
                                    <th class="px-4 py-3 border border-border font-semibold">Destino</th>
                                    <th class="px-4 py-3 border border-border font-semibold">Prioridad</th>
                                    <th class="px-4 py-3 border border-border font-semibold">E-commerce (opcional)</th>
                                    <th class="px-4 py-3 border border-border font-semibold">Unidades</th>
                                    <th v-if="lotsForm.some((lot) => lot.isEcommerce)" class="px-4 py-3 border font-semibold">
                                        Cant. E-commerce
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr v-for="(lot, index) in lotsForm" :key="index" class="hover:bg-muted/30">
                                    <td class="px-4 py-4 border whitespace-nowrap">MF300{{ index + 1 }}</td>

                                    <td class="px-4 py-4 border">
                                        {{ getDisplayReference(lot.product_sheet_id) }}
                                    </td>

                                    <td class="px-4 py-4 border" style="min-width: 180px;">
                                        <SelectField
                                            v-model="lot.season"
                                            :options="monthOptions"
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
                                            :options="priorityOptions"
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
                                            class="form-checkbox h-5 w-5 cursor-pointer text-primary"
                                            @change="
                                                () => {
                                                    if (!lot.isEcommerce) lot.ecommerce_units = null;
                                                }
                                            "
                                        />
                                    </td>

                                    <td class="px-4 py-4 border" style="min-width: 180px;">
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

                                    <td v-if="lot.isEcommerce" class="px-4 py-4 border text-center">
                                        <EcommerceSizesDialog
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
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </TooltipProvider>

            <!-- Footer fijo -->
            <div
                class="absolute bottom-0 right-0 left-0 rounded-b-[24px] border-t border-border bg-background px-6 py-4 shadow-lg flex flex-col gap-2 items-end"
            >
                <div class="flex items-center justify-end gap-3 w-full max-w-lg">
                    <Button variant="outline" size="pill" class="px-6 py-2.5" @click="closeDialog">
                        Cancelar
                    </Button>

                    <Button
                        type="button"
                        class="flex items-center justify-center rounded-full px-6 py-2.5 text-sm font-semibold shadow-sm"
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
