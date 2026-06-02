<script setup lang="ts">
import axios from 'axios';
import { computed, ref } from 'vue';

import AlertStack from '@/components/common/AlertStack.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import DatePickerField from '@/components/DatePickerField.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import FormLoadingOverlay from '@/components/FormLoadingOverlay.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import EcommerceSizesDialog from './EcommerceSizesDialog.vue';

import { useAlertStack } from '@/composables/useAlert';
import type { OptionParams, ProductSheet, Programmer } from '@/domain/Production';
import { calculateEstimatedDeliveryDateByPriority } from '@/domain/Production';
import { normalizeSizes } from '@/domain/Production';
import type { Option } from '@/domain/shared/types';

import { Button } from '@/components/ui/button';
import Badge from '@/components/ui/badge/Badge.vue';
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { ArrowLeftRight } from 'lucide-vue-next';
import Label from '@/components/ui/label/Label.vue';
import { TooltipProvider } from '@/components/ui/tooltip';

import { format, isValid, parse } from 'date-fns';
import { watch } from 'vue';

const props = defineProps<{
    destinations: OptionParams[];
    productSheets: ProductSheet[];
    months: Option[];
}>();

const emits = defineEmits<{
    (event: 'programmerUpdated', message?: string): void;
}>();

type AlertType = 'success' | 'error' | 'info' | 'warning';
type SelectOption = { id: string | number; name: string };
type EditableProgrammer = Omit<Programmer, 'season'> & { season: string | null };

const ALERT_TITLES: Record<AlertType, string> = {
    warning: 'Advertencia',
    success: 'Exito',
    info: 'Informacion',
    error: 'Error',
};

const PRIORITY_VALUES = ['A', 'B', 'C', 'D'] as const;

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

const { successMessage, errorMessage, errorType, showError, clearAlerts, clearSuccess, clearError } = useAlertStack();

const errorTitle = computed(() => ALERT_TITLES[errorType.value]);

