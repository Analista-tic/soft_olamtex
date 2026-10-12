/**
 * Composable: useProductSheetForm
 *
 * Encapsula el estado del formulario de ProductSheet, sus tipos, constantes,
 * funciones de normalización, y toda la lógica de construcción de payload para
 * crear/actualizar una sábana.
 */
import type { ProductSheet } from '@/domain/Production';
import {
    PRODUCT_SHEET_TYPE_PRODUCT,
    PRODUCT_SHEET_TYPE_SAMPLE,
    normalizeProductSheetType,
    parseBooleanRecord,
    stringifyJsonRecord,
} from '@/domain/Production';
import { computed, ref, watch, type Ref } from 'vue';

// ─── Tipos ────────────────────────────────────────────────────────────────────

export interface FormState {
    id?: number;
    reference: string;
    section_id: number | null;
    group_id: number | null;
    brand_id: number | null;
    world_id: number | null;
    fabric_base_id: number | null;
    yarn_base_id: number | null;
    type: 'muestra' | 'producto';
    operational_list_id: number | null;
    collection_id: number | null;
    billing_price: number;
    selling_price: number;
    sizes: string;
    woven_product: boolean;
    description: string;
    flat_sketch_path: string;
    sample_reference: string;
    product_reference: string;
    product_reference_2: string;
    processes: string;
    status: 'incomplete' | 'complete';
    version: number;
    parent_id: number | null;
}

export interface ProductSheetDialogOption {
    id: number | string;
    reference: string;
    type: string;
    isTemporary?: boolean;
}

// ─── Constantes ───────────────────────────────────────────────────────────────

export const TYPE_PRODUCT = PRODUCT_SHEET_TYPE_PRODUCT;
export const TYPE_SAMPLE = PRODUCT_SHEET_TYPE_SAMPLE;

export const TYPE_OPTIONS = [
    { value: TYPE_PRODUCT, label: 'Producto' },
    { value: TYPE_SAMPLE, label: 'Muestra' },
] as const;

export const TOOLTIPS = {
    type: 'Define si esta referencia es una muestra (desarrollo/prueba) o un producto listo para produccion y venta.',
    section: 'Indica el tipo de prenda al que pertenece la referencia.',
    group: 'Clasifica la referencia según su forma de producción: líneas continuas, lotes periodicos o desarrollos específicos.',
    world: 'Define el publico o tipo de mercado al que va dirigida la referencia.',
    wovenProduct: 'Indica si la prenda se fabrica a partir de tejido.',
    base: 'Material base principal de la prenda.',
    yarnBase: 'Define la hilaza base usada cuando la prenda se fabrica en tejido.',
    operationalList: 'Define las operaciones detalladas necesarias para el desarrollo o fabricacion de esta referencia.',
    processes: 'Etapas que seguira la referencia para el control y seguimiento de los lotes.',
    flatSketch: 'Boceto técnico de la referencia con medidas, detalles y observaciones para producción.',
} as const;

export const SIZE_GROUPS = {
    tallas_1: ['XS', 'S', 'M', 'L', 'XL', '2XL', 'U'],
    tallas_2: ['2', '3', '4', '5', '6', '8', '10', '12', '14', '16'],
    tallas_3: ['28', '30', '32', '34', '36', '38'],
} as const;

const DEVELOPMENT_PROCESS_LABEL = 'Desarrollo de muestras';
const DEVELOPMENT_PROCESS_ALIASES = ['Desarrollo de muestra', 'Desarrollo de Muestra', 'Desarrollo de Muestras'];
const PROCESS_ALIASES: Record<string, string> = {
    Bordado: 'Estampado',
    Estampando: 'Estampado',
    Sublimado: 'Estampado',
};
const MANDATORY_PROCESSES = ['Muestras', 'Confección', 'Terminación', 'Bodega'];

// ─── Helpers puros ────────────────────────────────────────────────────────────

export function toIdOrNull(value: unknown): number | null {
    const n = Number(value);
    return Number.isFinite(n) && n > 0 ? n : null;
}

export function normalizeUpperText(value: unknown): string {
    const text = typeof value === 'string' ? value : String(value ?? '');
    return text.trim().toLocaleUpperCase('es-CO');
}

