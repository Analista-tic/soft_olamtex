<script setup lang="ts">

import { computed, ref, watch } from 'vue';
import axios from 'axios';

import AlertStack from '@/components/common/AlertStack.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import EcommerceSizesDialog from './EcommerceSizesDialog.vue';

import { useAlertStack } from '@/composables/useAlert';
import { normalizeSizes } from '@/domain/Production';
import type { OptionParams, ProductSheet, Programmer } from '@/domain/Production';
import type { Option } from '@/domain/shared/types';

import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import Label from '@/components/ui/label/Label.vue';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { TooltipProvider } from '@/components/ui/tooltip';

import { format, isValid, parse } from 'date-fns';
import { es } from 'date-fns/locale';
import { Calendar as CalendarIcon } from 'lucide-vue-next';
import { getLocalTimeZone, parseDate, type DateValue } from '@internationalized/date';

const props = defineProps<{
    destinations: OptionParams[];
    productSheets: ProductSheet[];
    months: Option[];
}>();

const emits = defineEmits<{
    (event: 'programmerUpdated'): void;
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

const {
    successMessage,
    errorMessage,
    errorType,
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
    const typed = sheet as ProductSheet & { label?: string; display_reference?: string };
    return String(typed.label ?? typed.display_reference ?? sheet.reference ?? '').trim();
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

const dialogInnerRef = ref<HTMLElement | null>(null);
const estimatedDatePopoverOpen = ref(false);
const estimatedDateText = ref('');

const isReadOnly = computed(() => dialogMode.value === 'view');

function isAutoRow(row: EditableProgrammer) {
    return row.is_auto_from_product_sheet === true;
}

const isAutoGenerated = computed(() => isAutoRow(programmer.value));
const isManual = computed(() => !isAutoGenerated.value);

const dialogTitle = computed(() =>
    isReadOnly.value ? 'Ver Lote Programado' : 'Editar Lote Programado',
);

const dialogDescription = computed(() =>
    isReadOnly.value
        ? 'Aqui puedes ver los detalles del programador seleccionado.'
        : 'Aqui puedes editar los detalles del programador seleccionado.',
);

const destinationOptions = computed<SelectOption[]>(() =>
    (props.destinations ?? [])
        .filter((d) => typeof d.id === 'number' || typeof d.id === 'string')
        .map((d) => ({ id: d.id as string | number, name: d.name })),
);

const monthSelectOptions = computed<SelectOption[]>(() =>
    (props.months ?? [])
        .filter((m) => typeof m.id === 'number' || typeof m.id === 'string')
        .map((m) => ({ id: m.id as string | number, name: m.name })),
);

const seasonOptions = computed<SelectOption[]>(() =>
    (props.months ?? []).map((m) => {
        const name = String(m.name).toUpperCase();
        return { id: name, name };
    }),
);

const priorityOptions = computed<SelectOption[]>(() =>
    PRIORITY_VALUES.map((v) => ({ id: v, name: v })),
);

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

const selectedSeasonLabel = computed(() => normalizeSeasonForApi(programmer.value.season) || 'Sin temporada');

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

const calendarValue = computed<DateValue | undefined>({
    get() {
        const iso = normalizeIsoDate(programmer.value.estimated_delivery_date);
        if (!iso) return undefined;
        try {
            return parseDate(iso);
        } catch {
            return undefined;
        }
    },
    set(v) {
        programmer.value.estimated_delivery_date = v
            ? format(v.toDate(getLocalTimeZone()), 'yyyy-MM-dd')
            : null;
    },
});

const estimatedDeliveryDateLabel = computed(() => {
    const v = calendarValue.value;
    if (!v) return 'dd/mm/aaaa';
    try {
        return format(v.toDate(getLocalTimeZone()), 'dd/MM/yyyy', { locale: es });
    } catch {
        return 'dd/mm/aaaa';
    }
});

watch(
    () => programmer.value.estimated_delivery_date,
    (val) => {
        const iso = normalizeIsoDate(val);
        if (!iso) {
            estimatedDateText.value = '';
            return;
        }

        try {
            estimatedDateText.value = format(parseDate(iso).toDate(getLocalTimeZone()), 'dd/MM/yyyy', { locale: es });
        } catch {
            estimatedDateText.value = '';
        }
    },
    { immediate: true },
);

function commitEstimatedDateText() {
    const text = estimatedDateText.value.trim();

    if (text === '') {
        programmer.value.estimated_delivery_date = null;
        return;
    }

    const parsedInput = parse(text, 'dd/MM/yyyy', new Date());
    if (isValid(parsedInput)) {
        programmer.value.estimated_delivery_date = format(parsedInput, 'yyyy-MM-dd');
        return;
    }

    const iso = normalizeIsoDate(text);
    if (iso) {
        programmer.value.estimated_delivery_date = iso;
        return;
    }

    setAlert('Formato inválido. Usa dd/mm/aaaa', 'warning');
}

watch(estimatedDatePopoverOpen, (open) => {
    if (!open) commitEstimatedDateText();
});

function handleEstimatedDateSelect(value: DateValue | undefined) {
    calendarValue.value = value;
    estimatedDateText.value = value
        ? format(value.toDate(getLocalTimeZone()), 'dd/MM/yyyy', { locale: es })
        : '';
    if (value) estimatedDatePopoverOpen.value = false;
}

function clearEstimatedDate() {
    programmer.value.estimated_delivery_date = null;
    estimatedDatePopoverOpen.value = false;
}

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

function openDialog(selectedProgrammer: Programmer, mode: 'edit' | 'view' = 'edit') {
    dialogMode.value = mode;

    programmer.value = {
        ...createEmptyProgrammer(),
        ...selectedProgrammer,
        season: normalizeSeasonValue(selectedProgrammer.season),
        estimated_delivery_date: normalizeIsoDate(selectedProgrammer.estimated_delivery_date),
    };

    estimatedDatePopoverOpen.value = false;
    initializeEcommerceSizes();
    isDialogOpen.value = true;
    clearAlerts();
}

defineExpose({ openDialog });

function validateFields() {
    const required = [
        { ok: Boolean(programmer.value.product_sheet_id), message: 'La referencia es obligatoria.' },
        { ok: Boolean(programmer.value.season), message: 'La temporada es obligatoria.' },
        { ok: Boolean(programmer.value.destination_id), message: 'El destino es obligatorio.' },
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

    isLoading.value = true;
    const requestId = getRequestId();

    try {
        const basePayload = {
            product_sheet_id: programmer.value.product_sheet_id,
            season: normalizeSeasonForApi(programmer.value.season),
            destination_id: programmer.value.destination_id,
            units: programmer.value.units,
            ecommerce_units:
                typeof programmer.value.ecommerce_units === 'string'
                    ? programmer.value.ecommerce_units
                    : JSON.stringify(programmer.value.ecommerce_units || {}),
        };

        const payload: Record<string, unknown> = isAutoGenerated.value
            ? {
                  ...basePayload,
                  estimated_delivery_date: programmer.value.estimated_delivery_date,
              }
            : {
                  ...basePayload,
                  priority: normalizePriorityForApi(programmer.value.priority),
              };

        await axios.put(`/comercial/programador/${programmer.value.id}`, payload, {
            headers: { 'X-Request-Id': requestId },
        });

        emits('programmerUpdated');
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
</script>

<template>
    <Dialog v-model:open="isDialogOpen" aria-labelledby="dialog-title" aria-describedby="dialog-description">
        <FormDialogContent class="flex max-h-[85vh] max-w-[1050px] flex-col px-2 py-2">
            <TooltipProvider>
                <DialogHeader class="flex-shrink-0 px-6 pt-6 pb-4">
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

                <div ref="dialogInnerRef" class="flex-1 overflow-y-auto px-6 pb-24" style="scrollbar-width: thin">
                    <div class="space-y-4 pt-2">
                        <section>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <!-- Referencia -->
                                <div class="flex flex-col gap-1">
                                    <div class="flex items-center gap-2">
                                        <Label class="text-sm font-medium">
                                            Referencia<span class="text-red-500">*</span>
                                        </Label>
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
                                        <Label class="text-sm font-medium">
                                            Temporada<span class="text-red-500">*</span>
                                        </Label>
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
                                        />
                                    </template>
                                </div>

                                <!-- Destino -->
                                <div class="flex flex-col gap-1">
                                    <div class="flex items-center gap-2">
                                        <Label class="text-sm font-medium">
                                            Destino<span class="text-red-500">*</span>
                                        </Label>
                                        <HelpTooltip
                                            text="Canal o mercado destino para el lote programado."
                                            aria-label="Ayuda sobre destino"
                                        />
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

                                <!-- Prioridad / Fecha estimada -->
                                <div v-if="isManual" class="flex flex-col gap-1">
                                    <div class="flex items-center gap-2">
                                        <Label class="text-sm font-medium">Prioridad</Label>
                                        <HelpTooltip
                                            text="Nivel de prioridad del lote de produccion."
                                            aria-label="Ayuda sobre prioridad"
                                        />
                                    </div>
                                    <SelectField
                                        id="priority"
                                        v-model="programmer.priority"
                                        :options="priorityOptions"
                                        label=""
                                        :disabled="isReadOnly"
                                        class="mt-1 w-full"
                                    />
                                </div>

                                <div v-else class="flex flex-col gap-1">
                                    <div class="flex items-center gap-2">
                                        <Label class="text-sm font-medium">Fecha entrega estimada</Label>
                                        <HelpTooltip
                                            text="Fecha estimada de entrega de la muestra."
                                            aria-label="Ayuda sobre fecha entrega estimada"
                                        />
                                    </div>

                                    <div class="mt-1 flex items-center gap-2">
                                        <Input
                                            v-model="estimatedDateText"
                                            type="text"
                                            placeholder="dd/mm/aaaa"
                                            class="h-10"
                                            :disabled="isReadOnly"
                                            :title="estimatedDeliveryDateLabel"
                                            @keydown.enter.prevent="commitEstimatedDateText"
                                        />

                                        <Popover v-model:open="estimatedDatePopoverOpen" :modal="false">
                                            <PopoverTrigger as-child>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="icon"
                                                    class="h-10 w-10 shrink-0"
                                                    :disabled="isReadOnly"
                                                    aria-label="Abrir calendario"
                                                >
                                                    <CalendarIcon class="h-4 w-4" />
                                                </Button>
                                            </PopoverTrigger>

                                            <PopoverContent class="z-[9999] pointer-events-auto w-auto p-0" align="end">
                                                <Calendar
                                                    :model-value="calendarValue"
                                                    @update:model-value="handleEstimatedDateSelect"
                                                    caption-layout="dropdown"
                                                />
                                                <div v-if="!isReadOnly" class="border-t p-2">
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        class="w-full justify-center"
                                                        :disabled="!programmer.estimated_delivery_date"
                                                        @click="clearEstimatedDate"
                                                    >
                                                        Limpiar fecha
                                                    </Button>
                                                </div>
                                            </PopoverContent>
                                        </Popover>
                                    </div>
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

            <DialogFooter class="mt-4 flex items-center justify-end gap-3 border-t px-6 pt-4 pb-6">
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
                    :class="[
                        'rounded-full px-6 py-2 text-sm font-semibold shadow-sm',
                        isLoading ? 'cursor-not-allowed opacity-60' : '',
                    ]"
                    :disabled="isLoading"
                    @click="saveChanges"
                >
                    {{ isLoading ? 'Guardando...' : 'Guardar' }}
                </Button>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
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