function scrollToTop() {
    if (dialogInnerRef.value?.scrollTo) {
        dialogInnerRef.value.scrollTo({ top: 0, behavior: 'smooth' });
        return;
    }
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function setAlert(message: string, type: Exclude<AlertType, 'success'> = 'error') {
    showError(message, type);
    scrollToTop();
}

function createEmptyProgrammer(): EditableProgrammer {
    return {
        id: 0,
        op_code: null,
        product_sheet_id: null,
        product_sheet: null,
        season: null,
        season_display: null,
        destination: null,
        destination_id: null,
        priority: null,
        isEcommerce: false,
        ecommerce_units: null,
        units: null,
        estimated_delivery_date: null,
        status: 'incomplete',
        source: undefined,
        is_auto_from_product_sheet: false,
        is_pending_completion: false,
        pending_fields: [],
        created_at: '',
        updated_at: '',
    };
}

function getProductSheetValue(sheet: ProductSheet): number {
    const candidate = (sheet as ProductSheet & { value?: number | string }).value ?? sheet.id;
    return Number(candidate);
}

function getProductSheetLabel(sheet: ProductSheet): string {
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

function resolveEcommerceValues(value?: string | Record<string, number> | null): Record<string, number> | null {
    if (!value) return null;

    if (typeof value === 'string') {
        try {
            const parsed = JSON.parse(value);
            return parsed && typeof parsed === 'object' ? (parsed as Record<string, number>) : null;
        } catch {
            return null;
        }
    }

    return typeof value === 'object' ? (value as Record<string, number>) : null;
}

/** Normaliza a yyyy-MM-dd o null */
function normalizeIsoDate(raw: unknown): string | null {
    if (raw === null || raw === undefined) return null;

    const text = String(raw).trim();
    if (text === '' || text === '-' || text.toUpperCase() === 'N/A') return null;

    const isoMatch = text.match(/^(\d{4}-\d{2}-\d{2})/);
    if (isoMatch) return isoMatch[1];

    const parsedSlash = parse(text, 'dd/MM/yyyy', new Date());
    if (isValid(parsedSlash)) return format(parsedSlash, 'yyyy-MM-dd');

    return null;
}

const isDialogOpen = ref(false);
const dialogMode = ref<'edit' | 'view'>('edit');
const programmer = ref<EditableProgrammer>(createEmptyProgrammer());
const isLoading = ref(false);
const isFormInitializing = ref(false);
const canRenderForm = computed(() => !isFormInitializing.value);

// ── Confirmación de cambio de referencia ─────────────────────────────
const showChangeRefConfirm = ref(false);
const originalProductSheetId = ref<number | null>(null);
const confirmingRefChange = ref(false);

const referenceChanged = computed(() =>
    !isReadOnly.value &&
    originalProductSheetId.value !== null &&
    Number(programmer.value.product_sheet_id) !== Number(originalProductSheetId.value),
);

const newReferenceLabel = computed(() => {
    const sheet = props.productSheets.find(
        (s) => getProductSheetValue(s) === Number(programmer.value.product_sheet_id),
    );
    return sheet ? getProductSheetLabel(sheet) : '';
});

const dialogInnerRef = ref<HTMLElement | null>(null);

const isReadOnly = computed(() => dialogMode.value === 'view');

function isAutoRow(row: EditableProgrammer) {
    return row.is_auto_from_product_sheet === true;
}

const isAutoGenerated = computed(() => isAutoRow(programmer.value));
const isManual = computed(() => !isAutoGenerated.value);

const dialogTitle = computed(() => (isReadOnly.value ? 'Ver Lote Programado' : 'Editar Lote Programado'));

const dialogDescription = computed(() =>
    isReadOnly.value ? 'Aqui puedes ver los detalles del programador seleccionado.' : 'Aqui puedes editar los detalles del programador seleccionado.',
);

const destinationOptions = computed<SelectOption[]>(() =>
    (props.destinations ?? [])
        .filter((d) => typeof d.id === 'number' || typeof d.id === 'string')
        .map((d) => ({ id: d.id as string | number, name: d.name })),
);

const monthSelectOptions = computed<SelectOption[]>(() => {
    const baseMonths = (props.months ?? []).length > 0 ? props.months : MONTHS_FALLBACK;
    const currentYear = new Date().getFullYear();
    const years = [currentYear, currentYear + 1];

    return years.flatMap((year) =>
        baseMonths
            .filter((m) => typeof m.id === 'number' || typeof m.id === 'string')
            .map((m) => {
                const monthName = String(m.name).toUpperCase();
                return { id: `${monthName} ${year}`, name: `${monthName} ${year}` };
            }),
    );
});

const seasonOptions = computed<SelectOption[]>(() => monthSelectOptions.value);

const priorityOptions = computed<SelectOption[]>(() => PRIORITY_VALUES.map((v) => ({ id: v, name: v })));

const productSheetOptions = computed<SelectOption[]>(() =>
    (props.productSheets ?? [])
        .map((sheet) => ({ id: getProductSheetValue(sheet), name: getProductSheetLabel(sheet) }))
        .filter((opt) => opt.name !== ''),
);

const programmerUnits = computed<string | number>({
    get: () => programmer.value.units ?? '',
    set: (value) => {
        const parsed = Number(value);
        programmer.value.units = Number.isFinite(parsed) && parsed > 0 ? Math.floor(parsed) : null;
    },
});

function normalizeSeasonValue(season: string | number | null | undefined): string | null {
    if (season === null || season === undefined || season === '') return null;

    const raw = String(season).trim();
    if (raw === '') return null;

    // Si viene como número/id
    if (/^\d+$/.test(raw)) {
        const byNumericId = monthSelectOptions.value.find((m) => Number(m.id) === Number(raw));
        if (byNumericId) return String(byNumericId.name).toUpperCase();
    } else {
        const byExactId = monthSelectOptions.value.find((m) => String(m.id) === raw);
        if (byExactId) return String(byExactId.name).toUpperCase();
    }

    // Si viene como name
    const upper = raw.toUpperCase();
    const byName = monthSelectOptions.value.find((m) => String(m.name).toUpperCase() === upper);
    if (byName) return String(byName.name).toUpperCase();

    const monthOnlyMatch = upper.match(/(ENERO|FEBRERO|MARZO|ABRIL|MAYO|JUNIO|JULIO|AGOSTO|SEPTIEMBRE|OCTUBRE|NOVIEMBRE|DICIEMBRE)/);
    if (monthOnlyMatch) {
        const monthName = monthOnlyMatch[1];
        const inferredYear = new Date().getFullYear();
        return `${monthName} ${inferredYear}`;
    }

    return upper;
}

function normalizeSeasonForApi(season: string | number | null | undefined): string | null {
    if (season === null || season === undefined || season === '') return null;

    const byId = monthSelectOptions.value.find((m) => String(m.id) === String(season));
    if (byId) return String(byId.name).toUpperCase();

    const raw = String(season).trim();
    if (raw === '') return null;

    if (/^\d+$/.test(raw)) {
        const byNumeric = monthSelectOptions.value.find((m) => Number(m.id) === Number(raw));
        if (byNumeric) return String(byNumeric.name).toUpperCase();
    }

    const byName = monthSelectOptions.value.find((m) => String(m.name).toUpperCase() === raw.toUpperCase());
    if (byName) return String(byName.name).toUpperCase();

    return raw.toUpperCase();
}

function normalizePriorityForApi(priority: string | number | null | undefined): (typeof PRIORITY_VALUES)[number] | null {
    if (priority === null || priority === undefined || priority === '') return null;

    const normalized = String(priority).trim().toUpperCase();
    if (!normalized) return null;

    return PRIORITY_VALUES.includes(normalized as any) ? (normalized as (typeof PRIORITY_VALUES)[number]) : null;
}

function calculateEstimatedDeliveryDate(season: string | number | null | undefined, priority: string | number | null | undefined): string | null {
    const normalizedSeason = normalizeSeasonForApi(season);
    const normalizedPriority = normalizePriorityForApi(priority);

    if (!normalizedSeason || !normalizedPriority) return null;

    return calculateEstimatedDeliveryDateByPriority(normalizedSeason, normalizedPriority);
}

function updateEstimatedDeliveryDate() {
    if (isReadOnly.value) return;

    const priority = normalizePriorityForApi(programmer.value.priority);
    if (!priority) {
        return;
    }

    const calculated = calculateEstimatedDeliveryDate(programmer.value.season, priority);
    if (calculated) {
        programmer.value.estimated_delivery_date = calculated;
    }
}

const selectedSeasonLabel = computed(() => normalizeSeasonForApi(programmer.value.season) || 'Sin temporada');

const orderResumeStatusTone = computed(() => ((programmer.value.is_pending_completion ?? false) ? 'destructive' : 'default'));
const orderResumeStatusText = computed(() => ((programmer.value.is_pending_completion ?? false) ? 'Pendiente' : 'Completo'));

const ecommerceSummary = computed(() => {
    const values = resolveEcommerceValues(programmer.value.ecommerce_units);
    if (!values) return { configuredSizes: 0, totalUnits: 0, configured: false };

    const configuredEntries = Object.entries(values).filter(([, qty]) => Number(qty) > 0);
    const configuredSizes = configuredEntries.length;
    const totalUnits = configuredEntries.reduce((total, [, qty]) => total + Number(qty), 0);

    return { configuredSizes, totalUnits, configured: configuredSizes > 0 };
});

const ecommerceStatusText = computed(() =>
    ecommerceSummary.value.configured
        ? `Configurado: ${ecommerceSummary.value.configuredSizes} tallas (${ecommerceSummary.value.totalUnits} unds)`
        : 'Sin configuracion',
);

const ecommerceActionLabel = computed(() => {
    if (isReadOnly.value) return 'Ver';
    return ecommerceSummary.value.configured ? 'Editar' : 'Configurar';
});

function getReferenceName(productSheetId: number | null) {
    const sheet = (props.productSheets ?? []).find((s) => getProductSheetValue(s) === Number(productSheetId));
    return sheet ? getProductSheetLabel(sheet) : 'Referencia';
}

function getDialogProductSheet(productSheetId: number | null): { sizes: string | Record<string, boolean> | null; reference: string } {
    const sheet = (props.productSheets ?? []).find((s) => getProductSheetValue(s) === Number(productSheetId));
    return {
        sizes: normalizeSizes(sheet?.sizes),
        reference: getReferenceName(productSheetId),
    };
}

function initializeEcommerceSizes() {
    try {
        const raw = programmer.value.ecommerce_units;

        if (typeof raw === 'string') {
            programmer.value.ecommerce_units = JSON.parse(raw || '{}');
            return;
        }

        if (raw && typeof raw === 'object') {
            programmer.value.ecommerce_units = raw;
            return;
        }

        programmer.value.ecommerce_units = {};
    } catch {
        programmer.value.ecommerce_units = {};
    }
}

async function openDialog(selectedProgrammer: Programmer, mode: 'edit' | 'view' = 'edit') {
    isFormInitializing.value = true;
    try {
        dialogMode.value = mode;

        programmer.value = {
            ...createEmptyProgrammer(),
            ...selectedProgrammer,
            season: normalizeSeasonValue(selectedProgrammer.season ?? selectedProgrammer.season_display),
            estimated_delivery_date: normalizeIsoDate(selectedProgrammer.estimated_delivery_date),
        };

        originalProductSheetId.value = selectedProgrammer.product_sheet_id ?? null;
        initializeEcommerceSizes();
        isDialogOpen.value = true;
        clearAlerts();
    } finally {
        isFormInitializing.value = false;
    }
}

defineExpose({ openDialog });

function validateFields() {
    const required = [
        { ok: Boolean(programmer.value.product_sheet_id), message: 'La referencia es obligatoria.' },
        { ok: Boolean(programmer.value.season), message: 'La temporada es obligatoria.' },
        { ok: Boolean(programmer.value.destination_id), message: 'El destino es obligatorio.' },
        { ok: Boolean(programmer.value.estimated_delivery_date), message: 'La fecha estimada de entrega es obligatoria.' },
        {
            ok: programmer.value.units !== null && programmer.value.units !== undefined && Number(programmer.value.units) > 0,
            message: 'Las unidades son obligatorias y deben ser mayores a 0.',
        },
    ];

    for (const item of required) {
        if (!item.ok) {
            setAlert(item.message, 'warning');
            return false;
        }
    }

    clearAlerts();
    return true;
}

function getRequestId() {
    return typeof globalThis.crypto?.randomUUID === 'function'
        ? globalThis.crypto.randomUUID()
        : `${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

async function saveChanges() {
    if (isLoading.value) return;
    if (!validateFields()) return;

    if (!programmer.value.id) {
        setAlert('No se encontro el ID del programador.');
        return;
    }

    if (referenceChanged.value) {
        isDialogOpen.value = false;
        showChangeRefConfirm.value = true;
        return;
    }

    await executeSave();
}

async function executeSave() {
    if (!programmer.value.id) return;

    isLoading.value = true;
    const requestId = getRequestId();

    try {
        const normalizedPriority = normalizePriorityForApi(programmer.value.priority);
        const normalizedEstimatedDeliveryDate = normalizeIsoDate(programmer.value.estimated_delivery_date);

        const basePayload = {
            product_sheet_id: programmer.value.product_sheet_id,
            season: normalizeSeasonForApi(programmer.value.season),
            dispatch_month: normalizeSeasonForApi(programmer.value.dispatch_month ?? programmer.value.season),
            destination_id: programmer.value.destination_id,
            priority: normalizedPriority,
            units: programmer.value.units,
            estimated_delivery_date: normalizedEstimatedDeliveryDate,
            notes: programmer.value.notes ?? null,
            ecommerce_units:
                typeof programmer.value.ecommerce_units === 'string'
                    ? programmer.value.ecommerce_units
                    : JSON.stringify(programmer.value.ecommerce_units || {}),
        };

                const payload: Record<string, unknown> = basePayload;

        const response = await axios.put(`/comercial/programador/${programmer.value.id}`, payload, {
            headers: { 'X-Request-Id': requestId },
        });

        const successText =
            typeof response?.data?.message === 'string' && response.data.message.trim() !== ''
                ? response.data.message.trim()
                : 'Programador actualizado correctamente.';

        emits('programmerUpdated', successText);
        isDialogOpen.value = false;
        clearAlerts();
    } catch (error: unknown) {
        if (axios.isAxiosError(error)) {
            setAlert(error.response?.data?.message || 'Hubo un error al actualizar el programador.');
        } else {
            setAlert('Ocurrio un error inesperado.');
        }
    } finally {
        isLoading.value = false;
    }
}

watch(
    () => [programmer.value.season, programmer.value.priority, isReadOnly.value],
    () => {
        updateEstimatedDeliveryDate();
    },
);
</script>

<template>
    <Dialog v-model:open="isDialogOpen" aria-labelledby="dialog-title" aria-describedby="dialog-description">
        <FormDialogContent class="flex w-[96vw] max-w-[1200px] flex-col overflow-hidden p-0">
            <TooltipProvider>
                <DialogHeader class="flex-shrink-0 border-b px-6 pb-4 pt-6">
                    <DialogTitle>{{ dialogTitle }}</DialogTitle>
                    <DialogDescription>{{ dialogDescription }}</DialogDescription>
                </DialogHeader>

                <AlertStack
                    :success-message="successMessage"
                    :error-message="errorMessage"
                    :error-type="errorType"
                    :success-title="ALERT_TITLES.success"
                    :error-title="errorTitle"
                    class="top-20"
                    @close-success="clearSuccess"
                    @close-error="clearError"
                />

                <div ref="dialogInnerRef" class="relative min-h-0 flex-1 overflow-y-auto px-6 py-4" style="scrollbar-width: thin">
                    <FormLoadingOverlay :active="isFormInitializing" message="Cargando programado..." :delay-ms="100" />

                    <div v-if="canRenderForm" class="space-y-4 pt-2">
                        <section class="rounded-xl border border-border/70 bg-muted/20 p-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <Badge variant="outline">{{ programmer.op_code || 'OP sin código' }}</Badge>
                                <Badge :variant="orderResumeStatusTone">{{ orderResumeStatusText }}</Badge>
                                <Badge variant="outline">Temporada: {{ selectedSeasonLabel }}</Badge>
                            </div>
                        </section>

                        <section>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <!-- Referencia -->
                                <div class="flex flex-col gap-1">
                                    <div class="flex items-center gap-2">
                                        <Label class="text-sm font-medium"> Referencia<span class="text-red-500">*</span> </Label>
                                        <HelpTooltip
                                            text="Sabana de producto asignada a este lote de produccion."
                                            aria-label="Ayuda sobre referencia"
                                        />
                                    </div>
                                    <SelectField
                                        id="reference"
                                        v-model="programmer.product_sheet_id"
                                        :options="productSheetOptions"
                                        label=""
                                        required
                                        :disabled="isReadOnly"
                                        class="mt-1 w-full"
                                    />
                                </div>

                                <!-- Temporada -->
                                <div class="flex flex-col gap-1">
                                    <div class="flex items-center gap-2">
                                        <Label class="text-sm font-medium"> Temporada (Mes Año)<span class="text-red-500">*</span> </Label>
                                        <HelpTooltip
                                            text="Temporada o mes en el que se programara la produccion del lote."
                                            aria-label="Ayuda sobre temporada"
                                        />
                                    </div>

                                    <template v-if="isReadOnly">
                                        <div class="mt-1 rounded-md border border-input bg-muted/50 px-3 py-2 text-sm font-medium">
                                            {{ selectedSeasonLabel }}
                                        </div>
                                    </template>

                                    <template v-else>
                                        <SelectField
                                            id="season"
                                            v-model="programmer.season"
                                            :options="seasonOptions"
                                            label=""
                                            required
                                            class="mt-1 w-full"
                                            @update:modelValue="() => updateEstimatedDeliveryDate()"
                                        />
                                    </template>
                                </div>

                                <!-- Destino -->
                                <div class="flex flex-col gap-1">
                                    <div class="flex items-center gap-2">
                                        <Label class="text-sm font-medium"> Destino<span class="text-red-500">*</span> </Label>
                                        <HelpTooltip text="Canal o mercado destino para el lote programado." aria-label="Ayuda sobre destino" />
                                    </div>
                                    <SelectField
                                        id="destination"
                                        v-model="programmer.destination_id"
                                        :options="destinationOptions"
                                        label=""
                                        required
                                        :disabled="isReadOnly"
                                        class="mt-1 w-full"
                                    />
                                </div>

                                <div class="flex flex-col gap-1">
                                    <div class="flex items-center gap-2">
                                        <Label class="text-sm font-medium">Mes despacho (Mes Año)</Label>
                                    </div>
                                    <SelectField
                                        v-model="programmer.dispatch_month"
                                        :options="seasonOptions"
                                        label=""
                                        :disabled="isReadOnly"
                                        class="mt-1 w-full"
                                    />
                                </div>

                                <!-- Unidades -->
                                <div class="flex flex-col gap-1">
                                    <div class="flex items-center gap-2">
                                        <Label class="text-sm font-medium">Unidades</Label>
                                        <HelpTooltip
                                            text="Cantidad total de unidades a programar. Si se deja vacio, queda pendiente."
                                            aria-label="Ayuda sobre unidades"
                                        />
                                    </div>
                                    <InputField
                                        type="number"
                                        v-model="programmerUnits"
                                        label=""
                                        placeholder="Unds. a programar"
                                        :disabled="isReadOnly"
                                    />
                                </div>

                                <!-- Prioridad -->
                                <div class="flex flex-col gap-1">
                                    <div class="flex items-center gap-2">
                                        <Label class="text-sm font-medium">Prioridad</Label>
                                        <HelpTooltip text="Nivel de prioridad del lote de produccion." aria-label="Ayuda sobre prioridad" />
                                    </div>
                                    <SelectField
                                        id="priority"
                                        v-model="programmer.priority"
                                        :options="priorityOptions"
                                        label=""
                                        :clearable="true"
                                        :disabled="isReadOnly"
                                        class="mt-1 w-full"
                                        @update:modelValue="() => updateEstimatedDeliveryDate()"
                                    />
                                </div>

                                <!-- Fecha entrega estimada (disponible para todos) -->
                                <div class="flex flex-col gap-1">
                                    <div class="flex justify-end">
                                        <HelpTooltip text="Fecha estimada de entrega del lote." aria-label="Ayuda sobre fecha entrega estimada" />
                                    </div>

                                    <DatePickerField
                                        v-model="programmer.estimated_delivery_date"
                                        label="Fecha entrega estimada"
                                        :clearable="true"
                                        :show-today="true"
                                        :disabled="isReadOnly"
                                    />
                                </div>

                                <div class="flex flex-col gap-1 md:col-span-2">
                                    <Label class="text-sm font-medium">Nota</Label>
                                    <InputField
                                        v-model="programmer.notes"
                                        label=""
                                        placeholder="Nota de programación"
                                        :disabled="isReadOnly"
                                    />
                                </div>

                                <div class="hidden md:block" />
                            </div>
                        </section>

                        <!-- E-commerce -->
                        <section class="rounded-xl border border-border/70 bg-background p-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        <Label class="text-sm font-medium">
                                            E-commerce <span class="text-xs text-muted-foreground">(opcional)</span>
                                        </Label>
                                        <HelpTooltip
                                            text="Distribucion de unidades especifica para ventas en linea por talla."
                                            aria-label="Ayuda sobre e-commerce"
                                        />
                                    </div>
                                    <p class="mt-1 text-sm text-muted-foreground">{{ ecommerceStatusText }}</p>
                                </div>

                                <div class="ecommerce-trigger shrink-0">
                                    <EcommerceSizesDialog
                                        :productSheet="getDialogProductSheet(programmer.product_sheet_id)"
                                        :existingValues="resolveEcommerceValues(programmer.ecommerce_units)"
                                        :readonly="isReadOnly"
                                        :trigger-label="ecommerceActionLabel"
                                        @sizesUpdated="
                                            (data) => {
                                                if (!isReadOnly) programmer.ecommerce_units = data.sizes;
                                            }
                                        "
                                    />
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
            </TooltipProvider>

            <DialogFooter class="flex flex-shrink-0 items-center justify-end gap-3 border-t bg-background px-6 py-4">
                <button
                    type="button"
                    class="flex items-center justify-center gap-2 rounded-full border border-primary/40 bg-white px-5 py-2 text-sm font-semibold text-primary transition hover:bg-primary/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                    @click="isDialogOpen = false"
                >
                    {{ isReadOnly ? 'Cerrar' : 'Cancelar' }}
                </button>

                <Button
                    v-if="!isReadOnly"
                    type="button"
                    :class="['rounded-full px-6 py-2 text-sm font-semibold shadow-sm', isLoading ? 'cursor-not-allowed opacity-60' : '']"
                    :disabled="isLoading"
                    @click="saveChanges"
                >
                    {{ isLoading ? 'Guardando...' : 'Guardar' }}
                </Button>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>

    <ConfirmDialog
        :open="showChangeRefConfirm"
        title="¿Cambiar referencia?"
        :description="`Se eliminará la ficha técnica actual (materiales, hilos, insumos y costos) y se importará el listado operacional de ${newReferenceLabel}. Esta acción no se puede deshacer.`"
        confirm-text="Sí, cambiar"
        cancel-text="Cancelar"
        :destructive="false"
        @update:open="(v) => { if (!v) { showChangeRefConfirm = false; if (!confirmingRefChange) isDialogOpen = true; confirmingRefChange = false; } }"
        @confirm="() => { confirmingRefChange = true; showChangeRefConfirm = false; executeSave(); }"
    >
        <template #icon>
            <ArrowLeftRight class="h-5 w-5" />
        </template>
    </ConfirmDialog>

</template>

<style scoped>
.ecommerce-trigger :deep(button) {
    border: 1px solid hsl(var(--border));
    background-color: transparent;
    color: hsl(var(--foreground));
    box-shadow: none;
    font-size: 0.8125rem;
    font-weight: 600;
    padding: 0.45rem 0.95rem;
}

.ecommerce-trigger :deep(button:hover) {
    background-color: hsl(var(--muted));
}
</style>
