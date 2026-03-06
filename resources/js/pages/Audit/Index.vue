<script setup lang="ts">
import FilterBar from '@/components/FilterBar.vue';
import DatePickerField from '@/components/DatePickerField.vue';
import FilterField from '@/components/FilterField.vue';
import InputDate from '@/components/InputDate.vue';
import SelectField from '@/components/SelectField.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import {
    Pagination,
    PaginationEllipsis,
    PaginationFirst,
    PaginationLast,
    PaginationList,
    PaginationListItem,
    PaginationNext,
    PaginationPrev,
} from '@/components/ui/pagination';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';
import type { BreadcrumbItem, PaginatedData, SharedData } from '@/domain/shared';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { Eye } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref, watch } from 'vue';

interface AuditLog {
    id: number;
    user_id: number | null;
    user_name: string | null;
    action: string;
    auditable_type: string | null;
    auditable_id: string | number | null;
    module: string | null;
    module_label?: string;
    resource_label?: string;
    company_id?: number | null;
    old_values?: Record<string, unknown> | null;
    new_values?: Record<string, unknown> | null;
    created_at: string | null;
    user_area?: string | null;
    user_role?: string | null;
    [key: string]: any;
}
interface AuditPageProps extends SharedData {
    logs: PaginatedData<AuditLog>;
    filters: {
        user?: string | null;
        module?: string | null;
        action?: string | null;
        start_date?: string | null;
        end_date?: string | null;
    };
}

interface DiffEntry {
    key: string;
    oldValue: string;
    newValue: string;
    oldImageUrl: string | null;
    newImageUrl: string | null;
}

const props = defineProps<AuditPageProps>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Auditoría',
        href: route('auditoria.index'),
    },
];

const initialDate = props.filters.start_date ?? props.filters.end_date ?? '';
const filters = reactive({
    user: props.filters.user ?? '',
    module: props.filters.module ?? '',
    action: props.filters.action ?? null,
    date: initialDate,
    area: '',
});
// Opciones de área
type AreaOption = { id: number | string; name: string };
type NumericAreaOption = { id: number; name: string };
const areaOptions = ref<AreaOption[]>([]);
const areaSelectOptions = computed(() => [{ id: '', name: 'Todas las áreas' }, ...areaOptions.value]);
const selectedAreaId = ref(filters.area ?? '');
const isAreaFilterActive = computed(() => selectedAreaId.value !== '' && selectedAreaId.value !== null);
const areaFilterButtonClasses = useFilterButtonClasses(isAreaFilterActive);

function normalizeAreas(payload: unknown): AreaOption[] {
    if (typeof payload === 'string') {
        try {
            return normalizeAreas(JSON.parse(payload));
        } catch {
            return [];
        }
    }
    if (!Array.isArray(payload)) {
        if (payload && typeof payload === 'object') {
            const record = payload as Record<string, unknown>;
            if ('data' in record) return normalizeAreas(record.data);
            if ('valor' in record) return normalizeAreas(record.valor);
        }
        return [];
    }
    return payload
        .map((area) => {
            if (area && typeof area === 'object') {
                const record = area as Record<string, unknown>;
                const name = String(record.name ?? record.nombre ?? record.label ?? '').trim();
                if (!name) return null;
                const rawId = record.id;
                const parsedId = typeof rawId === 'number' ? rawId : Number(rawId);
                if (!Number.isFinite(parsedId)) return null;
                return { id: parsedId, name };
            }
            return null;
        })
        .filter((area): area is NumericAreaOption => Boolean(area));
}

async function fetchAreas() {
    try {
        const { data } = await axios.get('/api/parameters/areas');
        areaOptions.value = normalizeAreas(data);
    } catch (error) {
        console.error('Error al cargar áreas:', error);
        areaOptions.value = [];
    }
}

onMounted(() => {
    fetchAreas();
});

const actionOptions = [
    { id: 'all', value: null, label: 'Todos' },
    { id: 'created', value: 'created', label: 'Creado' },
    { id: 'updated', value: 'updated', label: 'Actualizado' },
    { id: 'deleted', value: 'deleted', label: 'Eliminado' },
];

