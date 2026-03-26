<script setup lang="ts">
import TableStatusBadge from '@/components/common/TableStatusBadge.vue';
import { type TableStatusTone } from '@/components/common/TableStatusBadge.vue';
import MetricTile from '@/components/MetricTile.vue';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { BankAccount, BankAccountType, BankMovement } from '@/domain/Finance/types';
import AppLayout from '@/layouts/AppLayout.vue';
import { uiPatterns } from '@/lib/ui-patterns';
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, Banknote, CreditCard, Landmark, Receipt } from 'lucide-vue-next';
import { computed } from 'vue';

// ━━━━━━ PROPS ━━━━━━
const props = defineProps<{
    bankAccount: BankAccount;
    movements: BankMovement[];
}>();

// ━━━━━━ BREADCRUMBS ━━━━━━
const breadcrumbs = computed(() => [
    { title: 'Bancos y Cajas', href: '/finanzas/bancos' },
    { title: props.bankAccount.name, href: '' },
]);

// ━━━━━━ CURRENCY FORMAT ━━━━━━
const currencyFormatter = new Intl.NumberFormat('es-CO', {
    style: 'currency',
    currency: 'COP',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
});

function formatCurrency(value: number): string {
    return currencyFormatter.format(Number(value || 0));
}

// ━━━━━━ TYPE LABELS & TONES ━━━━━━
const typeLabels: Record<BankAccountType, string> = {
    bank: 'Banco',
    cash: 'Efectivo',
    credit_card: 'Tarjeta de crédito',
};

const typeIcons: Record<BankAccountType, typeof Landmark> = {
    bank: Landmark,
    cash: Banknote,
    credit_card: CreditCard,
};

// ━━━━━━ ENTRY TYPE MAP ━━━━━━
const entryTypeMap: Record<string, { label: string; tone: TableStatusTone }> = {
    opening: { label: 'Apertura', tone: 'gray' },
    manual: { label: 'Manual', tone: 'blue' },
    sale_invoice: { label: 'Factura venta', tone: 'green' },
    purchase_invoice: { label: 'Factura compra', tone: 'amber' },
    cash_receipt: { label: 'Recibo de caja', tone: 'sky' },
    expense_voucher: { label: 'Comprobante egreso', tone: 'red' },
};

function getEntryTypeBadge(entryType: string): { label: string; tone: TableStatusTone } {
    return entryTypeMap[entryType] ?? { label: entryType, tone: 'gray' as TableStatusTone };
}

// ━━━━━━ KPI COMPUTEDS ━━━━━━
const totalEntradas = computed(() =>
    props.movements.reduce((sum, m) => sum + (Number(m.debit) > 0 ? Number(m.debit) : 0), 0),
);

const totalSalidas = computed(() =>
    props.movements.reduce((sum, m) => sum + (Number(m.credit) > 0 ? Number(m.credit) : 0), 0),
);

// ━━━━━━ RUNNING BALANCE ━━━━━━
// movements come ordered DESC from backend; reverse to compute running balance from oldest to newest
const movementsWithBalance = computed(() => {
    const reversed = [...props.movements].reverse();
    let runningBalance = 0;

    const balanceMap = new Map<number, number>();
    for (const m of reversed) {
        runningBalance += Number(m.debit) - Number(m.credit);
        balanceMap.set(m.id, runningBalance);
    }

    return props.movements.map((m) => ({
        ...m,
        runningBalance: balanceMap.get(m.id) ?? 0,
    }));
});

// ━━━━━━ DATE FORMAT ━━━━━━
function formatDate(dateStr: string): string {
    const [year, month, day] = dateStr.split('-');
    return `${day}/${month}/${year}`;
}

function padEntryNumber(num: number): string {
    return String(num).padStart(6, '0');
}

// ━━━━━━ NAVIGATION ━━━━━━
function goBack() {
    router.visit('/finanzas/bancos');
}
</script>

