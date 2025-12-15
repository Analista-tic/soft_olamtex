<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import type { BadgeVariants } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import Input from '@/components/ui/input/Input.vue';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
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
import AppLayout from '@/layouts/AppLayout.vue';
import IndexLayout from '@/components/Index.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import type { AuditLog, BreadcrumbItem, PaginatedData, SharedData } from '@/types';
import { Eye } from 'lucide-vue-next';

interface AuditPageProps extends SharedData {
    logs: PaginatedData<AuditLog>;
    filters: {
        user?: string | null;
        module?: string | null;
        action?: string | null;
    };
}

interface DiffEntry {
    key: string;
    oldValue: string;
    newValue: string;
}

const props = defineProps<AuditPageProps>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Configuración', href: route('panel-administrativo') },
    { title: 'Auditoría', href: route('auditoria.index') },
];

const filters = reactive({
    user: props.filters.user ?? '',
    module: props.filters.module ?? '',
    action: props.filters.action ?? null,
});

const actionOptions = [
    { id: 'all', value: null, label: 'Todos' },
    { id: 'created', value: 'created', label: 'Creado' },
    { id: 'updated', value: 'updated', label: 'Actualizado' },
    { id: 'deleted', value: 'deleted', label: 'Eliminado' },
];

const logs = computed(() => props.logs?.data ?? []);
const totalItems = computed(() => props.logs?.total ?? 0);
const itemsPerPage = computed(() => props.logs?.per_page ?? 50);
const lastPage = computed(() => props.logs?.last_page ?? 1);
const currentPage = ref(props.logs?.current_page ?? 1);

const diffCache = new Map<number, DiffEntry[]>();
const detailDialogOpen = ref(false);
const detailLog = ref<AuditLog | null>(null);
const detailDiffEntries = ref<DiffEntry[]>([]);

watch(
    () => props.filters,
    (values) => {
        filters.user = values.user ?? '';
        filters.module = values.module ?? '';
        filters.action = values.action ?? null;
    },
    { deep: true },
);

watch(
    () => props.logs?.current_page,
    (page) => {
        currentPage.value = page ?? 1;
    },
);

watch(
    () => props.logs?.data,
    () => {
        diffCache.clear();
    },
);

const filterParams = computed(() => {
    const params: Record<string, string> = {};
    if (filters.user.trim()) {
        params.user = filters.user.trim();
    }
    if (filters.module.trim()) {
        params.module = filters.module.trim();
    }
    if (filters.action) {
        params.action = filters.action;
    }
    return params;
});

function navigateTo(page = 1) {
    router.visit(route('auditoria.index'), {
        method: 'get',
        data: {
            ...filterParams.value,
            page,
        },
        preserveScroll: true,
        replace: true,
    });
}

function applyFilters() {
    navigateTo(1);
}

function resetFilters() {
    filters.user = '';
    filters.module = '';
    filters.action = null;
    navigateTo(1);
}

function handlePageChange(page: number) {
    if (!page || page === currentPage.value) {
        return;
    }
    navigateTo(page);
}

