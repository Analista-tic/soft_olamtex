<script setup lang="ts">
import HelpTooltip from '@/components/HelpTooltip.vue';
import type { CostsSummary, OpBasicInfo } from '@/domain/Production';
import { fetchOpCostsSummary } from '@/domain/Production';
import axios from 'axios';
import { Minus, RotateCcw, TrendingDown, TrendingUp } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const FIXED_COST_RATE = 0.1458;
const IVA_RATE        = 1.19;

async function patchPriceOverrides(opId: string | number, billing: number | null, sale: number | null): Promise<CostsSummary> {
    const res = await axios.patch<CostsSummary>(`/produccion/ficha-tecnica/${String(opId)}/costos/precios`, {
        billing_price_override: billing,
        selling_price_override: sale,
    });
    return res.data;
}

const props = defineProps<{
    opId: string | number | null;
    selectedOp?: OpBasicInfo | null;
}>();

const isLoading      = ref(false);
const isSaving       = ref(false);
const error          = ref<string | null>(null);
const saveError      = ref<string | null>(null);
const summary        = ref<CostsSummary | null>(null);
const editBilling    = ref('');  // raw digits
const editSale       = ref('');
const billingFocused = ref(false);
const saleFocused    = ref(false);

const hasOp = computed(() => props.opId != null && props.opId !== '');

// ── Formatters ────────────────────────────────────────────────────────────────

const copFmt = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 });

function fCOP(v: number | null | undefined): string {
    return copFmt.format(Number(v ?? 0)).replace(/ /g, ' ');
}

function fPct(v: number): string {
    return `${v > 0 ? '+' : ''}${v.toFixed(2)}%`;
}

function parseRaw(s: string): number | null {
    const normalized = s
        .replace(/\s+/g, '')
        .replace(/[^\d.,-]/g, '')
        .replace(/,/g, '.');
    const n = Number(normalized);
    return normalized !== '' && Number.isFinite(n) && n > 0 ? n : null;
}

function inputDisplayVal(raw: string, focused: boolean): string {
    if (focused) return raw;
    const n = parseRaw(raw);
    return n != null ? fCOP(n) : '';
}

function toTwoDecimals(value: number | null | undefined): string {
    if (value == null || !Number.isFinite(value)) return '';
    return Number(value).toFixed(2);
}

function onPriceInput(field: 'billing' | 'sale', e: Event): void {
    const input = e.target as HTMLInputElement;
    let sanitized = input.value
        .replace(/\s+/g, '')
        .replace(/[^\d.,]/g, '')
        .replace(/,/g, '.');

    const firstDot = sanitized.indexOf('.');
    if (firstDot >= 0) {
        sanitized = `${sanitized.slice(0, firstDot + 1)}${sanitized.slice(firstDot + 1).replace(/\./g, '')}`;
        const [intPart, decPart = ''] = sanitized.split('.');
        sanitized = `${intPart}.${decPart.slice(0, 2)}`;
    }

    if (field === 'billing') editBilling.value = sanitized;
    else editSale.value = sanitized;
}

// ── Precios efectivos (reactivos a inputs) ────────────────────────────────────

const effectiveInvoicing = computed<number | null>(() =>
    parseRaw(editBilling.value) ?? summary.value?.prices.invoicing ?? null,
);
const effectiveSale = computed<number | null>(() =>
    parseRaw(editSale.value) ?? summary.value?.prices.sale ?? null,
);

// ── Cálculos en vivo ──────────────────────────────────────────────────────────

const liveFixedCost = computed<number | null>(() => {
    const inv = effectiveInvoicing.value;
    return inv != null ? inv * FIXED_COST_RATE : null;
});

const liveTotal = computed<number>(() => {
    const s = summary.value;
    if (!s) return 0;
    return (liveFixedCost.value ?? 0) + s.costs.rawMaterial + s.costs.supplies + s.costs.processes;
});

const liveRentValue = computed<number | null>(() => {
    const inv = effectiveInvoicing.value;
    return inv != null ? inv - liveTotal.value : null;
});

const liveRentPercent = computed<number | null>(() => {
    const inv = effectiveInvoicing.value;
    const val = liveRentValue.value;
    return inv && inv > 0 && val != null ? (val / inv) * 100 : null;
});