// SelectField expects { id, name }
const actionOptionsForSelect = actionOptions.map((o) => ({ id: String(o.value ?? ''), name: o.label }));

const selectAction = ref<string | null>(filters.action ?? null);
const selectModule = ref<string | null>(filters.module ?? '');

const moduleFilterActive = computed(() => Boolean(selectModule.value?.trim()));
const actionFilterActive = computed(() => Boolean(selectAction.value));
const dateFilterActive = computed(() => Boolean(filters.date?.trim()));
const dateFilterModel = computed<string | null>({
    get: () => filters.date || null,
    set: (value) => {
        filters.date = value ?? '';
    },
});
const moduleButtonClasses = useFilterButtonClasses(moduleFilterActive);
const actionButtonClasses = useFilterButtonClasses(actionFilterActive);

const logs = ref<AuditLog[]>(props.logs?.data ?? []);
const totalItems = ref(props.logs?.total ?? 0);
const itemsPerPage = ref(props.logs?.per_page ?? 50);
const lastPage = ref(props.logs?.last_page ?? 1);
const currentPage = ref(props.logs?.current_page ?? 1);

const moduleCandidates = ref(new Map<string, string>());

function addModuleCandidates(payload: AuditLog[]) {
    const next = new Map(moduleCandidates.value);
    for (const log of payload) {
        const value = log.module ?? 'General';
        const label = log.module_label ?? value;
        if (!next.has(value)) {
            next.set(value, label);
        }
    }
    moduleCandidates.value = next;
}

addModuleCandidates(logs.value);

const moduleOptions = computed(() => {
    const arr = Array.from(moduleCandidates.value.entries()).map(([value, label]) => ({
        id: value,
        name: label,
    }));
    arr.sort((a, b) => a.name.localeCompare(b.name, 'es'));
    return [{ id: '', name: 'Todos los módulos' }, ...arr];
});

const diffCache = new Map<number, DiffEntry[]>();
const detailDialogOpen = ref(false);
const detailLog = ref<AuditLog | null>(null);
const detailDiffEntries = ref<DiffEntry[]>([]);

async function fetchLogs(page = 1) {
    const params: Record<string, unknown> = {
        page,
        per_page: itemsPerPage.value,
    };
    const userFilter = filters.user.trim();
    if (userFilter) {
        params.user = userFilter;
    }
    const moduleFilter = filters.module.trim();
    if (moduleFilter) {
        params.module = moduleFilter;
    }
    if (filters.action) {
        params.action = filters.action;
    }
    const selectedDate = filters.date?.trim();
    if (selectedDate) {
        params.start_date = selectedDate;
        params.end_date = selectedDate;
    }
    if (filters.area && filters.area !== '') {
        params.area_id = filters.area;
    }

    try {
        const response = await axios.get(route('auditoria.data'), { params });
        const data = response.data ?? {};
        const fetched = data.data ?? [];
        logs.value = fetched;
        addModuleCandidates(fetched);
        totalItems.value = data.total ?? 0;
        currentPage.value = data.current_page ?? page;
        lastPage.value = data.last_page ?? 1;
        itemsPerPage.value = data.per_page ?? itemsPerPage.value;
    } catch (error) {
        console.error('Error fetching audit logs', error);
    }
}

watch(selectModule, (value) => {
    const normalized = value ?? '';
    const previous = filters.module;
    filters.module = normalized;
    if (normalized !== previous) {
        fetchLogs(1);
    }
});

watch(selectAction, (value) => {
    const normalized = value && value !== '' ? (value as string) : null;
    const previous = filters.action;
    filters.action = normalized;
    if (normalized !== previous) {
        fetchLogs(1);
    }
});

watch(
    () => filters.user,
    (value, oldValue) => {
        if (value === oldValue) {
            return;
        }
        fetchLogs(1);
    },
);

watch(
    () => filters.date,
    (value, oldValue) => {
        if (value === oldValue) {
            return;
        }
        fetchLogs(1);
    },
);

watch(selectedAreaId, (value) => {
    const normalized = value ?? '';
    const previous = filters.area;
    filters.area = normalized;
    if (normalized !== previous) {
        fetchLogs(1);
    }
});

watch(logs, () => {
    diffCache.clear();
});

