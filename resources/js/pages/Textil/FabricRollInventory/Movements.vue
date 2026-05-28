<script setup lang="ts">
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import InputDate from '@/components/InputDate.vue';
import SelectField from '@/components/SelectField.vue';
import ServerPagination from '@/components/common/ServerPagination.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/AppLayout.vue';
import { uiPatterns } from '@/lib/ui-patterns';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { onMounted, ref, watch } from 'vue';

const breadcrumbs = [
    { title: 'Inventario Telas por Rollo', href: '/kardex-telas/inventario-rollos' },
    { title: 'Movimientos de Rollos', href: '/kardex-telas/inventario-rollos/movimientos' },
];

// ─── Tab 1: Historial de ajustes ──────────────────────────────────────────────

type RollAdjustment = {
    id: number;
    fabric_roll_id: number;
    type: string;
    quantity_before: string;
    quantity_after: string;
    partial_before: string | null;
    partial_after: string | null;
    reason: string;
    reference: string | null;
    created_at: string;
    roll: { id: number; roll_number: string } | null;
    user: { id: number; name: string } | null;
};

const adjustments = ref<RollAdjustment[]>([]);
const adjTotal = ref(0);
const adjPage = ref(1);
const adjPerPage = ref(20);
const adjLoading = ref(false);
const adjRollNumber = ref('');
const adjType = ref<string | null>(null);
const adjFrom = ref<string | null>(null);
const adjTo = ref<string | null>(null);

const adjTypeOptions = [
    { id: 'entrada', name: 'Entrada' },
    { id: 'salida', name: 'Salida' },
    { id: 'correction', name: 'Corrección' },
    { id: 'import_update', name: 'Importación' },
];

const adjTypeBadge: Record<string, string> = {
    entrada: 'bg-green-100 text-green-800 dark:bg-green-950/30 dark:text-green-400',
    salida: 'bg-red-100 text-red-800 dark:bg-red-950/30 dark:text-red-400',
    correction: 'bg-blue-100 text-blue-800 dark:bg-blue-950/30 dark:text-blue-400',
    import_update: 'bg-purple-100 text-purple-800 dark:bg-purple-950/30 dark:text-purple-400',
};

const adjTypeLabel: Record<string, string> = {
    entrada: 'Entrada',
    salida: 'Salida',
    correction: 'Corrección',
    import_update: 'Importación',
};

async function fetchAdjustments(page = 1) {
    adjLoading.value = true;
    try {
        const params: Record<string, any> = { page, per_page: adjPerPage.value };
        if (adjRollNumber.value.trim()) params.roll_number = adjRollNumber.value.trim();
        if (adjType.value) params.type = adjType.value;
        if (adjFrom.value) params.from = adjFrom.value;
        if (adjTo.value) params.to = adjTo.value;

        const { data } = await axios.get('/kardex-telas/reportes/historial-ajustes', { params });
        adjustments.value = data.data ?? [];
        adjTotal.value = data.total ?? adjustments.value.length;
        adjPage.value = data.current_page ?? 1;
    } catch {
        adjustments.value = [];
    } finally {
        adjLoading.value = false;
    }
}

watch([adjRollNumber, adjType, adjFrom, adjTo], () => fetchAdjustments(1));

// ─── Tab 2: Movimientos kardex (inventario) ───────────────────────────────────

type InventoryMovement = {
    id: number;
    type: string;
    quantity: string | number;
    unit_price: string | number;
    new_stock: string | number;
    notes: string | null;
    date: string;
    item_name: string | null;
    warehouse_name: string | null;
    document_number: string | null;
    display_document_number: string | null;
    user_name: string | null;
};

const movements = ref<InventoryMovement[]>([]);
const movTotal = ref(0);
const movPage = ref(1);
const movPerPage = ref(20);
const movLoading = ref(false);
const movSearch = ref('');
const movType = ref<string | null>(null);
const movDateFrom = ref<string | null>(null);
const movDateTo = ref<string | null>(null);
const movSortBy = ref('date');
const movSortDir = ref<'asc' | 'desc'>('desc');

