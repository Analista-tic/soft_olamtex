<script setup lang="ts">
import HoverTooltip from '@/components/common/HoverTooltip.vue';
import TableStatusBadge from '@/components/common/TableStatusBadge.vue';
import type { PurchaseDocumentChain } from '@/domain/Expenses';
import { formatDate, formatDateTime } from '@/domain/Expenses/format';
import {
    getPurchaseInvoiceStatus,
    getPurchaseInvoiceStatusHint,
    getPurchaseOrderStatus,
    getPurchaseRequestStatus,
    hasApprovedDifference,
} from '@/domain/Expenses/statuses';
import axios from 'axios';
import { ChevronRight, ExternalLink, Info } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    /** Endpoint `.../{id}/trazabilidad`; sin url (documento nuevo) el panel no se muestra. */
    url?: string | null;
}>();

const chain = ref<PurchaseDocumentChain | null>(null);

async function load() {
    chain.value = null;
    if (!props.url) return;
    try {
        const { data } = await axios.get(props.url);
        chain.value = data?.data?.chain ?? null;
    } catch {
        chain.value = null;
    }
}

watch(() => props.url, load, { immediate: true });

const isCurrent = (type: 'request' | 'order' | 'invoice', id: number) => chain.value?.current.type === type && chain.value.current.id === id;

function dayKey(value: string | null | undefined): number | null {
    if (!value) return null;
    const date = /^\d{4}-\d{2}-\d{2}$/.test(value) ? new Date(`${value}T00:00:00`) : new Date(value);
    if (Number.isNaN(date.getTime())) return null;
    return Math.floor(new Date(date.getFullYear(), date.getMonth(), date.getDate()).getTime() / 86_400_000);
}

/** Cumplimiento de entrega: la factura actual, o todas las de la orden cuando se mira la orden o la solicitud. */
const deliveryStatus = computed<{ text: string; tone: 'ok' | 'late' | 'pending' } | null>(() => {
    const c = chain.value;
    if (!c || c.orders.length !== 1) return null;
    const expected = dayKey(c.orders[0].delivery_date);
    if (expected === null) return null;

    const invoices = c.current.type === 'invoice' ? c.invoices.filter((i) => i.id === c.current.id) : c.invoices;
    const valid = invoices.filter((i) => i.status !== 'rejected');
    if (valid.length === 0 || valid.some((i) => !i.received_at)) return { text: 'Aún sin recibir', tone: 'pending' };

    const received = Math.max(...valid.map((i) => dayKey(i.received_at) ?? 0));
    const diff = received - expected;
    if (diff <= 0) return { text: diff === 0 ? 'Llegó en la fecha estimada' : `Llegó ${Math.abs(diff)} día(s) antes`, tone: 'ok' };
    return { text: `Llegó con ${diff} día(s) de retraso`, tone: 'late' };
});

const noAccessTitle = 'No tienes permiso para abrir este documento';
</script>