function handlePageChange(page: number) {
    if (!page || page === currentPage.value) {
        return;
    }
    currentPage.value = page;
    fetchLogs(page);
}

const dateFormatter = new Intl.DateTimeFormat('es-CO', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
});

const actionLabels: Record<string, string> = {
    created: 'Creado',
    updated: 'Actualizado',
    deleted: 'Eliminado',
};

const actionVerbMap: Record<string, string> = {
    created: 'creó',
    updated: 'actualizó',
    deleted: 'eliminó',
};

const actionGradientClasses: Record<string, string> = {
    // Creado -> Verde
    created: 'bg-gradient-to-r from-emerald-100 via-emerald-50 to-emerald-100 border-emerald-200 text-emerald-700',
    // Actualizado -> Azul
    updated: 'bg-gradient-to-r from-sky-100 via-sky-50 to-sky-100 border-sky-200 text-sky-700',
    // Eliminado -> Rojo
    deleted: 'bg-gradient-to-r from-rose-100 via-rose-50 to-rose-100 border-rose-200 text-rose-700',
};

const fieldLabelTranslations: Record<string, string> = {
    action: 'Acción',
    barcode: 'Código de barras',
    category_sub_group_id: 'Subgrupo',
    code: 'Código',
    company_id: 'Empresa',
    contact_id: 'Contacto',
    description: 'Descripción',
    default_value: 'Valor por defecto',
    field_type: 'Tipo de campo',
    extra_fields: 'Campos adicionales',
    group_id: 'Grupo',
    id: 'ID',
    is_required: 'Requerido',
    item_type: 'Tipo de ítem',
    module: 'Módulo',
    name: 'Nombre',
    negative_movement: 'Movimiento negativo',
    options: 'Opciones',
    position: 'Posición',
    reference: 'Referencia',
    required: 'Requerido',
    sku: 'SKU',
    status: 'Estado',
    tax_id: 'Impuesto',
    unit_measure: 'Unidad de medida',
};

function humanizeFieldKey(key: string): string {
    if (!key) return '';
    const spaced = key.replace(/([a-z])([A-Z])/g, '$1 $2').replace(/_/g, ' ');
    return spaced
        .split(/\s+/)
        .filter(Boolean)
        .map((segment) => segment.charAt(0).toUpperCase() + segment.slice(1))
        .join(' ');
}

function translateDiffLabel(key: string): string {
    return fieldLabelTranslations[key] ?? humanizeFieldKey(key);
}

const defaultActionGradientClass = 'bg-gradient-to-r from-muted/60 via-background to-muted/60 border-border text-muted-foreground';

function getActionGradient(action: string | null) {
    return actionGradientClasses[action ?? ''] ?? defaultActionGradientClass;
}

function translateModuleName(module: string | null | undefined) {
    if (!module) return 'General';
    return module;
}

function translateResourceName(label: string) {
    return label;
}

function formatTimestamp(value: string | null) {
    if (!value) return 'Desconocido';
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) {
        return value;
    }
    return dateFormatter.format(parsed);
}

function actionLabel(action: string | null) {
    if (!action) return 'Sin acción';
    return actionLabels[action] ?? action;
}

function formatResource(log: AuditLog) {
    if (log.resource_label) {
        return log.auditable_id ? `${log.resource_label} #${log.auditable_id}` : log.resource_label;
    }
    if (!log.auditable_type) {
        return 'Recurso global';
    }
    const segments = log.auditable_type.split('\\');
    const label = segments.at(-1) ?? log.auditable_type;
    const translatedLabel = translateResourceName(label);
    return log.auditable_id ? `${translatedLabel} #${log.auditable_id}` : translatedLabel;
}

function formatModuleLabel(log: AuditLog) {
    const moduleLabel = log.module_label ?? translateModuleName(log.module);
    if (log.company_id) {
        return `${moduleLabel} · Empresa #${log.company_id}`;
    }
    return moduleLabel;
}

function singularFromModuleLabel(moduleLabel: string | null | undefined): string | null {
    if (!moduleLabel) return null;
    const base = String(moduleLabel).split('·')[0].trim();
    const lower = base.toLowerCase();
    if (!lower) return null;
    if (lower.endsWith('es')) return lower.slice(0, -2);
    if (lower.endsWith('s')) return lower.slice(0, -1);
    return lower;
}

