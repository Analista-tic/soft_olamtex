<script setup lang="ts">
import type { BreadcrumbItem } from '@/domain/shared';
import type { NotificationRow, NotificationFilters } from '@/domain/Settings';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Card } from '@/components/ui/card';
import Input from '@/components/ui/input/Input.vue';
import TableStatusBadge from '@/components/common/TableStatusBadge.vue';
import SelectField from '@/components/SelectField.vue';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { useFilterButtonClasses } from '@/composables/useFilterButtonClasses';
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
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import { Calendar as CalendarIcon, Check, CheckCheck, ExternalLink, Search, Inbox, RotateCcw } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import AlertStack from '@/components/common/AlertStack.vue';
import { useAlertStack } from '@/composables/useAlert';
import { getLocalTimeZone, parseDate, type DateValue } from '@internationalized/date';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Configuración',
        href: '/configuracion',
    },
    {
        title: 'Notificaciones',
        href: '/configuracion/notificaciones',
    },
];

// Props recibidas desde el controlador (Inertia)
const props = defineProps<{
    notifications: {
        data: NotificationRow[];
        total: number;
        per_page: number;
        current_page: number;
        last_page: number;
    };
    filters: NotificationFilters;
}>();

// Estado local
const notificationsList = ref<NotificationRow[]>(props.notifications.data);
const totalItems = ref(props.notifications.total);
const currentPage = ref(props.notifications.current_page);
const itemsPerPage = ref(props.notifications.per_page);
const lastPage = ref(props.notifications.last_page);
const isLoading = ref(false);

// Filtros
const searchQuery = ref(props.filters.search ?? '');
const selectedArea = ref(props.filters.area ?? '');
const selectedStatus = ref(props.filters.status ?? '');
const selectedDate = ref(props.filters.date ?? '');

// Alertas
const { successMessage, errorMessage, errorType, showSuccess, showError, clearSuccess, clearError } = useAlertStack();

// ---------- OPCIONES DE ÁREA ----------
type AreaOption = { id: number | string; name: string };
type NumericAreaOption = { id: number; name: string };
const areaOptions = ref<AreaOption[]>([]);
const areaSelectOptions = computed(() => [{ id: '', name: 'Todas las áreas' }, ...areaOptions.value]);

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

// ---------- OPCIONES DE ESTADO ----------
const statusOptions = [
    { id: '', name: 'Todas' },
    { id: 'unread', name: 'No leídas' },
    { id: 'read', name: 'Leídas' },
];

// ---------- CLASES PARA FILTROS ACTIVOS ----------
const isSearchActive = computed(() => searchQuery.value.trim().length > 0);
const isAreaActive = computed(() => selectedArea.value !== '');
const isStatusActive = computed(() => selectedStatus.value !== '');
const isDateActive = computed(() => selectedDate.value !== '');
const hasActiveFilters = computed(() => isSearchActive.value || isAreaActive.value || isStatusActive.value || isDateActive.value);

const areaButtonClasses = useFilterButtonClasses(isAreaActive);
const statusButtonClasses = useFilterButtonClasses(isStatusActive);
const dateButtonClasses = useFilterButtonClasses(isDateActive, {
    baseClasses:
        'h-10 w-full rounded-md border px-3 text-sm font-normal flex items-center justify-between transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2',
});

// ---------- CALENDAR DATE VALUE ----------
const dateValue = computed<DateValue | undefined>({
    get() {
        if (!selectedDate.value) return undefined;
        try {
            return parseDate(selectedDate.value);
        } catch {
            return undefined;
        }
    },
    set(value) {
        selectedDate.value = value ? value.toString() : '';
    },
});

const dateLabel = computed(() => {
    if (!selectedDate.value) return 'Seleccionar';
    try {
        return format(parseDate(selectedDate.value).toDate(getLocalTimeZone()), 'dd/MM/yyyy', { locale: es });
    } catch {
        return 'Seleccionar';
    }
});

function clearDateFilter() {
    selectedDate.value = '';
}

/**
 * Formatea una fecha ISO para mostrarla en la tabla.
 */