const movTypeOptions = [
    { id: 'entrada', name: 'Entrada' },
    { id: 'salida', name: 'Salida' },
    { id: 'ajuste', name: 'Ajuste' },
];

const movTypeBadge: Record<string, string> = {
    entrada: 'bg-green-100 text-green-800 dark:bg-green-950/30 dark:text-green-400',
    salida: 'bg-red-100 text-red-800 dark:bg-red-950/30 dark:text-red-400',
    ajuste: 'bg-blue-100 text-blue-800 dark:bg-blue-950/30 dark:text-blue-400',
};

const movTypeLabel: Record<string, string> = {
    entrada: 'Entrada',
    salida: 'Salida',
    ajuste: 'Ajuste',
};

const numFmt = new Intl.NumberFormat('es-CO', { minimumFractionDigits: 0, maximumFractionDigits: 4 });
const currFmt = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 });

function fmtNum(v: string | number | null | undefined) {
    if (v === null || v === undefined || v === '') return '-';
    const n = Number(v);
    return Number.isNaN(n) ? String(v) : numFmt.format(n);
}

function fmtCurr(v: string | number | null | undefined) {
    if (v === null || v === undefined || v === '') return '-';
    const n = Number(v);
    return Number.isNaN(n) ? String(v) : currFmt.format(n);
}

function fmtDate(v: string | null | undefined) {
    if (!v) return '-';
    const d = new Date(v);
    return Number.isNaN(d.getTime()) ? String(v) : d.toLocaleDateString('es-CO');
}

function toggleMovSort(col: string) {
    if (movSortBy.value === col) {
        movSortDir.value = movSortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        movSortBy.value = col;
        movSortDir.value = 'desc';
    }
    fetchMovements(1);
}

async function fetchMovements(page = 1) {
    movLoading.value = true;
    try {
        const params: Record<string, any> = {
            page,
            per_page: movPerPage.value,
            sort_by: movSortBy.value,
            sort_direction: movSortDir.value,
            fabric_items_only: 1,
        };
        if (movSearch.value.trim()) params.search = movSearch.value.trim();
        if (movType.value) params.type = movType.value;
        if (movDateFrom.value) params.date_from = movDateFrom.value;
        if (movDateTo.value) params.date_to = movDateTo.value;

        const { data } = await axios.get('/movimientos-inventario/data', { params });
        movements.value = data.data ?? [];
        movTotal.value = data.total ?? movements.value.length;
        movPage.value = data.current_page ?? 1;
    } catch {
        movements.value = [];
    } finally {
        movLoading.value = false;
    }
}

watch([movSearch, movType, movDateFrom, movDateTo], () => fetchMovements(1));

onMounted(() => {
    fetchAdjustments(1);
    fetchMovements(1);
});
</script>