export function normalizeBooleanInput(value: unknown): boolean {
    if (Array.isArray(value)) return normalizeBooleanInput(value[0]);
    if (typeof value === 'boolean') return value;
    if (typeof value === 'number') return value === 1;
    if (typeof value === 'string') {
        return ['1', 'true', 'on', 'yes', 'si', 'sí'].includes(value.trim().toLowerCase());
    }
    return Boolean(value);
}

function defaultSizesJson(): string {
    return JSON.stringify({
        XS: false, S: false, M: false, L: false, XL: false, '2XL': false, U: false,
        '2': false, '3': false, '4': false, '5': false, '6': false,
        '8': false, '10': false, '12': false, '14': false, '16': false,
        '28': false, '30': false, '32': false, '34': false, '36': false, '38': false,
    });
}

export function ensureSizesJson(value: unknown): string {
    if (value == null || value === '') return '{}';
    if (typeof value === 'string') {
        const trimmed = value.trim();
        if (!trimmed) return '{}';
        try {
            const parsed = JSON.parse(trimmed);
            if (parsed && typeof parsed === 'object') return JSON.stringify(parsed);
        } catch { return '{}'; }
    }
    if (Array.isArray(value)) {
        return JSON.stringify(Object.fromEntries(value.map((s) => [String(s), true])));
    }
    if (typeof value === 'object') {
        try { return JSON.stringify(value); } catch { return '{}'; }
    }
    return '{}';
}

export function createInitialFormState(): FormState {
    return {
        reference: '',
        section_id: null,
        group_id: null,
        brand_id: null,
        world_id: null,
        fabric_base_id: null,
        yarn_base_id: null,
        type: TYPE_SAMPLE,
        operational_list_id: null,
        collection_id: null,
        billing_price: 0,
        selling_price: 0,
        sizes: defaultSizesJson(),
        woven_product: false,
        description: '',
        flat_sketch_path: '',
        sample_reference: '',
        product_reference: '',
        product_reference_2: '',
        processes: '',
        status: 'incomplete',
        version: 1,
        parent_id: null,
    };
}

// ─── Procesos ─────────────────────────────────────────────────────────────────

export const ALL_PROCESSES = [
    'I&D', 'Tejido', 'Corte', 'Logística', 'Estampado',
    'Desarrollo de muestras', 'Muestras', 'Confección', 'Lavandería',
    'Terminación', 'Inspección y aprobación', 'Bodega',
];

export function parseProcessesValue(value: unknown, allProcesses: string[]): Record<string, boolean> {
    const parsed = parseBooleanRecord(value ?? null);

    for (const alias of DEVELOPMENT_PROCESS_ALIASES) {
        if (parsed[alias]) {
            parsed[DEVELOPMENT_PROCESS_LABEL] = true;
            delete parsed[alias];
        }
    }

    for (const [legacy, current] of Object.entries(PROCESS_ALIASES)) {
        if (parsed[legacy]) {
            parsed[current] = true;
            delete parsed[legacy];
        }
    }

    const allowed = new Set(allProcesses);
    for (const key of Object.keys(parsed)) {
        if (!allowed.has(key)) delete parsed[key];
    }

    for (const process of MANDATORY_PROCESSES) {
        parsed[process] = true;
    }

    return parsed;
}

export function orderProcesses(processes: Record<string, boolean>, allProcesses: string[]): Record<string, boolean> {
    const ordered = allProcesses.reduce<Record<string, boolean>>((acc, process) => {
        if (processes[process]) acc[process] = true;
        return acc;
    }, {});

    for (const process of MANDATORY_PROCESSES) {
        ordered[process] = true;
    }

    return ordered;
}

// ─── Composable ───────────────────────────────────────────────────────────────

interface UseProductSheetFormParams {
    mode: Ref<'create' | 'edit' | 'view'>;
    productSheet: Ref<ProductSheet | undefined>;
    readonly: Ref<boolean>;
}

