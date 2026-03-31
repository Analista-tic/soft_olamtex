<script setup lang="ts">
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import SelectField from '@/components/SelectField.vue';
import TableStatusBadge from '@/components/common/TableStatusBadge.vue';
import { Button } from '@/components/ui/button';
import Input from '@/components/ui/input/Input.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';
import type { BreadcrumbItem, PaginatedData, SharedData } from '@/domain/shared';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { Bell } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';
import { uiPatterns } from '@/lib/ui-patterns';

interface NotificationPayload {
    title?: string;
    body?: string;
    url?: string | null;
    type?: string | null;
    entity_id?: number | string | null;
    module?: string | null;
}

interface NotificationRow {
    id: string;
    type: string;
    read_at: string | null;
    created_at: string | null;
    data: NotificationPayload;
}

interface NotificationsPageProps extends SharedData {
    notifications: PaginatedData<NotificationRow> | null;
}

const props = defineProps<NotificationsPageProps>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Notificaciones',
        href: '/notificaciones',
    },
];

const allNotifications = computed<NotificationRow[]>(() => props.notifications?.data ?? []);

const filters = reactive({
    status: 'all' as 'all' | 'unread' | 'read',
    type: '',
    date: '',
});

const statusOptions = [
    { id: 'all', name: 'Todas' },
    { id: 'unread', name: 'No leídas' },
    { id: 'read', name: 'Leídas' },
];

const selectedStatus = ref<'all' | 'unread' | 'read'>(filters.status);

const typeOptions = computed(() => {
    const map = new Map<string, string>();

    for (const n of allNotifications.value) {
        const raw = n.data?.type ?? n.type;
        if (!raw) continue;
        if (!map.has(raw)) {
            map.set(raw, formatTypeLabel(raw));
        }
    }

    const base = Array.from(map.entries()).map(([id, name]) => ({ id, name }));
    base.sort((a, b) => a.name.localeCompare(b.name, 'es'));

    return [{ id: '', name: 'Todos los tipos' }, ...base];
});

const selectedType = ref<string>('');

const statusFilterActive = computed(() => selectedStatus.value !== 'all');
const typeFilterActive = computed(() => Boolean(selectedType.value));
const dateFilterActive = computed(() => Boolean(filters.date?.trim()));

const statusFilterButtonClasses = useFilterButtonClasses(statusFilterActive);
const typeFilterButtonClasses = useFilterButtonClasses(typeFilterActive);

const dateFormatter = new Intl.DateTimeFormat('es-CO', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
});

function formatDateTime(value: string | null): string {
    if (!value) return '';
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return value;
    return dateFormatter.format(parsed);
}

