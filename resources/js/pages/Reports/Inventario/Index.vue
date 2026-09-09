<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import ServerPagination from '@/components/common/ServerPagination.vue';
import type { BreadcrumbItem } from '@/domain/shared';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import Input from '@/components/ui/input/Input.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { FileDown, Package, PackageCheck, PackageX, Search } from 'lucide-vue-next';
import { ref, watch, onMounted, nextTick } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Informes', href: '/informes' },
    { title: 'Inventario', href: '/informes/inventario' },
];

const search = ref('');
const currentPage = ref(1);
const itemsPerPage = ref(15);
const totalItems = ref(0);
const isLoading = ref(false);
const isPrinting = ref(false);
const isPrintLoading = ref(false);
const printItems = ref<any[]>([]);
const printDate = ref('');

const items = ref<any[]>([]);
const summary = ref({ total_items: 0, con_stock: 0, sin_stock: 0 });
const sortBy = ref<string>('');
const sortDir = ref<'asc' | 'desc'>('desc');

async function fetchData() {
    isLoading.value = true;
    try {
        const { data } = await axios.get('/informes/inventario/data', {
            params: {
                search: search.value || undefined,
                page: currentPage.value,
                per_page: itemsPerPage.value,
                sort_by: sortBy.value || undefined,
                sort_direction: sortBy.value ? sortDir.value : undefined,
            },
        });
        items.value = data.data.data;
        totalItems.value = data.data.total;
        summary.value = data.summary;
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

let searchTimeout: ReturnType<typeof setTimeout>;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => { currentPage.value = 1; fetchData(); }, 350);
});

onMounted(fetchData);