function buildDetailHeader(log: AuditLog | null): string {
    if (!log) return '';
    const user = log.user_name ?? 'Sistema';
    const verb = actionVerbMap[log.action ?? ''] ?? actionLabel(log.action).toLowerCase();
    const moduleLabel = formatModuleLabel(log);
    const resourceDisplay = formatResource(log);
    const resourceType = singularFromModuleLabel(moduleLabel) ?? 'recurso';
    return `${user} ${verb} el ${resourceType} “${resourceDisplay}” en el módulo ${moduleLabel} el ${formatTimestamp(log.created_at)}`;
}

function normalizeValues(values: Record<string, unknown> | null | undefined): Record<string, unknown> {
    if (!values || typeof values !== 'object') {
        return {};
    }
    return values as Record<string, unknown>;
}

function isStructuredValue(value: unknown): value is Record<string, unknown> | unknown[] {
    return typeof value === 'object' && value !== null;
}

function formatValue(value: unknown): string {
    if (value === null || value === undefined) {
        return 'nulo';
    }
    if (typeof value === 'boolean') {
        return value ? 'Sí' : 'No';
    }
    if (typeof value === 'number') {
        return String(value);
    }
    if (isStructuredValue(value)) {
        return formatStructured(value);
    }
    const text = String(value).trim();
    if (text === '') {
        return '-';
    }
    const parsed = tryParseJsonString(text);
    if (parsed !== null) {
        return formatStructured(parsed);
    }
    return text;
}

function formatStructured(value: Record<string, unknown> | unknown[]): string {
    if (Array.isArray(value)) {
        return formatStructuredArray(value);
    }
    return formatStructuredObject(value);
}

function formatStructuredArray(values: unknown[]): string {
    if (values.length === 0) {
        return 'Vacío';
    }
    return values.map((item) => formatValue(item)).join(', ');
}

function formatStructuredObject(value: Record<string, unknown>): string {
    const entries = Object.entries(value);
    if (entries.length === 0) {
        return 'Vacío';
    }
    return entries.map(([key, nested]) => `${translateDiffLabel(key)}: ${formatValue(nested)}`).join('\n');
}

function looksLikeImagePath(value: string): boolean {
    const cleanValue = value.split('?')[0] ?? value;
    return /\.(png|jpe?g|webp|gif|bmp|svg)$/i.test(cleanValue);
}

function buildAvatarImageUrl(value: unknown): string | null {
    if (typeof value !== 'string') {
        return null;
    }

    const raw = value.trim();
    if (!raw || raw === '-' || raw.toLowerCase() === 'null') {
        return null;
    }

    if (/^https?:\/\//i.test(raw) || raw.startsWith('data:image/')) {
        return looksLikeImagePath(raw) ? raw : null;
    }

    let normalizedPath = raw.replace(/^\/+/, '');
    if (normalizedPath.startsWith('storage/')) {
        normalizedPath = normalizedPath.slice('storage/'.length);
    }

    if (!normalizedPath.startsWith('avatars/') || !looksLikeImagePath(normalizedPath)) {
        return null;
    }

    const encodedPath = normalizedPath
        .split('/')
        .map((segment) => encodeURIComponent(segment))
        .join('/');

    return `/profile/avatar-file/${encodedPath}`;
}

function resolveAuditImageUrl(key: string, value: unknown): string | null {
    if (key !== 'avatar_path') {
        return null;
    }

    return buildAvatarImageUrl(value);
}

const auditImageFallback =
    'data:image/svg+xml;utf8,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 64 64%22%3E%3Crect width=%2264%22 height=%2264%22 rx=%228%22 fill=%22%23f1f5f9%22/%3E%3Ccircle cx=%2232%22 cy=%2226%22 r=%2210%22 fill=%22%23cbd5e1%22/%3E%3Crect x=%2216%22 y=%2240%22 width=%2232%22 height=%2212%22 rx=%226%22 fill=%22%23cbd5e1%22/%3E%3C/svg%3E';

function isAvatarField(key: string): boolean {
    return key === 'avatar_path';
}

