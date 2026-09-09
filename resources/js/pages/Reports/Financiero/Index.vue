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
import { FileDown, FileText, Clock, CheckCircle2, Search } from 'lucide-vue-next';
import { ref, watch, onMounted, nextTick } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Informes', href: '/informes' },
    { title: 'Financiero', href: '/informes/financiero' },
];

const search = ref('');
const dateFrom = ref('');
const dateTo = ref('');
const currentPage = ref(1);
const itemsPerPage = ref(15);
const totalItems = ref(0);
const isLoading = ref(false);
const isPrinting = ref(false);
const isPrintLoading = ref(false);
const printDocs = ref<any[]>([]);
const printDate = ref('');

const docs = ref<any[]>([]);
const summary = ref({ total_docs: 0, pendientes: 0, aprobados: 0 });
const sortBy = ref<string>('');
const sortDir = ref<'asc' | 'desc'>('desc');

async function fetchData() {
    isLoading.value = true;
    try {
        const { data } = await axios.get('/informes/financiero/data', {
            params: {
                search: search.value || undefined,
                date_from: dateFrom.value || undefined,
                date_to: dateTo.value || undefined,
                page: currentPage.value,
                per_page: itemsPerPage.value,
                sort_by: sortBy.value || undefined,
                sort_direction: sortBy.value ? sortDir.value : undefined,
            },
        });
        docs.value = data.data.data;
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

watch([currentPage, itemsPerPage, dateFrom, dateTo], () => { currentPage.value = 1; fetchData(); });

let searchTimeout: ReturnType<typeof setTimeout>;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => { currentPage.value = 1; fetchData(); }, 350);
});

onMounted(fetchData);

async function exportPdf() {
    isPrintLoading.value = true;
    try {
        const { data } = await axios.get('/informes/financiero/data', {
            params: { search: search.value || undefined, date_from: dateFrom.value || undefined, date_to: dateTo.value || undefined, all: true },
        });
        printDocs.value = data.data;
        printDate.value = new Date().toLocaleString('es-CO', { dateStyle: 'long', timeStyle: 'short' });
        isPrinting.value = true;
        await nextTick();
        window.print();
        window.addEventListener('afterprint', () => { isPrinting.value = false; }, { once: true });
    } finally {
        isPrintLoading.value = false;
    }
}

const statusBadge = (status: string) => {
    const map: Record<string, { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' }> = {
        pending:   { label: 'Pendiente', variant: 'outline' },
        approved:  { label: 'Aprobado', variant: 'default' },
        rejected:  { label: 'Rechazado', variant: 'destructive' },
        completed: { label: 'Completado', variant: 'secondary' },
    };
    return map[status] ?? { label: status, variant: 'secondary' };
};

