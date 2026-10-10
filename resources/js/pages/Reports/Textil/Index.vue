<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import ServerPagination from '@/components/common/ServerPagination.vue';
import type { BreadcrumbItem } from '@/domain/shared';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import Input from '@/components/ui/input/Input.vue';
import PillTabs from '@/components/common/PillTabs.vue';
import { Tabs } from '@/components/ui/tabs';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { FileDown, Layers, Ruler, AlertCircle, Search } from 'lucide-vue-next';
import { ref, watch, onMounted, nextTick } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Informes', href: '/informes' },
    { title: 'Textil', href: '/informes/textil' },
];

type ReportTab = 'disponibilidad' | 'sin_movimiento' | 'agotados';

const activeTab = ref<ReportTab>('disponibilidad');
const search = ref('');
const currentPage = ref(1);
const itemsPerPage = ref(15);
const totalItems = ref(0);
const isLoading = ref(false);
const isPrinting = ref(false);
const isPrintLoading = ref(false);
const printRolls = ref<any[]>([]);
const printDate = ref('');

const rolls = ref<any[]>([]);
const summary = ref({ total_rolls: 0, available_meters: 0, no_movement: 0 });
const sortBy = ref<string>('');
const sortDir = ref<'asc' | 'desc'>('desc');

const tabs: { value: ReportTab; label: string }[] = [
    { value: 'disponibilidad', label: 'Disponibilidad' },
    { value: 'sin_movimiento', label: 'Sin movimiento' },
    { value: 'agotados', label: 'Agotados' },
];

const tabLabels: Record<ReportTab, string> = {
    disponibilidad: 'Disponibilidad de Rollos',
    sin_movimiento: 'Rollos Sin Movimiento (+30 días)',
    agotados:       'Rollos Agotados',
};

async function fetchData() {
    isLoading.value = true;
    try {
        const { data } = await axios.get('/informes/textil/data', {
            params: {
                report: activeTab.value,
                search: search.value || undefined,
                page: currentPage.value,
                per_page: itemsPerPage.value,
                sort_by: sortBy.value || undefined,
                sort_direction: sortBy.value ? sortDir.value : undefined,
            },
        });
        rolls.value = data.data.data;
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

watch([activeTab, currentPage, itemsPerPage], () => fetchData());

let searchTimeout: ReturnType<typeof setTimeout>;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        currentPage.value = 1;
        fetchData();
    }, 350);
});

watch(activeTab, () => { currentPage.value = 1; });

onMounted(fetchData);

async function exportPdf() {
    isPrintLoading.value = true;
    try {
        const { data } = await axios.get('/informes/textil/data', {
            params: {
                report: activeTab.value,
                search: search.value || undefined,
                all: true,
            },
        });
        printRolls.value = data.data;
        printDate.value = new Date().toLocaleString('es-CO', { dateStyle: 'long', timeStyle: 'short' });
        isPrinting.value = true;
        await nextTick();
        window.print();
        window.addEventListener('afterprint', () => { isPrinting.value = false; }, { once: true });
    } finally {
        isPrintLoading.value = false;
    }
}

const availableQuantity = (roll: any) =>
    Math.max(0, Number(roll.quantity) + Number(roll.length_adjustment_meters ?? 0) - Number(roll.actual_output));

const approvalBadge = (status: string) => {
    const map: Record<string, { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' }> = {
        approved:         { label: 'Aprobado', variant: 'default' },
        pending_approval: { label: 'Pendiente', variant: 'outline' },
        not_approved:     { label: 'No aprobado', variant: 'destructive' },
    };
    return map[status] ?? { label: status, variant: 'secondary' };
};