function handleAuditImageError(event: Event) {
    const image = event.target as HTMLImageElement | null;
    if (image) {
        if (image.dataset.fallbackApplied === 'true') {
            return;
        }

        image.dataset.fallbackApplied = 'true';
        image.src = auditImageFallback;
        image.classList.add('opacity-70');
    }
}

function tryParseJsonString(value: string): Record<string, unknown> | unknown[] | null {
    if (!value) {
        return null;
    }
    const trimmed = value.trim();
    if (!trimmed.startsWith('{') && !trimmed.startsWith('[')) {
        return null;
    }
    try {
        const parsed = JSON.parse(trimmed);
        if (typeof parsed === 'object' && parsed !== null) {
            return parsed as Record<string, unknown> | unknown[];
        }
    } catch {
        // ignore
    }
    return null;
}

function summarizeChangedFields(log: AuditLog): string[] {
    return getDiffEntries(log)
        .map((entry) => translateDiffLabel(entry.key))
        .filter(Boolean);
}

function formatDescriptionSummary(log: AuditLog): string {
    const verb = actionVerbMap[log.action ?? ''] ?? 'registró';
    const fields = summarizeChangedFields(log);
    if (!fields.length) {
        return `Se ${verb} un recurso.`;
    }
    const limit = 3;
    const displayed = fields.slice(0, limit);
    const extras = fields.length > limit ? ` (+${fields.length - limit})` : '';
    return `Se ${verb} ${displayed.join(', ')}${extras}.`;
}

function getDiffEntries(log: AuditLog): DiffEntry[] {
    if (diffCache.has(log.id)) {
        return diffCache.get(log.id)!;
    }

    const oldValues = normalizeValues(log.old_values);
    const newValues = normalizeValues(log.new_values);
    const keys = Array.from(new Set([...Object.keys(oldValues), ...Object.keys(newValues)]));
    const ignoreDateKeys = ['created_at', 'updated_at', 'deleted_at'];

    const entries = keys
        .filter((key) => !ignoreDateKeys.includes(key))
        .map((key) => {
            const oldRaw = oldValues[key];
            const newRaw = newValues[key];
            const oldValue = formatValue(oldRaw);
            const newValue = formatValue(newRaw);
            const oldImageUrl = resolveAuditImageUrl(key, oldRaw);
            const newImageUrl = resolveAuditImageUrl(key, newRaw);
            return { key, oldValue, newValue, oldImageUrl, newImageUrl };
        })
        .filter((entry) => entry.oldValue !== entry.newValue);

    diffCache.set(log.id, entries);
    return entries;
}

function openDiffDialog(log: AuditLog) {
    detailLog.value = log;
    detailDiffEntries.value = getDiffEntries(log);
    detailDialogOpen.value = true;
}

watch(detailDialogOpen, (value) => {
    if (!value) {
        detailLog.value = null;
        detailDiffEntries.value = [];
    }
});
</script>