function normalizeDateOnly(value: string | null): string | null {
    if (!value) return null;
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return null;
    const year = parsed.getFullYear();
    const month = String(parsed.getMonth() + 1).padStart(2, '0');
    const day = String(parsed.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function formatTypeLabel(raw: string): string {
    if (!raw) return 'General';
    const base = raw.split('.').at(-1) ?? raw;
    return base
        .replace(/[_\-]+/g, ' ')
        .split(' ')
        .filter(Boolean)
        .map((segment) => segment.charAt(0).toUpperCase() + segment.slice(1))
        .join(' ');
}

function formatModuleLabel(notification: NotificationRow): string {
    if (notification.data?.module) return notification.data.module;
    const raw = notification.data?.type ?? notification.type;
    if (!raw) return 'General';
    const firstSegment = raw.split('.')[0] ?? raw;
    return formatTypeLabel(firstSegment);
}

const filteredNotifications = computed<NotificationRow[]>(() => {
    const rows = allNotifications.value;
    const status = selectedStatus.value;
    const type = selectedType.value;
    const selectedDate = filters.date?.trim();

    return rows.filter((row) => {
        if (status === 'unread' && row.read_at) return false;
        if (status === 'read' && !row.read_at) return false;

        if (type) {
            const rawType = row.data?.type ?? row.type ?? '';
            if (rawType !== type) return false;
        }

        if (selectedDate) {
            const dateOnly = normalizeDateOnly(row.created_at);
            if (dateOnly !== selectedDate) return false;
        }

        return true;
    });
});

const totalItems = computed(() => filteredNotifications.value.length);

const hasActiveFilters = computed(() => statusFilterActive.value || typeFilterActive.value || dateFilterActive.value);

function clearFilters() {
    filters.status = 'all';
    filters.type = '';
    filters.date = '';
    selectedStatus.value = 'all';
    selectedType.value = '';
}

watch(selectedStatus, (value) => {
    filters.status = value;
});

watch(selectedType, (value) => {
    filters.type = value ?? '';
});

function handleOpen(notification: NotificationRow) {
    const url = notification.data?.url;
    if (!url) return;

    router.visit(url);
}
</script>

<template>
    <Head title="Notificaciones" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="relative flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <!-- Encabezado -->
            <div class="mb-2 flex flex-col gap-1">
                <h1 :class="uiPatterns.typography.pageTitle">Notificaciones</h1>
                <p :class="uiPatterns.typography.pageSubtitle">
                    Consulta el historial de notificaciones generadas por el sistema para los distintos módulos y procesos operativos.
                </p>
            </div>

            <!-- Filtros -->
            <div class="mb-2">
                <FilterBar align="start" gap="gap-4" noMargin>
                    <FilterField label="Estado" :active="statusFilterActive" class="min-w-[180px]">
                        <SelectField
                            v-model="selectedStatus"
                            :options="statusOptions"
                            placeholder="Todas"
                            :button-class="statusFilterButtonClasses"
                        />
                    </FilterField>

                    <FilterField label="Tipo" :active="typeFilterActive" class="min-w-[220px]">
                        <SelectField
                            v-model="selectedType"
                            :options="typeOptions"
                            placeholder="Todos los tipos"
                            :searchable="true"
                            :button-class="typeFilterButtonClasses"
                        />
                    </FilterField>

                    <FilterField label="Fecha" :active="dateFilterActive" class="min-w-[220px]">
                        <Input id="notifications-filter-date" v-model="filters.date" type="date" placeholder="Fecha" class="h-10 w-full" />
                    </FilterField>

                    <div class="ml-auto flex items-end">
                        <Button variant="outline" size="sm" class="h-9 text-xs" :disabled="!hasActiveFilters" @click="clearFilters">
                            Limpiar filtros
                        </Button>
                    </div>
                </FilterBar>
            </div>

            <!-- Tabla / Card -->
            <div class="relative min-h-0 flex-1 rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border md:min-h-min">
                <Table class="text-sm">
                    <TableHeader>
                        <TableRow>
                            <TableHead class="px-4 py-2 text-left">Fecha</TableHead>
                            <TableHead class="w-[160px] px-4 py-2 text-left">Tipo</TableHead>
                            <TableHead class="w-[220px] px-4 py-2 text-left">Título</TableHead>
                            <TableHead class="w-[200px] px-4 py-2 text-left">Módulo</TableHead>
                            <TableHead class="w-[120px] px-4 py-2 text-left">Estado</TableHead>
                            <TableHead class="w-[80px] px-4 py-2 pr-6 text-right">Acción</TableHead>
                        </TableRow>
                    </TableHeader>

                    <TableBody>
                        <TableRow v-for="notification in filteredNotifications" :key="notification.id" class="align-middle">
                            <TableCell class="whitespace-nowrap px-4 py-3 text-left text-xs text-muted-foreground">
                                {{ formatDateTime(notification.created_at) }}
                            </TableCell>
                            <TableCell class="px-4 py-3 text-left">
                                <span class="text-xs font-semibold">
                                    {{ formatTypeLabel(notification.data?.type ?? notification.type) }}
                                </span>
                            </TableCell>
                            <TableCell class="px-4 py-3 text-left">
                                <div class="font-semibold">
                                    {{ notification.data?.title ?? 'Notificación' }}
                                </div>
                                <p v-if="notification.data?.body" class="mt-0.5 line-clamp-2 text-xs text-muted-foreground">
                                    {{ notification.data.body }}
                                </p>
                            </TableCell>
                            <TableCell class="px-4 py-3 text-left">
                                <div class="text-xs font-semibold">
                                    {{ formatModuleLabel(notification) }}
                                </div>
                            </TableCell>
                            <TableCell class="px-4 py-3 text-left">
                                <TableStatusBadge
                                    :label="!notification.read_at ? 'No leída' : 'Leída'"
                                    :tone="!notification.read_at ? 'green' : 'slate'"
                                />
                            </TableCell>
                            <TableCell class="px-4 py-3 pr-6 text-right">
                                <Button
                                    v-if="notification.data?.url"
                                    variant="ghost"
                                    size="icon"
                                    class="h-7 w-7 text-muted-foreground hover:text-primary"
                                    aria-label="Ver detalle de la notificación"
                                    @click="handleOpen(notification)"
                                >
                                    <Bell class="h-4 w-4" />
                                </Button>
                            </TableCell>
                        </TableRow>

                        <TableRow v-if="totalItems === 0">
                            <TableCell colspan="6" class="py-8 text-center text-sm text-muted-foreground">
                                No hay notificaciones que coincidan con los filtros seleccionados.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <!-- Pie de tabla sencillo -->
                <div class="flex items-center justify-between border-t border-border/70 bg-card/50 px-4 py-3">
                    <p class="text-xs text-muted-foreground">Mostrando {{ totalItems }} notificaciones de {{ allNotifications.length }} cargadas.</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>


