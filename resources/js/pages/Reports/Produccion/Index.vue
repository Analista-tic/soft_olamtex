<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import ServerPagination from '@/components/common/ServerPagination.vue';
import type { BreadcrumbItem } from '@/domain/shared';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import Input from '@/components/ui/input/Input.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import {
    ChevronRight, ClipboardList, CheckCircle2,
    DollarSign, FileDown, Layers, Package, PlayCircle, Search,
} from 'lucide-vue-next';
import { ref, watch, onMounted, nextTick, computed } from 'vue';

const breadcrumbs = computed<BreadcrumbItem[]>(() => {
    const base: BreadcrumbItem[] = [
        { title: 'Informes', href: '/informes' },
        { title: 'Producción', href: '/informes/produccion' },
    ];
    if (view.value === 'detail' && selectedOrder.value?.op_code) {
        base.push({ title: selectedOrder.value.op_code, href: '' });
    }
    return base;
});

type View = 'list' | 'detail';
const view = ref<View>('list');

// ─── List state ───────────────────────────────────────
const search   = ref('');
const dateFrom = ref('');
const dateTo   = ref('');
const currentPage  = ref(1);
const itemsPerPage = ref(15);
const totalItems   = ref(0);
const isLoading    = ref(false);
const orders  = ref<any[]>([]);
const summary = ref({ total: 0, activas: 0, completadas: 0 });
const sortBy = ref<string>('');
const sortDir = ref<'asc' | 'desc'>('desc');

// ─── Detail state ─────────────────────────────────────
const selectedOrder  = ref<any>(null);
const detailLines    = ref<any[]>([]);
const isDetailLoading = ref(false);
const detailFilter   = ref<'all' | 'tela' | 'insumo'>('all');

// ─── Print state ──────────────────────────────────────
const isPrinting      = ref(false);
const isPrintLoading  = ref(false);
const printDate       = ref('');
const printConsolidado = ref<any[]>([]);

const filteredLines = computed(() => {
    if (detailFilter.value === 'all') return detailLines.value;
    const t = detailFilter.value === 'tela' ? 'Tela' : 'Insumo';
    return detailLines.value.filter((l: any) => l.type === t);
});

const lineTotal = computed(() =>
    filteredLines.value.reduce((s: number, l: any) => s + Number(l.total ?? 0), 0),
);

// KPI cards del detalle: calculados desde las líneas (los campos en DB están vacíos)
const detailRawMaterial = computed(() =>
    detailLines.value.filter((l: any) => l.type === 'Tela').reduce((s: number, l: any) => s + Number(l.total ?? 0), 0),
);
const detailSupplies = computed(() =>
    detailLines.value.filter((l: any) => l.type === 'Insumo').reduce((s: number, l: any) => s + Number(l.total ?? 0), 0),
);
const detailGrandTotal = computed(() =>
    detailLines.value.reduce((s: number, l: any) => s + Number(l.total ?? 0), 0),
);

const consolidadoGrandTotal = computed(() =>
    printConsolidado.value.reduce((s: number, o: any) => s + Number(o.subtotal ?? 0), 0),
);

// ─── List fetch ───────────────────────────────────────
async function fetchData() {
    isLoading.value = true;
    try {
        const { data } = await axios.get('/informes/produccion/data', {
            params: {
                search:    search.value    || undefined,
                date_from: dateFrom.value  || undefined,
                date_to:   dateTo.value    || undefined,
                page:      currentPage.value,
                per_page:  itemsPerPage.value,
                sort_by: sortBy.value || undefined,
                sort_direction: sortBy.value ? sortDir.value : undefined,
            },
        });
        orders.value     = data.data.data;
        totalItems.value = data.data.total;
        summary.value    = data.summary;
    } finally {
        isLoading.value = false;
    }
}

function toggleSort(column: string) {
    if (sortBy.value === column) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = column;
        sortDir.value = 'desc';
    }

    currentPage.value = 1;
    fetchData();
}

watch([currentPage, itemsPerPage], fetchData);
watch([dateFrom, dateTo], () => { currentPage.value = 1; fetchData(); });

let searchTimeout: ReturnType<typeof setTimeout>;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => { currentPage.value = 1; fetchData(); }, 350);
});

