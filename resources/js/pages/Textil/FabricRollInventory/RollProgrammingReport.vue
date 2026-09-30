<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterField from '@/components/FilterField.vue';
import ServerPagination from '@/components/common/ServerPagination.vue';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { RollProgrammingRow } from '@/domain/Textil';
import AppLayout from '@/layouts/AppLayout.vue';
import { useGlobalAlert } from '@/composables/useGlobalAlert';
import { uiPatterns } from '@/lib/ui-patterns';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { onMounted, ref, watch } from 'vue';

const breadcrumbs = [
    { title: 'Kardex de Telas', href: '/kardex-telas' },
    { title: 'Programación de rollos', href: '/kardex-telas/programacion-rollos' },
];

const { successMessage, errorMessage, errorType, error, clear } = useGlobalAlert();

const rows = ref<RollProgrammingRow[]>([]);
const total = ref(0);
const page = ref(1);
const perPage = ref(20);
const isLoading = ref(false);

const opCode = ref('');
const rollNumber = ref('');
const onlyWithRoll = ref(false);
const sortBy = ref('programmed_meters');
const sortDir = ref<'asc' | 'desc'>('desc');

const numFmt = new Intl.NumberFormat('es-CO', { minimumFractionDigits: 0, maximumFractionDigits: 2 });

function fmtMeters(v: number | null | undefined): string {
    if (v === null || v === undefined) return '-';
    return `${numFmt.format(v)} m`;
}

function toggleSort(col: string) {
    if (sortBy.value === col) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = col;
        sortDir.value = 'desc';
    }
    fetchRows(1);
}

async function fetchRows(targetPage = 1) {
    isLoading.value = true;
    try {
        const params: Record<string, any> = {
            page: targetPage,
            per_page: perPage.value,
            sort_by: sortBy.value,
            sort_direction: sortDir.value,
        };
        if (opCode.value.trim()) params.op_code = opCode.value.trim();
        if (rollNumber.value.trim()) params.roll_number = rollNumber.value.trim();
        if (onlyWithRoll.value) params.only_with_roll = 1;

        const { data } = await axios.get('/kardex-telas/programacion-rollos/data', { params });
        rows.value = data.data ?? [];
        total.value = data.total ?? rows.value.length;
        page.value = data.current_page ?? 1;
    } catch {
        rows.value = [];
        error('No se pudo cargar la programación de rollos.');
    } finally {
        isLoading.value = false;
    }
}

watch([opCode, rollNumber, onlyWithRoll], () => fetchRows(1));

onMounted(() => fetchRows(1));
</script>