<template>
    <Head title="Auditoría" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <!-- Contenedor principal igual al de Gestión de Ítems -->
        <div class="relative flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <!-- Encabezado -->
            <div class="mb-2 flex flex-col gap-1">
                <h1 class="text-lg font-bold">Auditoría</h1>
                <p class="text-sm text-muted-foreground">
                    El módulo de auditoría documenta de forma detallada cada cambio realizado en el sistema: registra quién lo hizo, desde qué módulo
                    y qué recursos fueron impactados, facilitando la trazabilidad y el monitoreo operativo para la empresa.
                </p>
            </div>

            <!-- FILTROS: UNA SOLA FILA (mismo estilo que Items) -->
            <div class="mb-2">
                <FilterBar align="start" gap="gap-4" noMargin>
                    <FilterField label="Usuario" :active="filters.user.trim().length > 0" class="min-w-[220px]">
                        <Input id="audit-filter-user" v-model="filters.user" placeholder="Nombre del usuario" class="h-10 w-full" />
                    </FilterField>
                    <FilterField label="Área" :active="isAreaFilterActive" class="min-w-[200px]">
                        <SelectField
                            v-model="selectedAreaId"
                            :options="areaSelectOptions"
                            placeholder="Todas las áreas"
                            :button-class="areaFilterButtonClasses"
                        />
                    </FilterField>
                    <FilterField label="Módulo" :active="Boolean(selectModule?.trim())" class="min-w-[220px]">
                        <SelectField
                            :options="moduleOptions"
                            v-model="selectModule"
                            :placeholder="'Ej. Gastos'"
                            :searchable="true"
                            :button-class="moduleButtonClasses"
                        />
                    </FilterField>
                    <FilterField label="Acción" :active="Boolean(selectAction)" class="min-w-[200px]">
                        <SelectField
                            :options="actionOptionsForSelect"
                            v-model="selectAction"
                            :placeholder="'Filtrar por acción'"
                            :searchable="false"
                            :button-class="actionButtonClasses"
                        />
                    </FilterField>
                    <FilterField label="Fecha" :active="dateFilterActive" class="min-w-[220px]">
                        <DatePickerField v-model="dateFilterModel" label="" placeholder-text="dd/mm/aaaa" class="w-full" />
                    </FilterField>
                </FilterBar>
            </div>

            <!-- TARJETA / TABLA: mismo “card” que en Gestión de Ítems -->
            <div class="relative min-h-0 flex-1 rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border md:min-h-min">
                <Table class="text-sm">
                    <TableHeader>
                        <TableRow>
                            <TableHead class="px-4 py-2 text-left">Fecha</TableHead>
                            <TableHead class="w-[150px] px-4 py-2 text-left">Área</TableHead>
                            <TableHead class="w-[180px] px-4 py-2 text-left">Rol</TableHead>
                            <TableHead class="w-[220px] px-4 py-2 text-left">Usuario</TableHead>
                            <TableHead class="px-4 py-2 text-left">Acción</TableHead>
                            <TableHead class="px-4 py-2 text-left">Módulo</TableHead>
                            <TableHead class="px-4 py-2 text-left">Recurso</TableHead>
                            <TableHead class="px-4 py-2 text-left">Descripción</TableHead>
                            <TableHead class="w-[70px] px-4 py-2 pr-6 text-right"></TableHead>
                        </TableRow>
                    </TableHeader>

                    <TableBody>
                        <TableRow v-for="log in logs" :key="log.id" class="align-middle">
                            <!-- FECHA -->
                            <TableCell class="whitespace-nowrap px-4 py-3 text-left text-xs text-muted-foreground">
                                {{ formatTimestamp(log.created_at) }}
                            </TableCell>

                            <!-- ÁREA -->
                            <TableCell class="px-4 py-3 text-left">
                                <span v-if="log.user_area" class="text-xs font-semibold">
                                    {{ log.user_area }}
                                </span>
                                <span v-else class="text-xs text-muted-foreground">-</span>
                            </TableCell>

                            <!-- ROL -->
                            <TableCell class="px-4 py-3 text-left">
                                <span v-if="log.user_role" class="text-xs font-semibold">
                                    {{ log.user_role }}
                                </span>
                                <span v-else class="text-xs text-muted-foreground">-</span>
                            </TableCell>

                            <!-- USUARIO -->
                            <TableCell class="px-4 py-3 text-left">
                                <div class="font-semibold">
                                    {{ log.user_name ?? 'Sistema' }}
                                </div>
                            </TableCell>

                            <!-- ACCIÓN -->
                            <TableCell class="px-4 py-3 text-left">
                                <Badge variant="outline" :class="getActionGradient(log.action)">
                                    {{ actionLabel(log.action) }}
                                </Badge>
                            </TableCell>

                            <!-- MÓDULO -->
                            <TableCell class="px-4 py-3 text-left">
                                <div class="font-semibold">
                                    {{ formatModuleLabel(log) }}
                                </div>
                            </TableCell>

                            <!-- RECURSO -->
                            <TableCell class="px-4 py-3 text-left">
                                {{ formatResource(log) }}
                            </TableCell>

                            <!-- DESCRIPCIÓN -->
                            <TableCell class="px-4 py-3 text-left">
                                <p class="text-xs text-muted-foreground">
                                    {{ formatDescriptionSummary(log) }}
                                </p>
                            </TableCell>

                            <!-- OJITO -->
                            <TableCell class="px-4 py-3 pr-6 text-right">
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center text-muted-foreground transition-colors hover:text-sky-600 disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="!getDiffEntries(log).length"
                                    @click="openDiffDialog(log)"
                                >
                                    <Eye class="h-4 w-4" />
                                </button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <!-- Pie de tabla / paginación, mismo estilo de card -->
                <div class="flex flex-col gap-2 border-t border-border/70 bg-card/50 px-4 py-3 md:flex-row md:items-center md:justify-between">
                    <p class="text-xs text-muted-foreground">
                        Página {{ currentPage }} de {{ lastPage }} · Mostrando {{ logs.length }} de {{ totalItems }} registros
                    </p>

                    <Pagination
                        class="flex justify-center"
                        :items-per-page="itemsPerPage"
                        :total="totalItems"
                        :sibling-count="1"
                        show-edges
                        :page="currentPage"
                        @update:page="handlePageChange"
                    >
                        <PaginationList v-slot="{ items }" class="flex items-center gap-1">
                            <PaginationFirst />
                            <PaginationPrev />

                            <template v-for="(item, index) in items" :key="index">
                                <PaginationListItem v-if="item.type === 'page'" :value="item.value" as-child>
                                    <Button class="h-6 w-6 p-0 text-xs" :variant="item.value === currentPage ? 'default' : 'outline'">
                                        {{ item.value }}
                                    </Button>
                                </PaginationListItem>
                                <PaginationEllipsis v-else :index="index" />
                            </template>

                            <PaginationNext />
                            <PaginationLast />
                        </PaginationList>
                    </Pagination>
                </div>
            </div>
        </div>

        <!-- Diálogo de detalle de cambios (igual que ya tenías) -->
        <Dialog v-model:open="detailDialogOpen">
            <DialogContent class="flex max-h-[90vh] max-w-3xl flex-col rounded-2xl p-0">
                <DialogHeader class="flex-shrink-0 space-y-1 border-b border-border px-6 py-4">
                    <DialogTitle>Detalle de cambios</DialogTitle>
                    <DialogDescription v-if="detailLog">
                        {{ buildDetailHeader(detailLog) }}
                    </DialogDescription>
                </DialogHeader>
                <div class="flex-1 overflow-y-auto scroll-smooth px-6 py-4" style="scrollbar-width: thin">
                    <div v-if="detailDiffEntries.length">
                        <Table class="text-xs">
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Campo</TableHead>
                                    <TableHead class="text-center">Valor anterior</TableHead>
                                    <TableHead class="text-center">Valor actualizado</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="entry in detailDiffEntries" :key="`${entry.key}-${entry.oldValue}-${entry.newValue}`">
                                    <TableCell class="font-semibold text-muted-foreground">
                                        {{ translateDiffLabel(entry.key) }}
                                    </TableCell>
                                    <TableCell class="max-w-[220px] whitespace-pre-line break-words text-center text-xs">
                                        <div class="flex flex-col items-center gap-2">
                                            <template v-if="isAvatarField(entry.key)">
                                                <img
                                                    :src="entry.oldImageUrl ?? auditImageFallback"
                                                    alt="Avatar anterior"
                                                    class="h-16 w-16 rounded-md border border-border object-cover"
                                                    loading="lazy"
                                                    @error="handleAuditImageError"
                                                />
                                            </template>
                                            <span v-else>{{ entry.oldValue }}</span>
                                        </div>
                                    </TableCell>
                                    <TableCell class="max-w-[220px] whitespace-pre-line break-words text-center text-xs">
                                        <div class="flex flex-col items-center gap-2">
                                            <template v-if="isAvatarField(entry.key)">
                                                <img
                                                    :src="entry.newImageUrl ?? auditImageFallback"
                                                    alt="Avatar actualizado"
                                                    class="h-16 w-16 rounded-md border border-border object-cover"
                                                    loading="lazy"
                                                    @error="handleAuditImageError"
                                                />
                                            </template>
                                            <span v-else>{{ entry.newValue }}</span>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">No se encontraron campos modificados.</p>
                </div>
                <DialogFooter class="flex flex-shrink-0 justify-end gap-2 border-t border-border px-6 py-3">
                    <DialogClose as-child>
                        <Button variant="outline" size="pill">
                            Cerrar
                        </Button>
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