const dateFormatter = new Intl.DateTimeFormat('es-CO', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

const actionLabels: Record<string, string> = {
    created: 'Creado',
    updated: 'Actualizado',
    deleted: 'Eliminado',
};

const actionVariantMap: Record<string, BadgeVariants['variant']> = {
    created: 'default',
    updated: 'secondary',
    deleted: 'destructive',
};

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

function getActionVariant(action: string | null) {
    return actionVariantMap[action ?? ''] ?? 'outline';
}

function formatResource(log: AuditLog) {
    if (!log.auditable_type) {
        return 'Recurso global';
    }
    const segments = log.auditable_type.split('\\');
    const label = segments.at(-1) ?? log.auditable_type;
    return log.auditable_id ? `${label} #${log.auditable_id}` : label;
}

function formatModuleLabel(log: AuditLog) {
    const module = log.module ?? 'General';
    if (log.company_id) {
        return `${module} · Empresa #${log.company_id}`;
    }
    return module;
}

function normalizeValues(values: Record<string, unknown> | null | undefined): Record<string, unknown> {
    if (!values || typeof values !== 'object') {
        return {};
    }
    return values as Record<string, unknown>;
}

function formatValue(value: unknown): string {
    if (value === null || value === undefined) {
        return 'nulo';
    }
    if (typeof value === 'object') {
        try {
            return JSON.stringify(value);
        } catch {
            return String(value);
        }
    }
    return String(value);
}

function getDiffEntries(log: AuditLog): DiffEntry[] {
    if (diffCache.has(log.id)) {
        return diffCache.get(log.id)!;
    }

    const oldValues = normalizeValues(log.old_values);
    const newValues = normalizeValues(log.new_values);
    const keys = Array.from(new Set([...Object.keys(oldValues), ...Object.keys(newValues)]));

    const entries = keys
        .map((key) => {
            const oldValue = formatValue(oldValues[key]);
            const newValue = formatValue(newValues[key]);
            return { key, oldValue, newValue };
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
        <IndexLayout
            title="Auditoría"
            description="Rastro completo de cambios: quién, dónde y cómo se modificó cada recurso."
        >
            <template #filters>
                <form
                    class="grid gap-4 rounded-t-xl border-b border-divider bg-card/50 p-4 md:grid-cols-4"
                    @submit.prevent="applyFilters"
                >
                    <div class="col-span-1 min-w-[220px]">
                        <label
                            for="audit-filter-user"
                            class="mb-1 block text-xs font-semibold uppercase text-muted-foreground"
                        >
                            Usuario
                        </label>
                        <Input
                            id="audit-filter-user"
                            v-model="filters.user"
                            placeholder="Nombre o correo"
                            class="h-10"
                        />
                    </div>
                    <div class="col-span-1 min-w-[220px]">
                        <label
                            for="audit-filter-module"
                            class="mb-1 block text-xs font-semibold uppercase text-muted-foreground"
                        >
                            Módulo
                        </label>
                        <Input
                            id="audit-filter-module"
                            v-model="filters.module"
                            placeholder="E.g. Expenses"
                            class="h-10"
                        />
                    </div>
                    <div class="col-span-1 min-w-[220px]">
                        <label class="mb-1 block text-xs font-semibold uppercase text-muted-foreground">
                            Acción
                        </label>
                        <Select v-model="filters.action">
                            <SelectTrigger class="h-10 w-full">
                                <SelectValue placeholder="Filtrar por acción" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem v-for="option in actionOptions" :key="option.id" :value="option.value">
                                        {{ option.label }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex items-end gap-2 md:justify-end">
                        <Button type="submit" size="sm">Filtrar</Button>
                        <Button size="sm" variant="outline" type="button" @click="resetFilters">Limpiar</Button>
                    </div>
                </form>
            </template>
            <template #default>
                <div class="overflow-hidden">
                    <Table class="text-left text-sm">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Fecha</TableHead>
                                <TableHead>Usuario</TableHead>
                                <TableHead>Acción</TableHead>
                                <TableHead>Módulo</TableHead>
                                <TableHead>Recurso</TableHead>
                                <TableHead>Descripción</TableHead>
                                <TableHead class="w-[120px]"></TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="log in logs" :key="log.id">
                                <TableCell class="font-medium">{{ formatTimestamp(log.created_at) }}</TableCell>
                                <TableCell>
                                    <div class="font-semibold">{{ log.user_name ?? 'Sistema' }}</div>
                                    <p v-if="log.user_id" class="text-xs text-muted-foreground">ID {{ log.user_id }}</p>
                                    <p v-if="log.ip_address" class="text-xs text-muted-foreground">{{ log.ip_address }}</p>
                                    <p v-if="log.user_agent" class="text-xs text-muted-foreground line-clamp-2">
                                        {{ log.user_agent }}
                                    </p>
                                </TableCell>
                                <TableCell>
                                    <Badge :variant="getActionVariant(log.action)" class="mb-1">
                                        {{ actionLabel(log.action) }}
                                    </Badge>
                                    <p class="text-xs text-muted-foreground">{{ log.action }}</p>
                                </TableCell>
                                <TableCell>
                                    <div class="font-semibold">{{ formatModuleLabel(log) }}</div>
                                    <p class="text-xs text-muted-foreground">
                                        {{ log.auditable_type ? log.auditable_type.split('\\').pop() : 'General' }}
                                    </p>
                                </TableCell>
                                <TableCell>{{ formatResource(log) }}</TableCell>
                                <TableCell>
                                    <p class="text-xs text-muted-foreground">
                                        {{ log.description ?? 'Sin descripción registrada' }}
                                    </p>
                                </TableCell>
                                <TableCell class="space-y-1 text-right">
                                    <button
                                        type="button"
                                        class="rounded-full border border-border p-1 text-muted-foreground transition hover:text-foreground"
                                        :disabled="!getDiffEntries(log).length"
                                        @click="openDiffDialog(log)"
                                    >
                                        <Eye class="h-4 w-4" />
                                    </button>
                                    <p v-if="getDiffEntries(log).length > 2" class="text-xs text-muted-foreground">+{{ getDiffEntries(log).length - 2 }}</p>
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="!logs.length">
                                <TableCell colspan="7">
                                    <p class="text-center text-sm text-muted-foreground">
                                        Ningún registro de auditoría coincide con los filtros actuales.
                                    </p>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>

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
                            v-model:current-page="currentPage"
                            @page-changed="handlePageChange"
                        >
                            <PaginationList v-slot="{ items }" class="flex items-center gap-1">
                                <PaginationFirst />
                                <PaginationPrev />

                                <template v-for="(item, index) in items" :key="index">
                                    <PaginationListItem v-if="item.type === 'page'" :value="item.value" as-child>
                                        <Button
                                            class="h-6 w-6 p-0 text-xs"
                                            :variant="item.value === currentPage ? 'default' : 'outline'"
                                            @click="handlePageChange(item.value)"
                                        >
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
            </template>
        </IndexLayout>
        <Dialog v-model:open="detailDialogOpen">
            <DialogContent class="max-w-3xl rounded-2xl p-0">
                <DialogHeader class="space-y-1 border-b border-border px-6 py-4">
                    <DialogTitle>Detalle de cambios</DialogTitle>
                    <DialogDescription v-if="detailLog">
                        {{ detailLog.user_name ?? 'Sistema' }} · {{ actionLabel(detailLog.action) }} ·
                        {{ formatModuleLabel(detailLog) }}
                    </DialogDescription>
                </DialogHeader>
                <div class="px-6 py-4">
                    <div v-if="detailDiffEntries.length">
                        <Table class="text-xs">
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Campo</TableHead>
                                    <TableHead>Valor anterior</TableHead>
                                    <TableHead>Valor actualizado</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="entry in detailDiffEntries" :key="`${entry.key}-${entry.oldValue}-${entry.newValue}`">
                                    <TableCell class="font-semibold text-muted-foreground">{{ entry.key }}</TableCell>
                                    <TableCell class="max-w-[220px] break-words text-xs">{{ entry.oldValue }}</TableCell>
                                    <TableCell class="max-w-[220px] break-words text-xs">{{ entry.newValue }}</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">No se encontraron campos modificados.</p>
                </div>
                <DialogFooter class="flex justify-end gap-2 border-t border-border px-6 py-3">
                    <DialogClose as-child>
                        <Button variant="outline" size="sm">Cerrar</Button>
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