const liveMarginValue = computed<number | null>(() => {
    const sale = effectiveSale.value;
    const inv  = effectiveInvoicing.value;
    if (!sale || sale <= 0 || inv == null) return null;
    return sale / IVA_RATE - inv;
});

const liveMarginPercent = computed<number | null>(() => {
    const sale = effectiveSale.value;
    const val  = liveMarginValue.value;
    if (!sale || sale <= 0 || val == null) return null;
    return (val / (sale / IVA_RATE)) * 100;
});

// ── Card types ────────────────────────────────────────────────────────────────

type Card = {
    key: string;
    label: string;
    value: string;
    sub?: string | null;
    pill?: { text: string; dir: 'up' | 'down' | 'neutral'; cls: string } | null;
    cardCls: string;
    tooltip?: string | null;
};

function pill(v: number | null | undefined): Card['pill'] {
    if (v == null) return null;
    if (v > 0) return { text: fPct(v), dir: 'up',      cls: 'border-emerald-200 bg-emerald-50 text-emerald-700' };
    if (v < 0) return { text: fPct(v), dir: 'down',    cls: 'border-rose-200 bg-rose-50 text-rose-700' };
    return            { text: fPct(v), dir: 'neutral',  cls: 'border-slate-200 bg-slate-50 text-muted-foreground' };
}

const costCards = computed<Card[]>(() => {
    const s = summary.value;
    if (!s) return [];
    const inv      = effectiveInvoicing.value;
    const total    = liveTotal.value;
    const totalPct = inv && inv > 0 ? (total / inv) * 100 : null;
    return [
        { key: 'mp',   label: 'Materia prima', value: fCOP(s.costs.rawMaterial), sub: 'Costo por prenda',   pill: null, cardCls: 'bg-card' },
        { key: 'ins',  label: 'Insumos',        value: fCOP(s.costs.supplies),   sub: 'Costo por prenda',   pill: null, cardCls: 'bg-card' },
        { key: 'proc', label: 'Procesos',        value: fCOP(s.costs.processes),  sub: 'Costo por prenda',   pill: null, cardCls: 'bg-card' },
        {
            key: 'total', label: 'Costo total', value: fCOP(total), sub: 'Consolidado por prenda',
            pill: totalPct != null ? { text: `${totalPct.toFixed(1)}% de facturación`, dir: 'neutral', cls: 'border-blue-200 bg-blue-50 text-blue-700' } : null,
            cardCls: 'bg-blue-50 ring-blue-200',
        },
    ];
});

const profitCards = computed<Card[]>(() => {
    if (!summary.value) return [];
    const rentNeg = typeof liveRentPercent.value === 'number' && liveRentPercent.value < 0;
    return [
        {
            key: 'fixed', label: 'Costo fijo (14,58%)',
            value: liveFixedCost.value != null ? fCOP(liveFixedCost.value) : '—',
            sub: 'Precio facturación × 14,58%',
            pill: { text: '14,58%', dir: 'neutral', cls: 'border-sky-200 bg-sky-50 text-sky-700' },
            cardCls: 'bg-card', tooltip: 'Costo fijo = Precio facturación × 14,58%',
        },
        {
            key: 'rent', label: 'Rentabilidad operativa',
            value: liveRentValue.value != null ? fCOP(liveRentValue.value) : '—',
            sub: 'Precio facturación − Costo total',
            pill: pill(liveRentPercent.value),
            cardCls: rentNeg ? 'bg-rose-50 ring-rose-200' : 'bg-card',
            tooltip: 'Rentabilidad = Precio facturación − Costo total',
        },
        {
            key: 'margin', label: 'Margen comercial (s/IVA)',
            value: liveMarginValue.value != null ? fCOP(liveMarginValue.value) : '—',
            sub: '(P. venta s/IVA − P. facturación) / P. venta s/IVA',
            pill: pill(liveMarginPercent.value),
            cardCls: 'bg-card', tooltip: 'Margen = (Precio venta s/IVA − Precio facturación) / Precio venta s/IVA',
        },
    ];
});

// ── Guardar / Resetear ────────────────────────────────────────────────────────

function syncFields(): void {
    const p = summary.value?.prices;
    editBilling.value = p?.invoicingOverride != null ? toTwoDecimals(p.invoicingOverride) : toTwoDecimals(p?.sheetInvoicing);
    editSale.value    = p?.saleOverride      != null ? toTwoDecimals(p.saleOverride)      : toTwoDecimals(p?.sheetSale);
}