<template>
    <section v-if="chain" class="rounded-lg bg-muted/30 p-3" aria-label="Trazabilidad del documento">
        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
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

        <ol class="grid gap-2 text-sm md:grid-cols-[1fr_auto_1fr_auto_1fr_auto_1fr] md:items-start">
            <!-- Solicitud -->
            <li class="min-w-0">
                <p class="mb-1 text-xs font-medium uppercase tracking-wide text-muted-foreground">Solicitud</p>
                <div
                    v-if="chain.request"
                    class="rounded-md border bg-background p-2"
                    :class="isCurrent('request', chain.request.id) ? 'border-primary ring-1 ring-primary' : 'border-border'"
                >
                    <HoverTooltip :text="chain.request.url ? 'Abrir en una pestaña nueva' : noAccessTitle">
                        <component
                            :is="chain.request.url ? 'a' : 'span'"
                            :href="chain.request.url ?? undefined"
                            :target="chain.request.url ? '_blank' : undefined"
                            rel="noopener"
                            class="inline-flex items-center gap-1 font-medium"
                            :class="chain.request.url ? 'text-primary hover:underline' : 'text-foreground'"
                        >
                            {{ chain.request.number || `#${chain.request.id}` }}
                            <ExternalLink v-if="chain.request.url" class="h-3 w-3" />
                        </component>
                    </HoverTooltip>
                    <div class="mt-1 flex flex-wrap items-center gap-1">
                        <TableStatusBadge v-bind="getPurchaseRequestStatus(chain.request.status)" class="px-2 py-0.5" />
                        <span v-if="isCurrent('request', chain.request.id)" class="text-xs text-primary">Actual</span>
                    </div>
                    <p class="mt-1 text-xs text-muted-foreground">Fecha: {{ formatDate(chain.request.date) }}</p>
                </div>
                <p v-else class="text-xs text-muted-foreground">Sin solicitud asociada</p>
            </li>

            <li class="hidden pt-7 text-muted-foreground md:block" aria-hidden="true"><ChevronRight class="h-4 w-4" /></li>

            <!-- Órdenes -->
            <li class="min-w-0">
                <p class="mb-1 text-xs font-medium uppercase tracking-wide text-muted-foreground">Orden de compra</p>
                <div v-if="chain.orders.length" class="space-y-1.5">
                    <div
                        v-for="order in chain.orders"
                        :key="order.id"
                        class="rounded-md border bg-background p-2"
                        :class="isCurrent('order', order.id) ? 'border-primary ring-1 ring-primary' : 'border-border'"
                    >
                        <HoverTooltip :text="order.url ? 'Abrir en una pestaña nueva' : noAccessTitle">
                            <component
                                :is="order.url ? 'a' : 'span'"
                                :href="order.url ?? undefined"
                                :target="order.url ? '_blank' : undefined"
                                rel="noopener"
                                class="inline-flex items-center gap-1 font-medium"
                                :class="order.url ? 'text-primary hover:underline' : 'text-foreground'"
                            >
                                {{ order.number || `#${order.id}` }}
                                <ExternalLink v-if="order.url" class="h-3 w-3" />
                            </component>
                        </HoverTooltip>
                        <div class="mt-1 flex flex-wrap items-center gap-1">
                            <TableStatusBadge v-bind="getPurchaseOrderStatus(order.status)" class="px-2 py-0.5" />
                            <span v-if="isCurrent('order', order.id)" class="text-xs text-primary">Actual</span>
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">Fecha: {{ formatDate(order.date) }}</p>
                        <p class="text-xs text-muted-foreground">Entrega estimada: {{ formatDate(order.delivery_date) }}</p>
                    </div>
                </div>
                <p v-else class="text-xs text-muted-foreground">Sin orden asociada</p>
            </li>

            <li class="hidden pt-7 text-muted-foreground md:block" aria-hidden="true"><ChevronRight class="h-4 w-4" /></li>

            <!-- Facturas -->
            <li class="min-w-0">
                <p class="mb-1 text-xs font-medium uppercase tracking-wide text-muted-foreground">Facturas</p>
                <div v-if="chain.invoices.length" class="space-y-1.5">
                    <div
                        v-for="invoice in chain.invoices"
                        :key="invoice.id"
                        class="rounded-md border bg-background p-2"
                        :class="isCurrent('invoice', invoice.id) ? 'border-primary ring-1 ring-primary' : 'border-border'"
                    >
                        <HoverTooltip :text="invoice.url ? 'Abrir en una pestaña nueva' : noAccessTitle">
                            <component
                                :is="invoice.url ? 'a' : 'span'"
                                :href="invoice.url ?? undefined"
                                :target="invoice.url ? '_blank' : undefined"
                                rel="noopener"
                                class="inline-flex items-center gap-1 font-medium"
                                :class="invoice.url ? 'text-primary hover:underline' : 'text-foreground'"
                            >
                                {{ invoice.number || `#${invoice.id}` }}
                                <ExternalLink v-if="invoice.url" class="h-3 w-3" />
                            </component>
                        </HoverTooltip>
                        <div class="mt-1 flex flex-wrap items-center gap-1">
                            <HoverTooltip :text="getPurchaseInvoiceStatusHint(invoice.status, invoice.approval_status)">
                                <TableStatusBadge v-bind="getPurchaseInvoiceStatus(invoice.status, invoice.approval_status)" class="px-2 py-0.5" />
                            </HoverTooltip>
                            <HoverTooltip
                                v-if="hasApprovedDifference(invoice.approval_status, invoice.has_difference)"
                                text="Tiene una diferencia frente a la orden, ya aprobada"
                            >
                                <Info class="h-3.5 w-3.5 cursor-help text-sky-600" />
                            </HoverTooltip>
                            <span v-if="isCurrent('invoice', invoice.id)" class="text-xs text-primary">Actual</span>
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">Fecha: {{ formatDate(invoice.date) }}</p>
                        <p class="text-xs text-muted-foreground">Registrada: {{ formatDateTime(invoice.created_at) }}</p>
                    </div>
                </div>
                <p v-else class="text-xs text-muted-foreground">Sin facturas</p>
            </li>

            <li class="hidden pt-7 text-muted-foreground md:block" aria-hidden="true"><ChevronRight class="h-4 w-4" /></li>

            <!-- Recepción -->
            <li class="min-w-0">
                <p class="mb-1 text-xs font-medium uppercase tracking-wide text-muted-foreground">Recepción</p>
                <div v-if="chain.invoices.length" class="space-y-1.5">
                    <div v-for="invoice in chain.invoices" :key="invoice.id" class="rounded-md border border-border bg-background p-2">
                        <p class="text-xs text-muted-foreground">{{ invoice.number || `#${invoice.id}` }}</p>
                        <p class="font-medium" :class="invoice.received_at ? 'text-foreground' : 'text-muted-foreground'">
                            {{
                                invoice.received_at ? formatDateTime(invoice.received_at) : invoice.status === 'rejected' ? 'Rechazada' : 'Pendiente'
                            }}
                        </p>
                    </div>
                </div>
                <p v-else class="text-xs text-muted-foreground">Aún no se recibe</p>
            </li>
        </ol>
    </section>
</template>
