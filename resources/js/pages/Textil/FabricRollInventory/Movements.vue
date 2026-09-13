<script setup lang="ts">
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import InputDate from '@/components/InputDate.vue';
import SelectField from '@/components/SelectField.vue';
import ServerPagination from '@/components/common/ServerPagination.vue';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { uiPatterns } from '@/lib/ui-patterns';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { ArrowUpRight } from 'lucide-vue-next';
import { onMounted, ref, watch } from 'vue';

const breadcrumbs = [
    { title: 'Inventario Telas por Rollo', href: '/kardex-telas/inventario-rollos' },
    { title: 'Historial de ajustes de rollos', href: '/kardex-telas/inventario-rollos/movimientos' },
];

type RollAdjustment = {
    id: number;
    fabric_roll_id: number;
    type: string;
    quantity_before: string;
    quantity_after: string;
    partial_before: string | null;
    partial_after: string | null;
    actual_output_before: string | null;
    actual_output_after: string | null;
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

const numFmt = new Intl.NumberFormat('es-CO', { minimumFractionDigits: 0, maximumFractionDigits: 4 });

function fmtNum(v: string | number | null | undefined) {
    if (v === null || v === undefined || v === '') return '-';
    const n = Number(v);
    return Number.isNaN(n) ? String(v) : numFmt.format(n);
}

// Disponibilidad real = cantidad del rollo - salida real (actual_output), la misma
// fórmula que FabricRoll::getAvailableQuantityAttribute() en backend.
function fmtRealAvailability(quantity: string | null | undefined, actualOutput: string | null | undefined) {
    if (actualOutput === null || actualOutput === undefined) return '-';
    const q = Number(quantity ?? 0);
    const a = Number(actualOutput);
    if (Number.isNaN(q) || Number.isNaN(a)) return '-';
    return numFmt.format(q - a);
}

function fmtDate(v: string | null | undefined) {
    if (!v) return '-';
    const d = new Date(v);
    return Number.isNaN(d.getTime()) ? String(v) : d.toLocaleDateString('es-CO');
}

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

onMounted(() => fetchAdjustments(1));
</script>

<template>
    <Head title="Historial de ajustes de rollos" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="relative flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <div class="flex items-center justify-between gap-3">
                <div class="flex flex-col gap-1">
                    <h1 :class="uiPatterns.typography.pageTitle">Historial de ajustes de rollos</h1>
                    <p :class="uiPatterns.typography.pageSubtitle">
                        Correcciones de cantidad, entradas/salidas sueltas e importaciones aplicadas directamente sobre un rollo.
                    </p>
                </div>
                <Button as-child variant="outline" class="shrink-0 gap-1.5">
                    <Link :href="route('movimientos-inventario.index', { fabric_items_only: 1 })">
                        Ver movimientos de inventario
                        <ArrowUpRight class="h-4 w-4" />
                    </Link>
                </Button>
            </div>

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
                                <TableHead>Disp. real antes</TableHead>
                                <TableHead>Disp. real después</TableHead>
                                <TableHead>Razón</TableHead>
                                <TableHead>Referencia</TableHead>
                                <TableHead>Usuario</TableHead>
                                <TableHead>Fecha</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <template v-if="adjLoading">
                                <TableRow v-for="i in 5" :key="`adj-sk-${i}`">
                                    <TableCell v-for="col in 12" :key="`adj-sk-${i}-${col}`" class="px-4 py-3">
                                        <Skeleton class="h-4 w-full" />
                                    </TableCell>
                                </TableRow>
                            </template>
                            <TableRow v-else-if="adjustments.length === 0">
                                <TableCell colspan="12" class="py-8 text-center text-sm text-muted-foreground">
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
                                <TableCell class="text-sm">{{ fmtRealAvailability(adj.quantity_before, adj.actual_output_before) }}</TableCell>
                                <TableCell class="text-sm">{{ fmtRealAvailability(adj.quantity_after, adj.actual_output_after) }}</TableCell>
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
        </div>
    </AppLayout>
</template>