async function saveOverrides(): Promise<void> {
    if (!hasOp.value || isSaving.value) return;
    isSaving.value  = true;
    saveError.value = null;
    try {
        const billing = parseRaw(editBilling.value);
        const sale    = parseRaw(editSale.value);
        summary.value = await patchPriceOverrides(props.opId as string | number, billing, sale);
        syncFields();
    } catch {
        saveError.value = 'No se pudieron guardar los precios.';
    } finally {
        isSaving.value = false;
    }
}

async function resetOverride(field: 'billing' | 'sale'): Promise<void> {
    if (!hasOp.value || isSaving.value) return;
    isSaving.value  = true;
    saveError.value = null;
    try {
        const billing = field === 'billing' ? null : (summary.value?.prices.invoicingOverride ?? null);
        const sale    = field === 'sale'    ? null : (summary.value?.prices.saleOverride      ?? null);
        summary.value = await patchPriceOverrides(props.opId as string | number, billing, sale);
        syncFields();
    } catch {
        saveError.value = 'No se pudo restablecer el precio.';
    } finally {
        isSaving.value = false;
    }
}

// ── Carga ─────────────────────────────────────────────────────────────────────

async function load(): Promise<void> {
    if (!hasOp.value) { summary.value = null; error.value = null; return; }
    isLoading.value = true;
    error.value     = null;
    try {
        summary.value = await fetchOpCostsSummary(props.opId as string | number);
        syncFields();
    } catch {
        summary.value = null;
        error.value   = 'No se pudo cargar el resumen de costos.';
    } finally {
        isLoading.value = false;
    }
}

watch(() => props.opId, () => void load(), { immediate: true });
</script>