function formatDate(dateString: string | null): string {
    if (!dateString) return '—';
    const date = new Date(dateString);
    return new Intl.DateTimeFormat('es-ES', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
}

/**
 * Obtiene las notificaciones del servidor con los filtros actuales.
 */
async function fetchNotifications(page = 1) {
    isLoading.value = true;
    try {
        const params: Record<string, unknown> = {
            page,
            per_page: itemsPerPage.value,
        };
        
        if (searchQuery.value.trim()) params.search = searchQuery.value.trim();
        if (selectedArea.value) params.area = selectedArea.value;
        if (selectedStatus.value) params.status = selectedStatus.value;
        if (selectedDate.value) {
            params.date_from = selectedDate.value;
            params.date_to = selectedDate.value;
        }

        const response = await axios.get('/configuracion/notificaciones/data', { params });
        
        notificationsList.value = response.data.data ?? [];
        totalItems.value = response.data.total ?? 0;
        currentPage.value = response.data.current_page ?? page;
        lastPage.value = response.data.last_page ?? 1;
    } catch (error) {
        console.error('Error al obtener notificaciones:', error);
        showError('Error al cargar las notificaciones.');
    } finally {
        isLoading.value = false;
    }
}

/**
 * Marca una notificación como leída.
 */
async function markAsRead(notification: NotificationRow) {
    if (notification.is_read) return;
    
    try {
        await axios.post(`/configuracion/notificaciones/${notification.id}/read`);
        
        const index = notificationsList.value.findIndex((n) => n.id === notification.id);
        if (index !== -1) {
            notificationsList.value[index] = {
                ...notificationsList.value[index],
                is_read: true,
                read_at: new Date().toISOString(),
            };
        }
    } catch (error) {
        console.error('Error al marcar como leída:', error);
        showError('Error al marcar la notificación como leída.');
    }
}

/**
 * Marca todas las notificaciones como leídas.
 */
async function markAllAsRead() {
    try {
        const response = await axios.post('/configuracion/notificaciones/read-all');
        showSuccess(response.data.message || 'Todas las notificaciones marcadas como leídas.');
        
        notificationsList.value = notificationsList.value.map((n) => ({
            ...n,
            is_read: true,
            read_at: n.read_at ?? new Date().toISOString(),
        }));
    } catch (error) {
        console.error('Error al marcar todas como leídas:', error);
        showError('Error al marcar todas las notificaciones como leídas.');
    }
}

/**
 * Maneja el click en una notificación: marca como leída y redirige si hay URL.
 */
async function handleNotificationClick(notification: NotificationRow) {
    if (!notification.is_read) {
        await markAsRead(notification);
    }
    
    if (notification.url) {
        router.visit(notification.url);
    }
}

/**
 * Limpia todos los filtros.
 */
function clearFilters() {
    searchQuery.value = '';
    selectedArea.value = '';
    selectedStatus.value = '';
    selectedDate.value = '';
    fetchNotifications(1);
}

/**
 * Verifica si hay notificaciones no leídas en la lista actual.
 */
const hasUnreadNotifications = computed(() => 
    notificationsList.value.some((n) => !n.is_read)
);

// Watchers para actualizar cuando cambian los filtros
watch([searchQuery, selectedArea, selectedStatus, selectedDate], () => {
    fetchNotifications(1);
});
</script>

<template>
    <Head title="Notificaciones" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <AlertStack
            :successMessage="successMessage"
            :errorMessage="errorMessage"
            :errorType="errorType"
            class="mb-2"
            @close-success="clearSuccess"
            @close-error="clearError"
        />
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex flex-col gap-1">
                    <h1 class="text-lg font-bold">Notificaciones</h1>
                    <p class="text-sm text-muted-foreground">
                        Consulta el historial de notificaciones generadas por el sistema.
                    </p>
                </div>
                <Button
                    v-if="hasUnreadNotifications"
                    @click="markAllAsRead"
                    variant="outline"
                    size="sm"
                    class="flex items-center gap-2 rounded-full px-5 py-2 text-sm font-semibold shadow-sm h-10"
                >
                    <CheckCheck class="w-4 h-4" /> Marcar todas como leídas
                </Button>
            </div>

            <!-- Filtros: Grid 12 columnas -->
            <div class="grid grid-cols-12 gap-4">
                <!-- Buscar: col-span-12 md:col-span-6 -->
                <div class="col-span-12 md:col-span-6">
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">Buscar</label>
                    <div class="relative w-full">
                        <Input
                            id="search_notification"
                            v-model="searchQuery"
                            placeholder="Buscar por título o mensaje..."
                            class="h-10 w-full pl-10"
                        />
                        <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                            <Search :size="20" class="text-muted-foreground" />
                        </span>
                    </div>
                </div>
                
                <!-- Área: col-span-12 md:col-span-2 -->
                <div class="col-span-12 md:col-span-2">
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">Área</label>
                    <SelectField
                        v-model="selectedArea"
                        :options="areaSelectOptions"
                        placeholder="Todas las áreas"
                        :button-class="areaButtonClasses"
                    />
                </div>
                
                <!-- Estado: col-span-12 md:col-span-2 -->
                <div class="col-span-12 md:col-span-2">
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">Estado</label>
                    <SelectField
                        v-model="selectedStatus"
                        :options="statusOptions"
                        placeholder="Todas"
                        :button-class="statusButtonClasses"
                    />
                </div>
                
                <!-- Fecha: col-span-12 md:col-span-2 -->
                <div class="col-span-12 md:col-span-2">
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">Fecha</label>
                    <Popover>
                        <PopoverTrigger as-child>
                            <Button type="button" variant="outline" :class="dateButtonClasses">
                                <span class="truncate text-left">{{ dateLabel }}</span>
                                <CalendarIcon class="ml-2 h-4 w-4 shrink-0" />
                            </Button>
                        </PopoverTrigger>
                        <PopoverContent class="w-auto p-0" align="start">
                            <Calendar
                                :model-value="dateValue"
                                @update:model-value="(value) => { dateValue = value; }"
                                initial-focus
                            />
                            <div class="border-t p-2">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="w-full justify-center"
                                    :disabled="!isDateActive"
                                    @click="clearDateFilter"
                                >
                                    Limpiar
                                </Button>
                            </div>
                        </PopoverContent>
                    </Popover>
                </div>
            </div>
            
            <!-- Botón Limpiar Filtros -->
            <div v-if="hasActiveFilters" class="flex justify-end -mt-2">
                <Button
                    @click="clearFilters"
                    variant="ghost"
                    size="sm"
                    class="h-8 px-3 text-xs"
                >
                    <RotateCcw class="w-3 h-3 mr-1" />
                    Limpiar filtros
                </Button>
            </div>

            <!-- Tabla de Notificaciones -->
            <Card class="relative min-h-0 flex-1 rounded-xl border border-border bg-card shadow-sm text-card-foreground md:min-h-min overflow-hidden">
                <Table class="table-fixed">
                    <colgroup>
                        <col class="w-[140px]" />
                        <col class="w-[120px]" />
                        <col class="w-[140px]" />
                        <col class="w-[120px]" />
                        <col />
                        <col class="w-[120px]" />
                        <col class="w-[96px]" />
                    </colgroup>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="whitespace-nowrap">Fecha</TableHead>
                            <TableHead class="whitespace-nowrap">Área</TableHead>
                            <TableHead class="whitespace-nowrap">Usuario</TableHead>
                            <TableHead class="whitespace-nowrap">Módulo</TableHead>
                            <TableHead>Mensaje</TableHead>
                            <TableHead class="whitespace-nowrap">Estado</TableHead>
                            <TableHead class="whitespace-nowrap text-right">Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <!-- Estado vacío -->
                        <TableRow v-if="notificationsList.length === 0 && !isLoading">
                            <TableCell colspan="7" class="py-12">
                                <div class="flex flex-col items-center justify-center text-muted-foreground">
                                    <Inbox class="h-12 w-12 mb-3 opacity-50" />
                                    <p class="text-sm font-medium">No hay notificaciones</p>
                                    <p class="text-xs mt-1">
                                        {{ hasActiveFilters ? 'No se encontraron notificaciones con los filtros seleccionados.' : 'Las notificaciones del sistema aparecerán aquí.' }}
                                    </p>
                                </div>
                            </TableCell>
                        </TableRow>
                        
                        <!-- Loading -->
                        <TableRow v-if="isLoading">
                            <TableCell colspan="7" class="py-12">
                                <div class="flex items-center justify-center text-muted-foreground">
                                    <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-primary mr-3"></div>
                                    <span class="text-sm">Cargando notificaciones...</span>
                                </div>
                            </TableCell>
                        </TableRow>
                        
                        <!-- Notificaciones -->
                        <TableRow 
                            v-for="notification in notificationsList" 
                            :key="notification.id"
                            :class="[
                                'text-center transition-colors',
                                !notification.is_read ? 'bg-primary/5 hover:bg-primary/10' : 'hover:bg-muted/50',
                            ]"
                        >
                            <TableCell class="whitespace-nowrap">
                                {{ formatDate(notification.created_at) }}
                            </TableCell>
                            <TableCell class="whitespace-nowrap">
                                {{ notification.data?.area ?? notification.area ?? '—' }}
                            </TableCell>
                            <TableCell class="whitespace-nowrap">
                                {{ notification.data?.user_name ?? notification.user_name ?? '—' }}
                            </TableCell>
                            <TableCell class="whitespace-nowrap">
                                <Badge v-if="notification.module" variant="secondary" class="text-xs">
                                    {{ notification.module }}
                                </Badge>
                                <span v-else class="text-muted-foreground text-xs">—</span>
                            </TableCell>
                            <TableCell class="text-left align-top">
                                <div class="min-w-0">
                                    <span class="line-clamp-1 text-sm font-medium" :class="{ 'text-primary': !notification.is_read }">
                                        {{ notification.title ?? 'Sin título' }}
                                    </span>
                                    <span class="line-clamp-2 text-xs text-muted-foreground break-words">
                                        {{ notification.body ?? '' }}
                                    </span>
                                </div>
                            </TableCell>
                            <TableCell class="whitespace-nowrap">
                                <TableStatusBadge :label="notification.is_read ? 'Leída' : 'No leída'" :tone="notification.is_read ? 'neutral' : 'sky'" />
                            </TableCell>
                            <TableCell class="text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <Button
                                        v-if="notification.url"
                                        @click="handleNotificationClick(notification)"
                                        variant="ghost"
                                        size="sm"
                                        class="h-8 w-8 p-0"
                                        title="Ver detalle"
                                    >
                                        <ExternalLink class="h-4 w-4" />
                                    </Button>
                                    <Button
                                        v-if="!notification.is_read"
                                        @click="markAsRead(notification)"
                                        variant="ghost"
                                        size="sm"
                                        class="h-8 w-8 p-0"
                                        title="Marcar como leída"
                                    >
                                        <Check class="h-4 w-4" />
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <!-- Paginación -->
                <div v-if="totalItems > 0" class="border-t px-4 py-3">
                    <Pagination
                        class="flex justify-center"
                        v-slot="{ page }"
                        :items-per-page="itemsPerPage"
                        :total="totalItems"
                        :sibling-count="1"
                        show-edges
                        :default-page="currentPage"
                        @page-changed="fetchNotifications"
                    >
                        <PaginationList v-slot="{ items }" class="flex items-center gap-1">
                            <PaginationFirst />
                            <PaginationPrev />

                            <template v-for="(item, index) in items">
                                <PaginationListItem v-if="item.type === 'page'" :key="index" :value="item.value" as-child>
                                    <Button class="h-8 w-8 p-0" :variant="item.value === page ? 'default' : 'outline'">
                                        {{ item.value }}
                                    </Button>
                                </PaginationListItem>
                                <PaginationEllipsis v-else :key="item.type" :index="index" />
                            </template>

                            <PaginationNext />
                            <PaginationLast />
                        </PaginationList>
                    </Pagination>
                </div>
            </Card>
        </div>
    </AppLayout>
</template>