<template>
    <Head title="Programación de rollos" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="relative flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <AlertStack
                :success-message="successMessage"
                :error-message="errorMessage"
                :error-type="errorType"
                @close-success="clear"
                @close-error="clear"
            />

            <div class="flex flex-col gap-1">
                <h1 :class="uiPatterns.typography.pageTitle">Programación de rollos</h1>
                <p :class="uiPatterns.typography.pageSubtitle">
                    Rollos programados por Diseño en fichas técnicas, cruzados con lo que Corte realmente consumió — programado, disponible,
                    consumido real y diferencia por línea.
                </p>
            </div>

            <FilterBar>
                <FilterField label="Orden de producción" :active="opCode.trim().length > 0" class="min-w-[180px] p-0">
                    <input
                        v-model="opCode"
                        placeholder="N° OP"
                        class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm focus:border-primary focus:outline-none"
                    />
                </FilterField>
                <FilterField label="N° Rollo" :active="rollNumber.trim().length > 0" class="min-w-[160px] p-0">
                    <input
                        v-model="rollNumber"
                        placeholder="N° Rollo"
                        class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm focus:border-primary focus:outline-none"
                    />
                </FilterField>
                <FilterField label="Solo con rollo ligado" :active="onlyWithRoll" class="min-w-[180px] p-0">
                    <label class="flex h-10 items-center gap-2 px-3 text-sm">
                        <Checkbox :model-value="onlyWithRoll" @update:model-value="(v: boolean | 'indeterminate') => (onlyWithRoll = v === true)" />
                        Solo con rollo ligado
                    </label>
                </FilterField>
            </FilterBar>

            <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <div class="overflow-x-auto">
                    <Table class="min-w-full text-[0.9rem]">
                        <TableHeader>
                            <TableRow>
                                <TableHead class="cursor-pointer select-none" @click="toggleSort('op_code')">
                                    OP {{ sortBy === 'op_code' ? (sortDir === 'asc' ? '↑' : '↓') : '↕' }}
                                </TableHead>
                                <TableHead class="cursor-pointer select-none" @click="toggleSort('roll_number')">
                                    Rollo {{ sortBy === 'roll_number' ? (sortDir === 'asc' ? '↑' : '↓') : '↕' }}
                                </TableHead>
                                <TableHead>Ubicación</TableHead>
                                <TableHead>Tela</TableHead>
                                <TableHead>Color</TableHead>
                                <TableHead>Tipo</TableHead>
                                <TableHead class="cursor-pointer select-none text-right" @click="toggleSort('programmed_meters')">
                                    Programado {{ sortBy === 'programmed_meters' ? (sortDir === 'asc' ? '↑' : '↓') : '↕' }}
                                </TableHead>
                                <TableHead class="cursor-pointer select-none text-right" @click="toggleSort('available_meters')">
                                    Disponible {{ sortBy === 'available_meters' ? (sortDir === 'asc' ? '↑' : '↓') : '↕' }}
                                </TableHead>
                                <TableHead class="cursor-pointer select-none text-right" @click="toggleSort('consumed_real_meters')">
                                    Consumido real {{ sortBy === 'consumed_real_meters' ? (sortDir === 'asc' ? '↑' : '↓') : '↕' }}
                                </TableHead>
                                <TableHead class="cursor-pointer select-none text-right" @click="toggleSort('difference_meters')">
                                    Diferencia {{ sortBy === 'difference_meters' ? (sortDir === 'asc' ? '↑' : '↓') : '↕' }}
                                </TableHead>
                                <TableHead class="text-right" title="Reserva que la línea todavía mantiene sobre el rollo">Reserva pendiente</TableHead>
                                <TableHead class="cursor-pointer select-none text-right" @click="toggleSort('overconsumed_meters')">
                                    Sobreconsumo {{ sortBy === 'overconsumed_meters' ? (sortDir === 'asc' ? '↑' : '↓') : '↕' }}
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <template v-if="isLoading">
                                <TableRow v-for="i in 5" :key="`rp-sk-${i}`">
                                    <TableCell v-for="col in 12" :key="`rp-sk-${i}-${col}`" class="px-4 py-3">
                                        <Skeleton class="h-4 w-full" />
                                    </TableCell>
                                </TableRow>
                            </template>
                            <TableRow v-else-if="rows.length === 0">
                                <TableCell colspan="12" class="py-8 text-center text-sm text-muted-foreground">
                                    No hay rollos programados que coincidan con el filtro.
                                </TableCell>
                            </TableRow>
                            <TableRow v-for="row in rows" v-else :key="`${row.source}-${row.id}`" class="border-b">
                                <TableCell class="text-sm">
                                    <div class="flex items-center gap-1.5">
                                        <Badge :variant="row.source === 'manual' ? 'secondary' : 'outline'" class="shrink-0 text-[0.65rem]">
                                            {{ row.source === 'manual' ? 'Manual' : 'OP' }}
                                        </Badge>
                                        <span>{{ row.manual_order_code ?? row.op_code ?? '-' }}</span>
                                    </div>
                                </TableCell>
                                <TableCell class="text-sm">{{ row.roll_number ?? '-' }}</TableCell>
                                <TableCell class="text-sm">{{ row.location ?? '-' }}</TableCell>
                                <TableCell class="max-w-[180px] truncate text-sm" :title="row.fabric_name ?? ''">{{ row.fabric_name ?? '-' }}</TableCell>
                                <TableCell class="text-sm">{{ row.color ?? '-' }}</TableCell>
                                <TableCell class="text-sm">{{ row.type ?? '-' }}</TableCell>
                                <TableCell class="text-right text-sm">{{ fmtMeters(row.programmed_meters) }}</TableCell>
                                <TableCell class="text-right text-sm">{{ fmtMeters(row.available_meters) }}</TableCell>
                                <TableCell class="text-right text-sm">{{ fmtMeters(row.consumed_real_meters) }}</TableCell>
                                <TableCell
                                    class="text-right text-sm font-medium"
                                    :class="
                                        Math.abs(row.difference_meters) < 0.01
                                            ? 'text-muted-foreground'
                                            : row.difference_meters > 0
                                              ? 'text-amber-600 dark:text-amber-400'
                                              : 'text-red-600 dark:text-red-400'
                                    "
                                >
                                    {{ fmtMeters(row.difference_meters) }}
                                </TableCell>
                                <TableCell class="text-right text-sm text-muted-foreground">{{ fmtMeters(row.pending_meters) }}</TableCell>
                                <TableCell
                                    class="text-right text-sm"
                                    :class="row.overconsumed_meters > 0 ? 'font-medium text-amber-600 dark:text-amber-400' : 'text-muted-foreground'"
                                >
                                    {{ row.overconsumed_meters > 0 ? fmtMeters(row.overconsumed_meters) : '-' }}
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
                <div class="mt-4">
                    <ServerPagination v-model:current-page="page" v-model:page-size="perPage" :total-items="total" @fetch-data="fetchRows" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