onMounted(fetchData);

// ─── Detail ───────────────────────────────────────────
async function openDetail(order: any) {
    selectedOrder.value   = order;
    view.value            = 'detail';
    detailFilter.value    = 'all';
    detailLines.value     = [];
    isDetailLoading.value = true;
    try {
        const { data } = await axios.get('/informes/produccion/detalle', {
            params: { op_id: order.id },
        });
        detailLines.value   = data.lines;
        selectedOrder.value = { ...order, ...data.order };
    } finally {
        isDetailLoading.value = false;
    }
}

// ─── Export / Print ───────────────────────────────────
async function exportPdf() {
    isPrintLoading.value = true;
    try {
        if (view.value === 'list') {
            const { data } = await axios.get('/informes/produccion/consolidado', {
                params: {
                    search:    search.value   || undefined,
                    date_from: dateFrom.value || undefined,
                    date_to:   dateTo.value   || undefined,
                },
            });
            printConsolidado.value = data.data;
        }
        printDate.value  = new Date().toLocaleString('es-CO', { dateStyle: 'long', timeStyle: 'short' });
        isPrinting.value = true;
        await nextTick();
        window.print();
        window.addEventListener('afterprint', () => { isPrinting.value = false; }, { once: true });
    } finally {
        isPrintLoading.value = false;
    }
}

// ─── Helpers ──────────────────────────────────────────
const UUID_RE = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
const fmtRef = (ref: string) => {
    if (!ref || ref === '—') return '—';
    return UUID_RE.test(ref) ? ref.substring(0, 8) : ref;
};

const statusBadge = (status: string) => {
    const map: Record<string, { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' }> = {
        active:    { label: 'Activa',     variant: 'default' },
        completed: { label: 'Completada', variant: 'secondary' },
        cancelled: { label: 'Cancelada',  variant: 'destructive' },
    };
    return map[status] ?? { label: status, variant: 'outline' };
};

const cop = (n: number | string | null | undefined) => {
    if (n == null || n === '') return '—';
    const num = Number(n);
    if (isNaN(num)) return '—';
    return '$' + num.toLocaleString('es-CO', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
};

const fmtQty = (n: number | string | null) => {
    if (n == null) return '—';
    return Number(n).toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 4 });
};

const orderRef = (order: any) =>
    order?.reference ||
    order?.product_sheet?.sample_reference ||
    order?.product_sheet?.product_reference ||
    '—';