<template>
    <Head :title="`${bankAccount.name} — Bancos y Cajas`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div :class="uiPatterns.pageIndex.page">
            <!-- BACK BUTTON -->
            <div>
                <Button variant="ghost" size="sm" class="gap-2 text-muted-foreground" @click="goBack">
                    <ArrowLeft class="h-4 w-4" />
                    Volver
                </Button>
            </div>

            <!-- PAGE HEADER -->
            <div :class="uiPatterns.pageIndex.header">
                <h1 :class="uiPatterns.pageIndex.title">{{ bankAccount.name }}</h1>
                <div class="flex items-center gap-2">
                    <component :is="typeIcons[bankAccount.type]" class="h-4 w-4 text-muted-foreground" />
                    <span class="text-sm font-medium text-foreground">
                        {{ typeLabels[bankAccount.type] }}
                    </span>
                    <span v-if="bankAccount.account_number" class="text-sm text-muted-foreground">
                        · {{ bankAccount.account_number }}
                    </span>
                    <TableStatusBadge
                        :label="bankAccount.is_active ? 'Activo' : 'Inactivo'"
                        :tone="bankAccount.is_active ? 'green' : 'gray'"
                    />
                </div>
            </div>

            <!-- KPI CARDS -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <MetricTile
                    label="Saldo actual"
                    :value="formatCurrency(bankAccount.current_balance)"
                    :show-badge="false"
                    :value-class="bankAccount.current_balance >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'"
                    dense
                />
                <MetricTile
                    label="Total entradas"
                    :value="formatCurrency(totalEntradas)"
                    :show-badge="false"
                    value-class="text-emerald-600 dark:text-emerald-400"
                    dense
                />
                <MetricTile
                    label="Total salidas"
                    :value="formatCurrency(totalSalidas)"
                    :show-badge="false"
                    value-class="text-red-600 dark:text-red-400"
                    dense
                />
            </div>

            <!-- MOVEMENTS SECTION HEADER -->
            <div>
                <h2 :class="uiPatterns.typography.sectionTitle">
                    Movimientos ({{ movements.length }})
                </h2>
            </div>

            <!-- MOVEMENTS TABLE -->
            <div :class="uiPatterns.pageIndex.tableWrap">
                <Table class="text-center">
                    <TableHeader>
                        <TableRow>
                            <TableHead :class="uiPatterns.typography.tableHead">Fecha</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">N° Asiento</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Tipo</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Descripción</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Entrada</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Salida</TableHead>
                            <TableHead :class="uiPatterns.typography.tableHead">Saldo acumulado</TableHead>
                        </TableRow>
                    </TableHeader>

                    <TableBody>
                        <!-- EMPTY STATE -->
                        <TableRow v-if="movementsWithBalance.length === 0">
                            <TableCell colspan="7" class="py-12 text-center">
                                <div class="flex flex-col items-center gap-3">
                                    <Receipt class="h-10 w-10 text-muted-foreground/40" />
                                    <p class="text-sm text-muted-foreground">
                                        Esta cuenta no tiene movimientos registrados aún.
                                    </p>
                                </div>
                            </TableCell>
                        </TableRow>

                        <!-- DATA ROWS -->
                        <TableRow v-for="movement in movementsWithBalance" :key="movement.id">
                            <TableCell class="text-sm">
                                {{ formatDate(movement.entry_date) }}
                            </TableCell>
                            <TableCell class="font-mono text-sm">
                                {{ padEntryNumber(movement.entry_number) }}
                            </TableCell>
                            <TableCell>
                                <TableStatusBadge
                                    :label="getEntryTypeBadge(movement.entry_type).label"
                                    :tone="getEntryTypeBadge(movement.entry_type).tone"
                                />
                            </TableCell>
                            <TableCell class="max-w-[250px] truncate text-left text-sm">
                                {{ movement.line_reference || movement.entry_description || '—' }}
                            </TableCell>
                            <TableCell>
                                <span
                                    v-if="Number(movement.debit) > 0"
                                    class="font-semibold text-emerald-600 dark:text-emerald-400"
                                >
                                    {{ formatCurrency(Number(movement.debit)) }}
                                </span>
                                <span v-else class="text-muted-foreground">—</span>
                            </TableCell>
                            <TableCell>
                                <span
                                    v-if="Number(movement.credit) > 0"
                                    class="font-semibold text-red-600 dark:text-red-400"
                                >
                                    {{ formatCurrency(Number(movement.credit)) }}
                                </span>
                                <span v-else class="text-muted-foreground">—</span>
                            </TableCell>
                            <TableCell>
                                <span
                                    class="font-semibold"
                                    :class="movement.runningBalance >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'"
                                >
                                    {{ formatCurrency(movement.runningBalance) }}
                                </span>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>
    </AppLayout>
</template>