<template>
    <Head title="Movimientos de Rollos" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="relative flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <div class="flex flex-col gap-1">
                <h1 :class="uiPatterns.typography.pageTitle">Movimientos de rollos</h1>
                <p :class="uiPatterns.typography.pageSubtitle">Historial de ajustes y movimientos de inventario de telas.</p>
            </div>

            <Tabs default-value="adjustments" class="flex flex-1 flex-col gap-4">
                <TabsList class="w-fit">
                    <TabsTrigger value="adjustments">Historial de ajustes</TabsTrigger>
                    <TabsTrigger value="movements">Movimientos kardex</TabsTrigger>
                </TabsList>

                <!-- ── Tab 1: Historial de ajustes ─────────────────────────── -->
                <TabsContent value="adjustments" class="flex flex-col gap-4">
                    <FilterBar>
                        <FilterField label="N° Rollo" :active="adjRollNumber.trim().length > 0" class="min-w-[160px] p-0">
                            <input
                                v-model="adjRollNumber"
                                placeholder="N° Rollo"
                                class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm focus:border-primary focus:outline-none"
                            />
                        </FilterField>
                        <FilterField label="Tipo" :active="adjType != null" class="min-w-[160px] p-0">
                            <SelectField
                                v-model="adjType"
                                :options="adjTypeOptions"
                                placeholder="Todos los tipos"
                                clearable
                            />
                        </FilterField>
                        <FilterField label="Desde" :active="adjFrom != null" class="min-w-[160px] p-0">
                            <InputDate v-model="adjFrom" placeholder="Desde" />
                        </FilterField>
                        <FilterField label="Hasta" :active="adjTo != null" class="min-w-[160px] p-0">
                            <InputDate v-model="adjTo" placeholder="Hasta" />
                        </FilterField>
                    </FilterBar>

                    <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <div class="overflow-x-auto">
                            <Table class="min-w-full text-[0.9rem]">
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Rollo</TableHead>
                                        <TableHead>Tipo</TableHead>
                                        <TableHead>Cant. antes</TableHead>
                                        <TableHead>Cant. después</TableHead>
                                        <TableHead>Parc. antes</TableHead>
                                        <TableHead>Parc. después</TableHead>
                                        <TableHead>Razón</TableHead>
                                        <TableHead>Referencia</TableHead>
                                        <TableHead>Usuario</TableHead>
                                        <TableHead>Fecha</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <template v-if="adjLoading">
                                        <TableRow v-for="i in 5" :key="`adj-sk-${i}`">
                                            <TableCell v-for="col in 10" :key="`adj-sk-${i}-${col}`" class="px-4 py-3">
                                                <Skeleton class="h-4 w-full" />
                                            </TableCell>
                                        </TableRow>
                                    </template>
                                    <TableRow v-else-if="adjustments.length === 0">
                                        <TableCell colspan="10" class="py-8 text-center text-sm text-muted-foreground">
                                            No hay registros de ajustes.
                                        </TableCell>
                                    </TableRow>
                                    <TableRow v-for="adj in adjustments" v-else :key="adj.id" class="border-b">
                                        <TableCell class="text-sm">{{ adj.roll?.roll_number ?? '-' }}</TableCell>
                                        <TableCell>
                                            <span
                                                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                                :class="adjTypeBadge[adj.type] ?? 'bg-muted text-muted-foreground'"
                                            >
                                                {{ adjTypeLabel[adj.type] ?? adj.type }}
                                            </span>
                                        </TableCell>
                                        <TableCell class="text-sm">{{ fmtNum(adj.quantity_before) }}</TableCell>
                                        <TableCell class="text-sm">{{ fmtNum(adj.quantity_after) }}</TableCell>
                                        <TableCell class="text-sm">{{ fmtNum(adj.partial_before) }}</TableCell>
                                        <TableCell class="text-sm">{{ fmtNum(adj.partial_after) }}</TableCell>
                                        <TableCell class="max-w-[200px] truncate text-sm" :title="adj.reason">{{ adj.reason }}</TableCell>
                                        <TableCell class="text-sm">{{ adj.reference ?? '-' }}</TableCell>
                                        <TableCell class="text-sm">{{ adj.user?.name ?? '-' }}</TableCell>
                                        <TableCell class="whitespace-nowrap text-sm">{{ fmtDate(adj.created_at) }}</TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                        <div class="mt-4">
                            <ServerPagination
                                v-model:current-page="adjPage"
                                v-model:page-size="adjPerPage"
                                :total-items="adjTotal"
                                @fetch-data="fetchAdjustments"
                            />
                        </div>
                    </div>
                </TabsContent>

                <!-- ── Tab 2: Movimientos kardex ───────────────────────────── -->
                <TabsContent value="movements" class="flex flex-col gap-4">
                    <FilterBar>
                        <FilterField label="Buscar" :active="movSearch.trim().length > 0" class="min-w-[200px] flex-1 p-0">
                            <input
                                v-model="movSearch"
                                placeholder="Buscar por ítem, documento..."
                                class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm focus:border-primary focus:outline-none"
                            />
                        </FilterField>
                        <FilterField label="Tipo" :active="movType != null" class="min-w-[160px] p-0">
                            <SelectField
                                v-model="movType"
                                :options="movTypeOptions"
                                placeholder="Todos los tipos"
                                clearable
                            />
                        </FilterField>
                        <FilterField label="Desde" :active="movDateFrom != null" class="min-w-[160px] p-0">
                            <InputDate v-model="movDateFrom" placeholder="Desde" />
                        </FilterField>
                        <FilterField label="Hasta" :active="movDateTo != null" class="min-w-[160px] p-0">
                            <InputDate v-model="movDateTo" placeholder="Hasta" />
                        </FilterField>
                    </FilterBar>

                    <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <div class="overflow-x-auto">
                            <Table class="min-w-full text-[0.9rem]">
                                <TableHeader>
                                    <TableRow>
                                        <TableHead class="cursor-pointer select-none" @click="toggleMovSort('date')">
                                            Fecha {{ movSortBy === 'date' ? (movSortDir === 'asc' ? '↑' : '↓') : '↕' }}
                                        </TableHead>
                                        <TableHead>Tipo</TableHead>
                                        <TableHead>Ítem</TableHead>
                                        <TableHead>Bodega</TableHead>
                                        <TableHead class="cursor-pointer select-none" @click="toggleMovSort('quantity')">
                                            Cantidad {{ movSortBy === 'quantity' ? (movSortDir === 'asc' ? '↑' : '↓') : '↕' }}
                                        </TableHead>
                                        <TableHead>Precio unit.</TableHead>
                                        <TableHead>Nuevo stock</TableHead>
                                        <TableHead>Documento</TableHead>
                                        <TableHead>Notas</TableHead>
                                        <TableHead>Usuario</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <template v-if="movLoading">
                                        <TableRow v-for="i in 5" :key="`mov-sk-${i}`">
                                            <TableCell v-for="col in 10" :key="`mov-sk-${i}-${col}`" class="px-4 py-3">
                                                <Skeleton class="h-4 w-full" />
                                            </TableCell>
                                        </TableRow>
                                    </template>
                                    <TableRow v-else-if="movements.length === 0">
                                        <TableCell colspan="10" class="py-8 text-center text-sm text-muted-foreground">
                                            No hay movimientos de telas.
                                        </TableCell>
                                    </TableRow>
                                    <TableRow v-for="mov in movements" v-else :key="mov.id" class="border-b">
                                        <TableCell class="whitespace-nowrap text-sm">{{ fmtDate(mov.date) }}</TableCell>
                                        <TableCell>
                                            <span
                                                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                                :class="movTypeBadge[mov.type] ?? 'bg-muted text-muted-foreground'"
                                            >
                                                {{ movTypeLabel[mov.type] ?? mov.type }}
                                            </span>
                                        </TableCell>
                                        <TableCell class="max-w-[200px] truncate text-sm" :title="mov.item_name ?? ''">{{ mov.item_name ?? '-' }}</TableCell>
                                        <TableCell class="text-sm">{{ mov.warehouse_name ?? '-' }}</TableCell>
                                        <TableCell class="text-right text-sm">{{ fmtNum(mov.quantity) }}</TableCell>
                                        <TableCell class="text-right text-sm">{{ fmtCurr(mov.unit_price) }}</TableCell>
                                        <TableCell class="text-right text-sm">{{ fmtNum(mov.new_stock) }}</TableCell>
                                        <TableCell class="text-sm">{{ mov.display_document_number ?? mov.document_number ?? '-' }}</TableCell>
                                        <TableCell class="max-w-[180px] truncate text-sm" :title="mov.notes ?? ''">{{ mov.notes ?? '-' }}</TableCell>
                                        <TableCell class="text-sm">{{ mov.user_name ?? '-' }}</TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                        <div class="mt-4">
                            <ServerPagination
                                v-model:current-page="movPage"
                                v-model:page-size="movPerPage"
                                :total-items="movTotal"
                                @fetch-data="fetchMovements"
                            />
                        </div>
                    </div>
                </TabsContent>
            </Tabs>
        </div>
    </AppLayout>
</template>