const approvalLabel = (status: string) => approvalBadge(status).label;
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Informe Textil" />

        <!-- Encabezado solo visible al imprimir -->
        <div class="print-only print-header">
            <img src="/images/olamtex.png" alt="Logo" class="print-logo" onerror="this.style.display='none'" />
            <div class="print-header-center">
                <div class="print-title">Informe Textil</div>
                <div class="print-subtitle">{{ tabLabels[activeTab] }}</div>
            </div>
            <div class="print-header-right">
                <div>Generado: {{ printDate }}</div>
                <div>Total registros: {{ printRolls.length }}</div>
            </div>
        </div>

        <div class="flex flex-col gap-6 p-6">
            <!-- Encabezado pantalla -->
            <div class="no-print flex items-start justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Informe Textil</h1>
                    <p class="mt-1 text-sm text-muted-foreground">Consulta disponibilidad y estado de rollos de tela.</p>
                </div>
                <Button variant="outline" size="sm" :disabled="isPrintLoading" @click="exportPdf">
                    <FileDown class="mr-2 size-4" />
                    {{ isPrintLoading ? 'Cargando...' : 'Imprimir / PDF' }}
                </Button>
            </div>

            <!-- KPIs -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                            <Layers class="size-4 no-print" /> Total rollos
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <span class="text-2xl font-bold">{{ summary.total_rolls }}</span>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                            <Ruler class="size-4 no-print" /> Metros disponibles
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <span class="text-2xl font-bold">{{ summary.available_meters.toLocaleString('es-CO') }}</span>
                        <span class="ml-1 text-sm text-muted-foreground">mts</span>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                            <AlertCircle class="size-4 no-print" /> Sin movimiento (+30 días)
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <span class="text-2xl font-bold">{{ summary.no_movement }}</span>
                    </CardContent>
                </Card>
            </div>

            <!-- Tabs + Buscador -->
            <div class="no-print flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <Tabs v-model="activeTab">
                    <PillTabs :tabs="tabs" class="mb-0" />
                </Tabs>
                <div class="relative w-full sm:w-64">
                    <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="search" placeholder="Buscar por N° rollo..." class="pl-9" />
                </div>
            </div>

            <!-- Tabla -->
            <Card>
                <CardContent class="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead class="cursor-pointer select-none whitespace-nowrap" @click="toggleSort('roll_number')">
                                    <span class="inline-flex items-center gap-1">
                                        N° Rollo
                                        <span class="text-muted-foreground">
                                            <template v-if="sortBy === 'roll_number'">{{ sortDir === 'asc' ? '↑' : '↓' }}</template>
                                            <template v-else><span class="opacity-30">↕</span></template>
                                        </span>
                                    </span>
                                </TableHead>
                                <TableHead>Tela / Ítem</TableHead>
                                <TableHead class="cursor-pointer select-none whitespace-nowrap" @click="toggleSort('internal_color_name')">
                                    <span class="inline-flex items-center gap-1">
                                        Color
                                        <span class="text-muted-foreground">
                                            <template v-if="sortBy === 'internal_color_name'">{{ sortDir === 'asc' ? '↑' : '↓' }}</template>
                                            <template v-else><span class="opacity-30">↕</span></template>
                                        </span>
                                    </span>
                                </TableHead>
                                <TableHead class="cursor-pointer select-none whitespace-nowrap" @click="toggleSort('quantity')">
                                    <span class="inline-flex items-center gap-1">
                                        Cantidad inicial
                                        <span class="text-muted-foreground">
                                            <template v-if="sortBy === 'quantity'">{{ sortDir === 'asc' ? '↑' : '↓' }}</template>
                                            <template v-else><span class="opacity-30">↕</span></template>
                                        </span>
                                    </span>
                                </TableHead>
                                <TableHead>Disponible</TableHead>
                                <TableHead class="cursor-pointer select-none whitespace-nowrap" @click="toggleSort('batch')">
                                    <span class="inline-flex items-center gap-1">
                                        Lote
                                        <span class="text-muted-foreground">
                                            <template v-if="sortBy === 'batch'">{{ sortDir === 'asc' ? '↑' : '↓' }}</template>
                                            <template v-else><span class="opacity-30">↕</span></template>
                                        </span>
                                    </span>
                                </TableHead>
                                <TableHead class="cursor-pointer select-none whitespace-nowrap" @click="toggleSort('approval_status')">
                                    <span class="inline-flex items-center gap-1">
                                        Estado
                                        <span class="text-muted-foreground">
                                            <template v-if="sortBy === 'approval_status'">{{ sortDir === 'asc' ? '↑' : '↓' }}</template>
                                            <template v-else><span class="opacity-30">↕</span></template>
                                        </span>
                                    </span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <template v-if="isLoading && !isPrinting">
                                <TableRow v-for="n in 8" :key="n">
                                    <TableCell v-for="c in 7" :key="c"><Skeleton class="h-4 w-full" /></TableCell>
                                </TableRow>
                            </template>
                            <template v-else-if="(isPrinting ? printRolls : rolls).length === 0">
                                <TableRow>
                                    <TableCell colspan="7" class="py-10 text-center text-sm text-muted-foreground">
                                        No hay rollos que mostrar.
                                    </TableCell>
                                </TableRow>
                            </template>
                            <template v-else>
                                <TableRow v-for="roll in (isPrinting ? printRolls : rolls)" :key="roll.id">
                                    <TableCell class="font-mono text-sm">{{ roll.roll_number }}</TableCell>
                                    <TableCell>{{ roll.item?.name ?? '—' }}</TableCell>
                                    <TableCell>{{ roll.internal_color_name ?? '—' }}</TableCell>
                                    <TableCell>{{ Number(roll.quantity).toLocaleString('es-CO') }} mts</TableCell>
                                    <TableCell class="font-medium">{{ availableQuantity(roll).toLocaleString('es-CO') }} mts</TableCell>
                                    <TableCell>{{ roll.batch ?? '—' }}</TableCell>
                                    <TableCell>
                                        <span class="no-print">
                                            <Badge :variant="approvalBadge(roll.approval_status).variant">
                                                {{ approvalLabel(roll.approval_status) }}
                                            </Badge>
                                        </span>
                                        <span class="print-only">{{ approvalLabel(roll.approval_status) }}</span>
                                    </TableCell>
                                </TableRow>
                            </template>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <ServerPagination
                class="no-print"
                v-model:current-page="currentPage"
                v-model:page-size="itemsPerPage"
                :total-items="totalItems"
            />
        </div>
    </AppLayout>
</template>

<style>
@page { margin: 10mm; }

@media print {
    [data-sidebar="sidebar"],
    header.sticky,
    .no-print { display: none !important; }

    .print-only { display: flex !important; }

    body, html {
        background: white !important;
        overflow: hidden !important;
        width: 100% !important;
    }

    main, .peer-data-\[variant\=inset\] {
        box-shadow: none !important;
        border-radius: 0 !important;
        margin: 0 !important;
        overflow: hidden !important;
        min-height: unset !important;
    }

    * { overflow: visible !important; scrollbar-width: none !important; }
    *::-webkit-scrollbar { display: none !important; }
}
</style>

<style scoped>
.print-only { display: none; }

.print-header {
    align-items: center;
    justify-content: space-between;
    padding: 12px 24px 8px;
    border-bottom: 2px solid #1e3d70;
    margin-bottom: 16px;
    gap: 16px;
}

.print-logo { max-height: 40px; max-width: 120px; object-fit: contain; }

.print-header-center { text-align: center; flex: 1; }
.print-title  { font-size: 15pt; font-weight: 700; color: #1e3d70; }
.print-subtitle { font-size: 10pt; color: #555; margin-top: 2px; }

.print-header-right { text-align: right; font-size: 8pt; color: #666; white-space: nowrap; }
</style>
