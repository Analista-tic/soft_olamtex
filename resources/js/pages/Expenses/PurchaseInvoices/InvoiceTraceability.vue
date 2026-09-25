<script setup lang="ts">
import axios from 'axios';
import { computed, ref, watch } from 'vue';

type Traceability = {
    request: { number: string | null; date: string | null; created_at: string | null } | null;
    order: { number: string | null; date: string | null; created_at: string | null; delivery_date: string | null } | null;
    received_at: string | null;
    invoice: { date: string | null; created_at: string | null };
};

const props = defineProps<{
    /** Id de la factura; sin id (factura nueva) el panel no se muestra. */
    invoiceId?: number | null;
    /** Prefijo de rutas: facturas normales o de tela. */
    baseUrl?: string;
}>();

const traceability = ref<Traceability | null>(null);

async function load() {
    traceability.value = null;
    const id = Number(props.invoiceId ?? 0);
    if (!id) return;
    try {
        const { data } = await axios.get(`${props.baseUrl ?? '/gastos/facturas-compra'}/${id}/trazabilidad`);
        traceability.value = data?.data ?? null;
    } catch {
        traceability.value = null;
    }
}

watch(() => [props.invoiceId, props.baseUrl], load, { immediate: true });

/** Fechas "YYYY-MM-DD" se leen como día calendario (sin desfase de zona horaria); los timestamps traen fecha y hora. */
function formatDate(value: string | null | undefined, withTime = false): string {
    if (!value) return '—';
    if (/^\d{4}-\d{2}-\d{2}$/.test(value)) {
        const [y, m, d] = value.split('-');
        return `${d}/${m}/${y}`;
    }
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    return withTime
        ? date.toLocaleString('es-CO', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
        : date.toLocaleDateString('es-CO', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function dayKey(value: string | null | undefined): number | null {
    if (!value) return null;
    const date = /^\d{4}-\d{2}-\d{2}$/.test(value) ? new Date(`${value}T00:00:00`) : new Date(value);
    if (Number.isNaN(date.getTime())) return null;
    return Math.floor(new Date(date.getFullYear(), date.getMonth(), date.getDate()).getTime() / 86_400_000);
}

const deliveryStatus = computed<{ text: string; tone: 'ok' | 'late' | 'pending' } | null>(() => {
    const t = traceability.value;
    const expected = dayKey(t?.order?.delivery_date);
    if (expected === null) return null;
    const received = dayKey(t?.received_at);
    if (received === null) return { text: 'Aún sin recibir', tone: 'pending' };
    const diff = received - expected;
    if (diff <= 0) return { text: diff === 0 ? 'Llegó en la fecha estimada' : `Llegó ${Math.abs(diff)} día(s) antes`, tone: 'ok' };
    return { text: `Llegó con ${diff} día(s) de retraso`, tone: 'late' };
});

const steps = computed(() => {
    const t = traceability.value;
    if (!t) return [];
    return [
        {
            key: 'request',
            title: 'Solicitud',
            ref: t.request?.number,
            date: formatDate(t.request?.date),
            hint: t.request ? 'Fecha de la solicitud' : 'Sin solicitud asociada',
        },
        {
            key: 'order',
            title: 'Orden de compra',
            ref: t.order?.number,
            date: formatDate(t.order?.date),
            hint: t.order ? 'Fecha de la orden' : 'Sin orden asociada',
        },
        { key: 'delivery', title: 'Entrega estimada', ref: null, date: formatDate(t.order?.delivery_date), hint: 'Prometida en la orden' },
        {
            key: 'received',
            title: 'Recepción',
            ref: null,
            date: formatDate(t.received_at, true),
            hint: t.received_at ? 'Cuando llegó la mercancía' : 'Aún no se recibe',
        },
        {
            key: 'invoice',
            title: 'Factura generada',
            ref: null,
            date: formatDate(t.invoice.created_at, true),
            hint: `Fecha de la factura: ${formatDate(t.invoice.date)}`,
        },
    ];
});
</script>

<template>
    <section v-if="steps.length" class="rounded-lg border border-border bg-muted/30 p-4" aria-label="Trazabilidad de fechas">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-sm font-medium text-foreground">Trazabilidad</h3>
            <span
                v-if="deliveryStatus"
                class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                :class="{
                    'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300': deliveryStatus.tone === 'ok',
                    'bg-red-100 text-red-800 dark:bg-red-500/15 dark:text-red-300': deliveryStatus.tone === 'late',
                    'bg-muted text-muted-foreground': deliveryStatus.tone === 'pending',
                }"
            >
                {{ deliveryStatus.text }}
            </span>
        </div>
        <ol class="grid grid-cols-2 gap-3 text-sm md:grid-cols-5">
            <li v-for="step in steps" :key="step.key" class="min-w-0">
                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ step.title }}</p>
                <p class="font-medium text-foreground">{{ step.date }}</p>
                <p v-if="step.ref" class="truncate text-xs text-muted-foreground">{{ step.ref }}</p>
                <p class="text-xs text-muted-foreground">{{ step.hint }}</p>
            </li>
        </ol>
    </section>
</template>