<template>
    <div v-if="hasOp" class="space-y-5 p-1">

        <!-- Loading -->
        <div v-if="isLoading" class="flex items-center justify-center py-16 text-sm text-muted-foreground">
            Cargando costos...
        </div>

        <template v-else-if="summary">

            <!-- ── 1. Precios base ──────────────────────────────────────────── -->
            <section class="space-y-2">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Precios base</p>
                    <p class="text-xs text-muted-foreground">Solo aplica a esta OP · no afecta la ficha técnica</p>
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">

                    <!-- Precio facturación -->
                    <article class="flex flex-col rounded-xl p-4 ring-1 ring-border/60 bg-card gap-1">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-xs font-medium text-muted-foreground">Precio facturación</p>
                            <div class="flex items-center gap-1.5">
                                <span v-if="summary.prices.invoicingOverride != null" class="rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700">Ajustado</span>
                                <button
                                    v-if="summary.prices.invoicingOverride != null"
                                    type="button"
                                    class="flex h-6 w-6 items-center justify-center rounded border border-input bg-muted text-muted-foreground transition hover:text-foreground disabled:opacity-40"
                                    :disabled="isSaving"
                                    title="Restablecer al precio de la ficha"
                                    @click="resetOverride('billing')"
                                ><RotateCcw class="h-3 w-3" /></button>
                            </div>
                        </div>
                        <input
                            type="text"
                            inputmode="numeric"
                            placeholder="$ 0"
                            :value="inputDisplayVal(editBilling, billingFocused)"
                            :disabled="isSaving"
                            class="w-full border-0 bg-transparent p-0 text-2xl font-bold tabular-nums text-foreground outline-none placeholder:text-muted-foreground/40 disabled:opacity-50"
                            @focus="billingFocused = true"
                            @blur="billingFocused = false"
                            @input="onPriceInput('billing', $event)"
                            @keydown.enter="saveOverrides"
                        />
                        <p class="text-[11px] text-muted-foreground">
                            <span v-if="summary.prices.sheetInvoicing != null">Ficha: {{ fCOP(summary.prices.sheetInvoicing) }}</span>
                            <span v-else>Sin precio en ficha</span>
                        </p>
                    </article>

                    <!-- Precio venta -->
                    <article class="flex flex-col rounded-xl p-4 ring-1 ring-border/60 bg-card gap-1">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-xs font-medium text-muted-foreground">Precio venta</p>
                            <div class="flex items-center gap-1.5">
                                <span v-if="summary.prices.saleOverride != null" class="rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700">Ajustado</span>
                                <button
                                    v-if="summary.prices.saleOverride != null"
                                    type="button"
                                    class="flex h-6 w-6 items-center justify-center rounded border border-input bg-muted text-muted-foreground transition hover:text-foreground disabled:opacity-40"
                                    :disabled="isSaving"
                                    title="Restablecer al precio de la ficha"
                                    @click="resetOverride('sale')"
                                ><RotateCcw class="h-3 w-3" /></button>
                            </div>
                        </div>
                        <input
                            type="text"
                            inputmode="numeric"
                            placeholder="$ 0"
                            :value="inputDisplayVal(editSale, saleFocused)"
                            :disabled="isSaving"
                            class="w-full border-0 bg-transparent p-0 text-2xl font-bold tabular-nums text-foreground outline-none placeholder:text-muted-foreground/40 disabled:opacity-50"
                            @focus="saleFocused = true"
                            @blur="saleFocused = false"
                            @input="onPriceInput('sale', $event)"
                            @keydown.enter="saveOverrides"
                        />
                        <p class="text-[11px] text-muted-foreground">
                            <span v-if="summary.prices.sheetSale != null">Ficha: {{ fCOP(summary.prices.sheetSale) }}</span>
                            <span v-else>Sin precio en ficha</span>
                        </p>
                    </article>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        class="inline-flex h-8 items-center gap-1.5 rounded-md border border-input bg-background px-3 text-xs font-medium transition hover:bg-muted disabled:pointer-events-none disabled:opacity-40"
                        :disabled="isSaving"
                        @click="saveOverrides"
                    >{{ isSaving ? 'Guardando...' : 'Guardar precios' }}</button>
                    <p v-if="saveError" class="text-xs text-destructive">{{ saveError }}</p>
                </div>
            </section>

            <!-- ── 2. Desglose de costos ────────────────────────────────────── -->
            <section class="space-y-2">
                <div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
                    <article
                        v-for="card in costCards"
                        :key="card.key"
                        :class="['flex flex-col rounded-xl p-4 ring-1 ring-border/60', card.cardCls]"
                    >
                        <p class="text-xs font-medium text-muted-foreground">{{ card.label }}</p>
                        <p class="mt-2 text-2xl font-bold tabular-nums leading-tight text-foreground">{{ card.value }}</p>
                        <div class="mt-auto pt-3 flex items-end justify-between gap-2">
                            <p class="text-[11px] text-muted-foreground">{{ card.sub }}</p>
                            <span
                                v-if="card.pill"
                                :class="['inline-flex shrink-0 items-center gap-1 rounded-full border px-2 py-0.5 text-[11px] font-semibold', card.pill.cls]"
                            >
                                <Minus class="h-3 w-3" />
                                {{ card.pill.text }}
                            </span>
                        </div>
                    </article>
                </div>
            </section>

            <!-- ── 3. Rentabilidad ──────────────────────────────────────────── -->
            <section class="space-y-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Rentabilidad</p>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <article
                        v-for="card in profitCards"
                        :key="card.key"
                        :class="['flex flex-col rounded-xl p-4 ring-1 ring-border/60', card.cardCls]"
                    >
                        <div class="flex items-center gap-1.5">
                            <p class="text-xs font-medium text-muted-foreground">{{ card.label }}</p>
                            <HelpTooltip v-if="card.tooltip" :text="card.tooltip" :aria-label="`Ayuda sobre ${card.label}`" />
                        </div>
                        <div class="mt-2 flex items-end justify-between gap-2">
                            <p class="text-2xl font-bold tabular-nums leading-tight text-foreground">{{ card.value }}</p>
                            <span
                                v-if="card.pill"
                                :class="['mb-0.5 inline-flex shrink-0 items-center gap-1 rounded-lg border px-2.5 py-1 text-sm font-bold', card.pill.cls]"
                            >
                                <TrendingUp   v-if="card.pill.dir === 'up'"      class="h-4 w-4" />
                                <TrendingDown v-else-if="card.pill.dir === 'down'" class="h-4 w-4" />
                                <Minus        v-else                               class="h-4 w-4" />
                                {{ card.pill.text }}
                            </span>
                        </div>
                        <p class="mt-auto pt-2 text-[11px] text-muted-foreground">{{ card.sub }}</p>
                    </article>
                </div>
            </section>

        </template>

        <div v-if="error" class="rounded-lg bg-destructive/10 px-4 py-3 text-sm text-destructive ring-1 ring-destructive/30">
            {{ error }}
        </div>
    </div>
</template>