async function exportPdf() {
    isPrintLoading.value = true;
    try {
        const { data } = await axios.get('/informes/inventario/data', {
            params: { search: search.value || undefined, all: true },
        });
        printItems.value = data.data;
        printDate.value = new Date().toLocaleString('es-CO', { dateStyle: 'long', timeStyle: 'short' });
        isPrinting.value = true;
        await nextTick();
        window.print();
        window.addEventListener('afterprint', () => { isPrinting.value = false; }, { once: true });
    } finally {
        isPrintLoading.value = false;
    }
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Informe Inventario" />

        <div class="print-only print-header">
            <img src="/images/olamtex.png" alt="Logo" class="print-logo" onerror="this.style.display='none'" />
            <div class="print-header-center">
                <div class="print-title">Informe Inventario</div>
                <div class="print-subtitle">Stock por Bodega</div>
            </div>
            <div class="print-header-right">
                <div>Generado: {{ printDate }}</div>
                <div>Total registros: {{ printItems.length }}</div>
            </div>
        </div>

        <div class="flex flex-col gap-6 p-6">
            <div class="no-print flex items-start justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Informe Inventario</h1>
                    <p class="mt-1 text-sm text-muted-foreground">Stock actual por bodega e ítem.</p>
                </div>
                <Button variant="outline" size="sm" :disabled="isPrintLoading" @click="exportPdf">
                    <FileDown class="mr-2 size-4" />
                    {{ isPrintLoading ? 'Cargando...' : 'Imprimir / PDF' }}
                </Button>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                            <Package class="size-4 no-print" /> Total ítems
                        </CardTitle>
                    </CardHeader>
                    <CardContent><span class="text-2xl font-bold">{{ summary.total_items }}</span></CardContent>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                            <PackageCheck class="size-4 no-print" /> Con stock
                        </CardTitle>
                    </CardHeader>
                    <CardContent><span class="text-2xl font-bold">{{ summary.con_stock }}</span></CardContent>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                            <PackageX class="size-4 no-print" /> Sin stock
                        </CardTitle>
                    </CardHeader>
                    <CardContent><span class="text-2xl font-bold">{{ summary.sin_stock }}</span></CardContent>
                </Card>
            </div>

            <div class="no-print flex justify-end">
                <div class="relative w-full sm:w-64">
                    <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="search" placeholder="Buscar ítem..." class="pl-9" />
                </div>
            </div>

            <Card>
                <CardContent class="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Ítem</TableHead>
                                <TableHead>Bodega</TableHead>
                                <TableHead class="cursor-pointer select-none whitespace-nowrap" @click="toggleSort('quantity')">
                                    <span class="inline-flex items-center gap-1">
                                        Cantidad
                                        <span class="text-muted-foreground">
                                            <template v-if="sortBy === 'quantity'">{{ sortDir === 'asc' ? '↑' : '↓' }}</template>
                                            <template v-else><span class="opacity-30">↕</span></template>
                                        </span>
                                    </span>
                                </TableHead>
                                <TableHead class="cursor-pointer select-none whitespace-nowrap" @click="toggleSort('stock_min')">
                                    <span class="inline-flex items-center gap-1">
                                        Stock mín.
                                        <span class="text-muted-foreground">
                                            <template v-if="sortBy === 'stock_min'">{{ sortDir === 'asc' ? '↑' : '↓' }}</template>
                                            <template v-else><span class="opacity-30">↕</span></template>
                                        </span>
                                    </span>
                                </TableHead>
                                <TableHead class="cursor-pointer select-none whitespace-nowrap" @click="toggleSort('stock_max')">
                                    <span class="inline-flex items-center gap-1">
                                        Stock máx.
                                        <span class="text-muted-foreground">
                                            <template v-if="sortBy === 'stock_max'">{{ sortDir === 'asc' ? '↑' : '↓' }}</template>
                                            <template v-else><span class="opacity-30">↕</span></template>
                                        </span>
                                    </span>
                                </TableHead>
                                <TableHead class="cursor-pointer select-none whitespace-nowrap" @click="toggleSort('weighted_average_price')">
                                    <span class="inline-flex items-center gap-1">
                                        Costo prom.
                                        <span class="text-muted-foreground">
                                            <template v-if="sortBy === 'weighted_average_price'">{{ sortDir === 'asc' ? '↑' : '↓' }}</template>
                                            <template v-else><span class="opacity-30">↕</span></template>
                                        </span>
                                    </span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <template v-if="isLoading && !isPrinting">
                                <TableRow v-for="n in 8" :key="n">
                                    <TableCell v-for="c in 6" :key="c"><Skeleton class="h-4 w-full" /></TableCell>
                                </TableRow>
                            </template>
                            <template v-else-if="(isPrinting ? printItems : items).length === 0">
                                <TableRow>
                                    <TableCell colspan="6" class="py-10 text-center text-sm text-muted-foreground">No hay ítems que mostrar.</TableCell>
                                </TableRow>
                            </template>
                            <template v-else>
                                <TableRow v-for="item in (isPrinting ? printItems : items)" :key="item.id">
                                    <TableCell class="font-medium">{{ item.item?.name ?? '—' }}</TableCell>
                                    <TableCell>{{ item.warehouse?.name ?? '—' }}</TableCell>
                                    <TableCell>{{ Number(item.quantity).toLocaleString('es-CO') }}</TableCell>
                                    <TableCell>{{ item.stock_min ?? '—' }}</TableCell>
                                    <TableCell>{{ item.stock_max ?? '—' }}</TableCell>
                                    <TableCell>{{ item.weighted_average_price ? '$' + Number(item.weighted_average_price).toLocaleString('es-CO') : '—' }}</TableCell>
                                </TableRow>
                            </template>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <ServerPagination class="no-print" v-model:current-page="currentPage" v-model:page-size="itemsPerPage" :total-items="totalItems" />
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
}
</style>

<style scoped>
.print-only { display: none; }
.print-header { align-items: center; justify-content: space-between; padding: 12px 24px 8px; border-bottom: 2px solid #1e3d70; margin-bottom: 16px; gap: 16px; }
.print-logo { max-height: 40px; max-width: 120px; object-fit: contain; }
.print-header-center { text-align: center; flex: 1; }
.print-title { font-size: 15pt; font-weight: 700; color: #1e3d70; }
.print-subtitle { font-size: 10pt; color: #555; margin-top: 2px; }
.print-header-right { text-align: right; font-size: 8pt; color: #666; white-space: nowrap; }
</style>