export function useProductSheetForm(params: UseProductSheetFormParams) {
    const { mode, productSheet } = params;

    const form = ref<FormState>(createInitialFormState());
    const allProcesses = ref<string[]>([...ALL_PROCESSES]);
    const hasAppliedDefaultSampleProcess = ref(false);

    // ── Modo ──────────────────────────────────────────────────────────────────
    const isCreateMode = computed(() => mode.value === 'create');
    const isEditMode = computed(() => mode.value === 'edit');
    const isViewMode = computed(() => mode.value === 'view');
    const isReadonly = computed(() => params.readonly.value || isViewMode.value);

    // ── Tipo ──────────────────────────────────────────────────────────────────
    const normalizeTypeValue = (value?: string) => normalizeProductSheetType(value);

    const typeModel = computed({
        get: () => normalizeTypeValue(form.value.type),
        set: (value: string) => { form.value.type = normalizeTypeValue(value); },
    });

    const isTypeReadonly = computed(
        () => isReadonly.value || (isEditMode.value && normalizeTypeValue(productSheet.value?.type) === TYPE_PRODUCT),
    );

    const showConvertButton = computed(
        () => isEditMode.value
            && normalizeTypeValue(form.value.type) === TYPE_SAMPLE
            && !productSheet.value?.has_derived_product,
    );

    // ── Referencias ───────────────────────────────────────────────────────────
    const activeReference = computed<string>({
        get() {
            if (form.value.type === TYPE_SAMPLE) {
                return form.value.sample_reference || form.value.product_reference || '';
            }
            return form.value.product_reference || form.value.sample_reference || '';
        },
        set(value: string) {
            const normalized = normalizeUpperText(value);
            form.value.reference = normalized;
            if (form.value.type === TYPE_SAMPLE) {
                form.value.sample_reference = normalized;
            } else {
                form.value.product_reference = normalized;
            }
        },
    });

    // ── Tallas ────────────────────────────────────────────────────────────────
    const parsedSizes = computed<Record<string, boolean>>(() => parseBooleanRecord(form.value.sizes));

    // ── Procesos ──────────────────────────────────────────────────────────────
    function normalizeProcessName(value: string): string {
        return value.trim().replace(/\s+/g, ' ');
    }

    function normalizeProcessesForForm(value: unknown): string {
        return JSON.stringify(orderProcesses(parseProcessesValue(value, allProcesses.value), allProcesses.value));
    }

    function ensureValidProcessesJson(value: string | undefined): string {
        return JSON.stringify(orderProcesses(parseProcessesValue(value, allProcesses.value), allProcesses.value));
    }

    function applyDefaultSampleProcessOnce() {
        if (hasAppliedDefaultSampleProcess.value) return;
        if (!isCreateMode.value) return;
        if (form.value.type !== TYPE_SAMPLE) return;

        const current = parseProcessesValue(form.value.processes, allProcesses.value);
        const isEmpty = !form.value.processes || Object.keys(current).length === 0;

        if (isEmpty) {
            form.value.processes = JSON.stringify(
                orderProcesses({ [DEVELOPMENT_PROCESS_LABEL]: true }, allProcesses.value),
            );
        }

        hasAppliedDefaultSampleProcess.value = true;
    }

    // ── Watches ───────────────────────────────────────────────────────────────
    watch(
        () => form.value.type,
        (type) => {
            form.value.reference = type === TYPE_SAMPLE
                ? (form.value.sample_reference ?? '')
                : (form.value.product_reference ?? '');
            applyDefaultSampleProcessOnce();
        },
        { immediate: true },
    );

    const makeUpperWatch = (getter: () => string, setter: (v: string) => void) => {
        watch(getter, (value) => {
            if (typeof value !== 'string') return;
            const normalized = normalizeUpperText(value);
            if (value !== normalized) setter(normalized);
        });
    };

    makeUpperWatch(() => form.value.sample_reference, (v) => { form.value.sample_reference = v; });
    makeUpperWatch(() => form.value.product_reference, (v) => { form.value.product_reference = v; });
    makeUpperWatch(() => form.value.product_reference_2, (v) => { form.value.product_reference_2 = v; });

    watch(
        () => form.value.woven_product,
        (isWoven) => {
            const processes = parseBooleanRecord(form.value.processes);
            if (isWoven) {
                if (!processes['Tejido']) processes['Tejido'] = true;
            } else {
                delete processes['Tejido'];
                form.value.yarn_base_id = null;
                if (!form.value.fabric_base_id || Number(form.value.fabric_base_id) === 0) {
                    form.value.fabric_base_id = null;
                }
            }
            form.value.processes = stringifyJsonRecord(processes);
        },
    );

    // ── Hidratar formulario desde prop ────────────────────────────────────────
    function hydrateFromProductSheet(sheet: ProductSheet): void {
        form.value = {
            ...createInitialFormState(),
            id: sheet.id,
            section_id: toIdOrNull(sheet.section_id),
            group_id: toIdOrNull(sheet.group_id),
            brand_id: toIdOrNull(sheet.brand_id),
            world_id: toIdOrNull(sheet.world_id),
            fabric_base_id: toIdOrNull(sheet.fabric_base_id),
            yarn_base_id: toIdOrNull(sheet.yarn_base_id),
            type: normalizeTypeValue(sheet.type),
            operational_list_id: toIdOrNull(sheet.operational_list_id),
            collection_id: toIdOrNull(sheet.collection_id),
            billing_price: Number(sheet.billing_price ?? 0),
            selling_price: Number(sheet.selling_price ?? 0),
            sizes: ensureSizesJson(sheet.sizes),
            woven_product: Boolean(sheet.woven_product),
            description: String(sheet.description ?? ''),
            flat_sketch_path: String(sheet.flat_sketch_path ?? ''),
            sample_reference: normalizeUpperText(sheet.sample_reference ?? ''),
            product_reference: normalizeUpperText(sheet.product_reference ?? ''),
            product_reference_2: normalizeUpperText(
                sheet.product_reference_2 ?? (sheet as Record<string, unknown>).export_reference ?? '',
            ),
            processes: normalizeProcessesForForm(sheet.processes),
            status: sheet.status === 'complete' ? 'complete' : 'incomplete',
            version: Number(sheet.version ?? 1),
            parent_id: sheet.parent_id ?? null,
        };
        form.value.reference = form.value.type === TYPE_SAMPLE
            ? form.value.sample_reference
            : form.value.product_reference;
    }

    // ── Payload ───────────────────────────────────────────────────────────────
    function buildBasePayload(): Record<string, unknown> {
        const wovenProduct = normalizeBooleanInput(form.value.woven_product);

        return {
            type: form.value.type ?? TYPE_PRODUCT,
            section_id: toIdOrNull(form.value.section_id),
            group_id: toIdOrNull(form.value.group_id),
            brand_id: toIdOrNull(form.value.brand_id),
            world_id: toIdOrNull(form.value.world_id),
            fabric_base_id: toIdOrNull(form.value.fabric_base_id),
            yarn_base_id: wovenProduct ? toIdOrNull(form.value.yarn_base_id) : null,
            billing_price: form.value.billing_price ?? 0,
            selling_price: form.value.selling_price ?? 0,
            sizes: ensureSizesJson(form.value.sizes),
            woven_product: wovenProduct ? 1 : 0,
            description: form.value.description ?? '',
            processes: ensureValidProcessesJson(form.value.processes),
            status: form.value.status ?? 'incomplete',
            operational_list_id: toIdOrNull(form.value.operational_list_id),
            collection_id: toIdOrNull(form.value.collection_id),
            sample_reference: normalizeUpperText(form.value.sample_reference) || null,
            product_reference: normalizeUpperText(form.value.product_reference) || null,
            product_reference_2: normalizeUpperText(form.value.product_reference_2) || null,
            version: form.value.version ?? 1,
            parent_id: form.value.parent_id ?? null,
        };
    }

    function buildMultipartPayload(basePayload: Record<string, unknown>): FormData {
        const fd = new FormData();
        Object.entries(basePayload).forEach(([key, value]) => {
            if (value !== undefined && value !== null) fd.append(key, String(value));
        });
        return fd;
    }

    // ── Status ────────────────────────────────────────────────────────────────
    // El estado lo decide el servidor al guardar; aquí solo se conserva el que ya tenía la ficha.
    function determineStatus(): void {
        form.value.status = form.value.status === 'complete' ? 'complete' : 'incomplete';
    }

    // ── Reset ─────────────────────────────────────────────────────────────────
    function resetForm() {
        hasAppliedDefaultSampleProcess.value = false;
        form.value = createInitialFormState();
    }

    return {
        // Estado
        form,
        allProcesses,

        // Computados de modo
        isCreateMode,
        isEditMode,
        isViewMode,
        isReadonly,

        // Tipo
        typeModel,
        isTypeReadonly,
        showConvertButton,
        normalizeTypeValue,

        // Referencias
        activeReference,

        // Tallas
        parsedSizes,

        // Procesos
        normalizeProcessName,
        normalizeProcessesForForm,
        applyDefaultSampleProcessOnce,

        // Formulario
        hydrateFromProductSheet,
        buildBasePayload,
        buildMultipartPayload,
        determineStatus,
        resetForm,
    };
}