const docTypeLabel = (type: string) => {
    const map: Record<string, string> = {
        purchase_invoice:      'Factura compra',
        purchase_invoice_tela: 'Factura tela',
        credit_note:           'Nota crédito',
        debit_note:            'Nota débito',
    };
    return map[type] ?? type;
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Informe Financiero" />

        <div class="print-only print-header">
            <img src="/images/olamtex.png" alt="Logo" class="print-logo" onerror="this.style.display='none'" />
            <div class="print-header-center">
                <div class="print-title">Informe Financiero</div>
                <div class="print-subtitle">
                    Documentos de Compra
                    <template v-if="dateFrom || dateTo">
                        — {{ dateFrom || '…' }} / {{ dateTo || '…' }}
                    </template>
                </div>
            </div>
            <div class="print-header-right">
                <div>Generado: {{ printDate }}</div>
                <div>Total registros: {{ printDocs.length }}</div>
            </div>
        </div>

        <div class="flex flex-col gap-6 p-6">
            <div class="no-print flex items-start justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Informe Financiero</h1>
                    <p class="mt-1 text-sm text-muted-foreground">Documentos de compra y estado de aprobaciones.</p>
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
                            <FileText class="size-4 no-print" /> Total documentos
                        </CardTitle>
                    </CardHeader>
                    <CardContent><span class="text-2xl font-bold">{{ summary.total_docs }}</span></CardContent>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                            <Clock class="size-4 no-print" /> Pendientes
                        </CardTitle>
                    </CardHeader>
                    <CardContent><span class="text-2xl font-bold">{{ summary.pendientes }}</span></CardContent>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                            <CheckCircle2 class="size-4 no-print" /> Aprobados
                        </CardTitle>
                    </CardHeader>
                    <CardContent><span class="text-2xl font-bold">{{ summary.aprobados }}</span></CardContent>
                </Card>
            </div>

            <div class="no-print flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-2">
                    <Input v-model="dateFrom" type="date" class="w-40" />
                    <span class="text-sm text-muted-foreground">—</span>
                    <Input v-model="dateTo" type="date" class="w-40" />
                </div>
                <div class="relative w-full sm:w-64">
                    <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="search" placeholder="Buscar por N° documento..." class="pl-9" />
                </div>
            </div>

            <Card>
                <CardContent class="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead class="cursor-pointer select-none whitespace-nowrap" @click="toggleSort('document_number')">
                                    <span class="inline-flex items-center gap-1">
                                        N° Documento
                                        <span class="text-muted-foreground">
                                            <template v-if="sortBy === 'document_number'">{{ sortDir === 'asc' ? '↑' : '↓' }}</template>
                                            <template v-else><span class="opacity-30">↕</span></template>
                                        </span>
                                    </span>
                                </TableHead>
                                <TableHead>Tipo</TableHead>
                                <TableHead>Proveedor</TableHead>
                                <TableHead class="cursor-pointer select-none whitespace-nowrap" @click="toggleSort('date')">
                                    <span class="inline-flex items-center gap-1">
                                        Fecha
                                        <span class="text-muted-foreground">
                                            <template v-if="sortBy === 'date'">{{ sortDir === 'asc' ? '↑' : '↓' }}</template>
                                            <template v-else><span class="opacity-30">↕</span></template>
                                        </span>
                                    </span>
                                </TableHead>
                                <TableHead class="cursor-pointer select-none whitespace-nowrap" @click="toggleSort('status')">
                                    <span class="inline-flex items-center gap-1">
                                        Estado
                                        <span class="text-muted-foreground">
                                            <template v-if="sortBy === 'status'">{{ sortDir === 'asc' ? '↑' : '↓' }}</template>
                                            <template v-else><span class="opacity-30">↕</span></template>
                                        </span>
                                    </span>
                                </TableHead>
                                <TableHead>Aprobación</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <template v-if="isLoading && !isPrinting">
                                <TableRow v-for="n in 8" :key="n">
                                    <TableCell v-for="c in 6" :key="c"><Skeleton class="h-4 w-full" /></TableCell>
                                </TableRow>
                            </template>
                            <template v-else-if="(isPrinting ? printDocs : docs).length === 0">
                                <TableRow>
                                    <TableCell colspan="6" class="py-10 text-center text-sm text-muted-foreground">No hay documentos que mostrar.</TableCell>
                                </TableRow>
                            </template>
                            <template v-else>
                                <TableRow v-for="doc in (isPrinting ? printDocs : docs)" :key="doc.id">
                                    <TableCell class="font-mono text-sm">{{ doc.document_number ?? '—' }}</TableCell>
                                    <TableCell>{{ docTypeLabel(doc.document_type) }}</TableCell>
                                    <TableCell>{{ doc.contact?.name ?? '—' }}</TableCell>
                                    <TableCell>{{ doc.date ?? '—' }}</TableCell>
                                    <TableCell>
                                        <span class="no-print"><Badge :variant="statusBadge(doc.status).variant">{{ statusBadge(doc.status).label }}</Badge></span>
                                        <span class="print-only">{{ statusBadge(doc.status).label }}</span>
                                    </TableCell>
                                    <TableCell>
                                        <span class="no-print"><Badge :variant="statusBadge(doc.approval_status).variant">{{ statusBadge(doc.approval_status).label }}</Badge></span>
                                        <span class="print-only">{{ statusBadge(doc.approval_status).label }}</span>
                                    </TableCell>
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
