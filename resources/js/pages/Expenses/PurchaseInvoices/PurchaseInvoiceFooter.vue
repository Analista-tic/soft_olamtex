<script setup lang="ts">
/**
 * Parte inferior común de las facturas de compra (normal y de tela), siempre en este orden:
 * detalle (tabla de ítems, fuera de este componente) → retenciones → totales → observación.
 */
import TextareaField from '@/components/TextareaField.vue';
import type { PurchaseInvoiceWithholding } from '@/domain/Expenses';

import PurchaseInvoiceSummary from './PurchaseInvoiceSummary.vue';
import PurchaseInvoiceWithholdingsTable from './PurchaseInvoiceWithholdingsTable.vue';

defineProps<{
    withholdings: PurchaseInvoiceWithholding[];
    description: string | null | undefined;
    subtotal: number;
    iva: number;
    ivaLabel?: string;
    retencionesTotal: number;
    total: number;
    totalPagar: number;
    getWithholdingAmount: (withholding: PurchaseInvoiceWithholding) => number;
    isReadOnly?: boolean;
}>();

defineEmits<{
    (e: 'update:withholdings', value: PurchaseInvoiceWithholding[]): void;
    (e: 'update:description', value: string): void;
}>();
</script>

<template>
    <div class="space-y-6">
        <PurchaseInvoiceWithholdingsTable
            :modelValue="withholdings"
            :invoiceSubtotal="subtotal"
            :invoiceIva="iva"
            :isReadOnly="isReadOnly"
            @update:modelValue="(value) => $emit('update:withholdings', value)"
        />

        <div class="flex w-full flex-col items-end">
            <PurchaseInvoiceSummary
                :subtotal="subtotal"
                :iva="iva"
                :iva-label="ivaLabel"
                :retenciones-total="retencionesTotal"
                :total="total"
                :total-pagar="totalPagar"
                :withholdings="withholdings"
                :get-withholding-amount="(w) => getWithholdingAmount(w as PurchaseInvoiceWithholding)"
            />
        </div>

        <div class="flex flex-col gap-1">
            <label class="text-sm font-medium">Observación</label>
            <TextareaField
                :modelValue="description ?? ''"
                data-no-uppercase
                label=""
                placeholder="Agrega aquí observaciones adicionales para la factura de compra"
                :readonly="isReadOnly"
                @update:modelValue="(value) => $emit('update:description', String(value ?? ''))"
            />
        </div>
    </div>
</template>