const dateLabel = computed(() => {
    if (dateFrom.value && dateTo.value) return `${dateFrom.value} al ${dateTo.value}`;
    if (dateFrom.value) return `Desde ${dateFrom.value}`;
    if (dateTo.value)   return `Hasta ${dateTo.value}`;
    return 'Todas las fechas';
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Informe Producción" />

        <!-- ── Print header: lista consolidada ── -->
        <div v-if="view === 'list'" class="print-only print-header">
            <img src="/images/olamtex.png" alt="Logo" class="print-logo" onerror="this.style.display='none'" />
            <div class="print-header-center">
                <div class="print-title">Informe de Consumo de Producción</div>
                <div class="print-subtitle">{{ dateLabel }}</div>
            </div>
            <div class="print-header-right">
                <div>Generado: {{ printDate }}</div>
                <div>Órdenes: {{ printConsolidado.length }}</div>
            </div>
        </div>

        <!-- ── Print header: detalle individual ── -->
        <div v-if="view === 'detail'" class="print-only print-header">
            <img src="/images/olamtex.png" alt="Logo" class="print-logo" onerror="this.style.display='none'" />
            <div class="print-header-center">
                <div class="print-title">Informe de Consumo</div>
                <div class="print-subtitle">
                    {{ selectedOrder?.op_code }} — {{ orderRef(selectedOrder) }} — {{ selectedOrder?.units }} unidades
                </div>
            </div>
            <div class="print-header-right">
                <div>Generado: {{ printDate }}</div>
                <div>Líneas: {{ filteredLines.length }}</div>
            </div>
        </div>

        <div class="flex flex-col gap-6 p-6">

            <!-- ══════════════ VISTA LISTA ══════════════ -->
            <template v-if="view === 'list'">

                <!-- Encabezado pantalla -->
                <div class="no-print flex items-start justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight">Informe Producción</h1>
                        <p class="mt-1 text-sm text-muted-foreground">Órdenes de producción y costos de consumo.</p>
                    </div>
                    <Button variant="outline" size="sm" :disabled="isPrintLoading" @click="exportPdf">
                        <FileDown class="mr-2 size-4" />
                        {{ isPrintLoading ? 'Cargando...' : 'Imprimir / PDF' }}
                    </Button>
                </div>

                <!-- KPIs -->
                <div class="no-print grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                                <ClipboardList class="size-4" /> Total órdenes
                            </CardTitle>
                        </CardHeader>
                        <CardContent><span class="text-2xl font-bold">{{ summary.total }}</span></CardContent>
                    </Card>
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                                <PlayCircle class="size-4" /> Activas
                            </CardTitle>
                        </CardHeader>
                        <CardContent><span class="text-2xl font-bold">{{ summary.activas }}</span></CardContent>
                    </Card>
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                                <CheckCircle2 class="size-4" /> Completadas
                            </CardTitle>
                        </CardHeader>
                        <CardContent><span class="text-2xl font-bold">{{ summary.completadas }}</span></CardContent>
                    </Card>
                </div>

                <!-- Filtros: fecha + búsqueda -->
                <div class="no-print flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-2">
                        <Input v-model="dateFrom" type="date" class="w-40" />
                        <span class="text-sm text-muted-foreground">—</span>
                        <Input v-model="dateTo" type="date" class="w-40" />
                    </div>
                    <div class="relative w-full sm:w-64">
                        <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input v-model="search" placeholder="Buscar por código OP..." class="pl-9" />
                    </div>
                </div>

                <!-- Tabla de órdenes (pantalla) -->
                <Card class="no-print">
                    <CardContent class="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead class="cursor-pointer select-none whitespace-nowrap" @click="toggleSort('op_code')">
                                        <span class="inline-flex items-center gap-1">
                                            Código OP
                                            <span class="text-muted-foreground">
                                                <template v-if="sortBy === 'op_code'">{{ sortDir === 'asc' ? '↑' : '↓' }}</template>
                                                <template v-else><span class="opacity-30">↕</span></template>
                                            </span>
                                        </span>
                                    </TableHead>
                                    <TableHead>Referencia</TableHead>
                                    <TableHead class="cursor-pointer select-none whitespace-nowrap" @click="toggleSort('season')">
                                        <span class="inline-flex items-center gap-1">
                                            Temporada
                                            <span class="text-muted-foreground">
                                                <template v-if="sortBy === 'season'">{{ sortDir === 'asc' ? '↑' : '↓' }}</template>
                                                <template v-else><span class="opacity-30">↕</span></template>
                                            </span>
                                        </span>
                                    </TableHead>
                                    <TableHead class="cursor-pointer select-none whitespace-nowrap text-right" @click="toggleSort('units')">
                                        <span class="inline-flex items-center justify-end gap-1">
                                            Unidades
                                            <span class="text-muted-foreground">
                                                <template v-if="sortBy === 'units'">{{ sortDir === 'asc' ? '↑' : '↓' }}</template>
                                                <template v-else><span class="opacity-30">↕</span></template>
                                            </span>
                                        </span>
                                    </TableHead>
                                    <TableHead class="text-right">M.P.</TableHead>
                                    <TableHead class="text-right">Insumos</TableHead>
                                    <TableHead class="text-right">Total</TableHead>
                                    <TableHead class="cursor-pointer select-none whitespace-nowrap" @click="toggleSort('status')">
                                        <span class="inline-flex items-center gap-1">
                                            Estado
                                            <span class="text-muted-foreground">
                                                <template v-if="sortBy === 'status'">{{ sortDir === 'asc' ? '↑' : '↓' }}</template>
                                                <template v-else><span class="opacity-30">↕</span></template>
                                            </span>
                                        </span>
                                    </TableHead>
                                    <TableHead class="w-20"></TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <template v-if="isLoading">
                                    <TableRow v-for="n in 8" :key="n">
                                        <TableCell v-for="c in 8" :key="c"><Skeleton class="h-4 w-full" /></TableCell>
                                    </TableRow>
                                </template>
                                <template v-else-if="orders.length === 0">
                                    <TableRow>
                                        <TableCell colspan="9" class="py-10 text-center text-sm text-muted-foreground">
                                            No hay órdenes que mostrar.
                                        </TableCell>
                                    </TableRow>
                                </template>
                                <template v-else>
                                    <TableRow v-for="order in orders" :key="order.id">
                                        <TableCell class="font-mono text-sm font-medium">{{ order.op_code ?? '—' }}</TableCell>
                                        <TableCell>{{ orderRef(order) }}</TableCell>
                                        <TableCell>{{ order.season ?? '—' }}</TableCell>
                                        <TableCell class="text-right">{{ order.units ?? '—' }}</TableCell>
                                        <TableCell class="text-right text-sm">{{ cop(order.technical_sheet?.raw_material_cost) }}</TableCell>
                                        <TableCell class="text-right text-sm">{{ cop(order.technical_sheet?.supplies_cost) }}</TableCell>
                                        <TableCell class="text-right text-sm font-medium">{{ cop(order.technical_sheet?.total_cost) }}</TableCell>
                                        <TableCell>
                                            <Badge :variant="statusBadge(order.status).variant">{{ statusBadge(order.status).label }}</Badge>
                                        </TableCell>
                                        <TableCell>
                                            <Button variant="ghost" size="sm" class="h-7 gap-1 px-2 text-xs" @click="openDetail(order)">
                                                Ver <ChevronRight class="size-3" />
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                </template>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <ServerPagination class="no-print" v-model:current-page="currentPage" v-model:page-size="itemsPerPage" :total-items="totalItems" />

                <!-- ── Bloques de impresión consolidada ── -->
                <template v-if="isPrinting">
                    <template v-if="printConsolidado.length === 0">
                        <p class="py-8 text-center text-sm text-muted-foreground">
                            No hay consumos registrados para el período seleccionado.
                        </p>
                    </template>
                    <template v-else>
                        <div v-for="order in printConsolidado" :key="order.id" class="order-block">
                            <div class="order-block-header">
                                <span class="font-bold">{{ order.op_code }}</span>
                                <span>Ref: {{ order.reference }}</span>
                                <span>Temporada: {{ order.season }}</span>
                                <span>Unidades: {{ order.units }}</span>
                            </div>
                            <table class="consume-table">
                                <thead>
                                    <tr>
                                        <th>Tipo</th>
                                        <th>Referencia</th>
                                        <th>Artículo</th>
                                        <th class="text-right">IVA</th>
                                        <th class="text-right">Cantidad</th>
                                        <th>UOM</th>
                                        <th class="text-right">Precio</th>
                                        <th class="text-right">Total</th>
                                        <th class="text-right">Costo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(line, i) in order.lines" :key="i">
                                        <td>{{ line.type }}</td>
                                        <td class="font-mono text-xs">{{ line.reference }}</td>
                                        <td>{{ line.name }}</td>
                                        <td class="text-right">{{ line.iva }}%</td>
                                        <td class="text-right">{{ fmtQty(line.qty) }}</td>
                                        <td class="text-xs">{{ line.uom }}</td>
                                        <td class="text-right">{{ cop(line.price) }}</td>
                                        <td class="text-right font-medium">{{ cop(line.total) }}</td>
                                        <td class="text-right font-medium">{{ cop(line.total) }}</td>
                                    </tr>
                                    <tr class="subtotal-row">
                                        <td colspan="7" class="text-right font-semibold">Subtotal {{ order.op_code }}:</td>
                                        <td class="text-right font-bold">{{ cop(order.subtotal) }}</td>
                                        <td class="text-right font-bold">{{ cop(order.subtotal) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Total general -->
                        <div class="grand-total-row">
                            <span>TOTAL GENERAL:</span>
                            <span class="font-bold">{{ cop(consolidadoGrandTotal) }}</span>
                        </div>
                    </template>
                </template>

            </template>

            <!-- ══════════════ VISTA DETALLE ══════════════ -->
            <template v-if="view === 'detail'">

                <div class="no-print flex items-start justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight">{{ selectedOrder?.op_code }}</h1>
                        <p class="mt-0.5 text-sm text-muted-foreground">
                            {{ orderRef(selectedOrder) }} — Temporada {{ selectedOrder?.season }} — {{ selectedOrder?.units }} unidades
                        </p>
                    </div>
                    <Button variant="outline" size="sm" :disabled="isPrintLoading" @click="exportPdf">
                        <FileDown class="mr-2 size-4" />
                        {{ isPrintLoading ? 'Cargando...' : 'Imprimir / PDF' }}
                    </Button>
                </div>

                <!-- KPIs costos -->
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                                <Layers class="size-4 no-print" /> Materia prima
                            </CardTitle>
                        </CardHeader>
                        <CardContent><span class="text-xl font-bold">{{ cop(detailRawMaterial) }}</span></CardContent>
                    </Card>
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                                <Package class="size-4 no-print" /> Insumos
                            </CardTitle>
                        </CardHeader>
                        <CardContent><span class="text-xl font-bold">{{ cop(detailSupplies) }}</span></CardContent>
                    </Card>
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                                <ClipboardList class="size-4 no-print" /> Procesos
                            </CardTitle>
                        </CardHeader>
                        <CardContent><span class="text-xl font-bold">{{ cop(selectedOrder?.processes_cost) }}</span></CardContent>
                    </Card>
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                                <DollarSign class="size-4 no-print" /> Total
                            </CardTitle>
                        </CardHeader>
                        <CardContent><span class="text-xl font-bold">{{ cop(detailGrandTotal) }}</span></CardContent>
                    </Card>
                </div>

                <!-- Filtros tipo -->
                <div class="no-print flex gap-2">
                    <Button :variant="detailFilter === 'all'    ? 'default' : 'outline'" size="sm" @click="detailFilter = 'all'">Todo</Button>
                    <Button :variant="detailFilter === 'tela'   ? 'default' : 'outline'" size="sm" @click="detailFilter = 'tela'">Telas</Button>
                    <Button :variant="detailFilter === 'insumo' ? 'default' : 'outline'" size="sm" @click="detailFilter = 'insumo'">Insumos</Button>
                </div>

                <!-- Tabla consumo detalle -->
                <Card class="detail-table">
                    <CardContent class="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Tipo</TableHead>
                                    <TableHead>Referencia</TableHead>
                                    <TableHead>Artículo</TableHead>
                                    <TableHead class="text-right">IVA</TableHead>
                                    <TableHead class="text-right">Cantidad</TableHead>
                                    <TableHead>UOM</TableHead>
                                    <TableHead class="text-right">Precio</TableHead>
                                    <TableHead class="text-right">Total</TableHead>
                                    <TableHead class="text-right">Costo</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <template v-if="isDetailLoading">
                                    <TableRow v-for="n in 6" :key="n">
                                        <TableCell v-for="c in 9" :key="c"><Skeleton class="h-4 w-full" /></TableCell>
                                    </TableRow>
                                </template>
                                <template v-else-if="filteredLines.length === 0">
                                    <TableRow>
                                        <TableCell colspan="9" class="py-10 text-center text-sm text-muted-foreground">
                                            No hay líneas de consumo registradas para esta orden.
                                        </TableCell>
                                    </TableRow>
                                </template>
                                <template v-else>
                                    <TableRow v-for="(line, i) in filteredLines" :key="i">
                                        <TableCell>
                                            <span class="no-print">
                                                <Badge :variant="line.type === 'Tela' ? 'default' : 'secondary'">{{ line.type }}</Badge>
                                            </span>
                                            <span class="print-only">{{ line.type }}</span>
                                        </TableCell>
                                        <TableCell class="font-mono text-xs">{{ fmtRef(line.reference) }}</TableCell>
                                        <TableCell>{{ line.name }}</TableCell>
                                        <TableCell class="text-right">{{ line.iva }}%</TableCell>
                                        <TableCell class="text-right">{{ fmtQty(line.qty) }}</TableCell>
                                        <TableCell class="text-xs text-muted-foreground">{{ line.uom }}</TableCell>
                                        <TableCell class="text-right">{{ cop(line.price) }}</TableCell>
                                        <TableCell class="text-right font-medium">{{ cop(line.total) }}</TableCell>
                                        <TableCell class="text-right font-medium">{{ cop(line.total) }}</TableCell>
                                    </TableRow>
                                    <TableRow class="border-t-2 font-semibold bg-muted/40">
                                        <TableCell colspan="7" class="text-right text-sm">Total:</TableCell>
                                        <TableCell class="text-right">{{ cop(lineTotal) }}</TableCell>
                                        <TableCell class="text-right">{{ cop(lineTotal) }}</TableCell>
                                    </TableRow>
                                </template>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

            </template>

        </div>
    </AppLayout>
</template>

<style>
@page { margin: 10mm; }

@media print {
    [data-sidebar="sidebar"], header.sticky, .no-print { display: none !important; }
    .print-only { display: flex !important; }
    body, html { background: white !important; overflow: hidden !important; width: 100% !important; }
    main { box-shadow: none !important; border-radius: 0 !important; margin: 0 !important; overflow: hidden !important; min-height: unset !important; }
    * { overflow: visible !important; scrollbar-width: none !important; }
    *::-webkit-scrollbar { display: none !important; }

    /* Tabla detalle individual */
    .detail-table table {
        font-size: 8pt !important;
        table-layout: fixed !important;
        width: 100% !important;
    }
    .detail-table th,
    .detail-table td {
        padding: 3px 5px !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        white-space: nowrap !important;
    }
    .detail-table th:nth-child(1), .detail-table td:nth-child(1) { width: 7%; }   /* Tipo */
    .detail-table th:nth-child(2), .detail-table td:nth-child(2) { width: 10%; }  /* Referencia */
    .detail-table th:nth-child(3), .detail-table td:nth-child(3) { width: 28%; }  /* Artículo */
    .detail-table th:nth-child(4), .detail-table td:nth-child(4) { width: 6%; }   /* IVA */
    .detail-table th:nth-child(5), .detail-table td:nth-child(5) { width: 9%; }   /* Cantidad */
    .detail-table th:nth-child(6), .detail-table td:nth-child(6) { width: 5%; }   /* UOM */
    .detail-table th:nth-child(7), .detail-table td:nth-child(7) { width: 12%; }  /* Precio */
    .detail-table th:nth-child(8), .detail-table td:nth-child(8) { width: 12%; }  /* Total */
    .detail-table th:nth-child(9), .detail-table td:nth-child(9) { width: 11%; }  /* Costo */

    /* Estilos de la tabla consolidada en impresión */
    .consume-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 8pt;
        margin-top: 2px;
    }
    .consume-table th {
        background: #f1f5f9;
        padding: 3px 6px;
        text-align: left;
        font-weight: 600;
        border-bottom: 1px solid #cbd5e1;
    }
    .consume-table td {
        padding: 3px 6px;
        border-bottom: 1px solid #e2e8f0;
    }
    .consume-table .text-right { text-align: right; }
    .consume-table .subtotal-row td {
        background: #f8fafc;
        border-top: 2px solid #1e3d70;
        padding-top: 4px;
        padding-bottom: 4px;
    }
    .order-block {
        margin-bottom: 18px;
        break-inside: avoid;
    }
    .order-block-header {
        background: #1e3d70;
        color: white;
        padding: 5px 10px;
        font-size: 9pt;
        display: flex;
        gap: 20px;
        align-items: center;
    }
    .grand-total-row {
        display: flex;
        justify-content: flex-end;
        gap: 32px;
        padding: 8px 12px;
        border-top: 3px solid #1e3d70;
        font-size: 11pt;
        margin-top: 8px;
    }
}
</style>

<style scoped>
.print-only { display: none; }
.print-header { align-items: center; justify-content: space-between; padding: 12px 24px 8px; border-bottom: 2px solid #1e3d70; margin-bottom: 16px; gap: 16px; }
.print-logo { max-height: 40px; max-width: 120px; object-fit: contain; }
.print-header-center { text-align: center; flex: 1; }
.print-title  { font-size: 15pt; font-weight: 700; color: #1e3d70; }
.print-subtitle { font-size: 10pt; color: #555; margin-top: 2px; }
.print-header-right { text-align: right; font-size: 8pt; color: #666; white-space: nowrap; }
</style>
