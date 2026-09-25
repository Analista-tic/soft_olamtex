import { computed, type Ref } from 'vue';

import { withholdingAmount, type WithholdingLike } from '@/domain/Expenses/withholdings';

interface TotalsSource {
    items?: Array<{ total?: number | string | null; total_tax?: number | string | null }> | null;
    withholdings?: WithholdingLike[] | null;
}

/**
 * Totales de una factura de compra (subtotal, IVA, retenciones y total a pagar).
 * Única fuente de cálculo para los diálogos y páginas de factura normal y de tela.
 * Debe coincidir con App\Support\Finance\PurchaseWithholdingCalculator en el backend.
 */
export function usePurchaseInvoiceTotals(form: Ref<TotalsSource>) {
    const subtotal = computed(() => (form.value.items || []).reduce((acc, item) => acc + (Number(item.total) || 0), 0));
    const iva = computed(() => (form.value.items || []).reduce((acc, item) => acc + (Number(item.total_tax) || 0), 0));
    const total = computed(() => subtotal.value + iva.value);

    function getWithholdingAmount(withholding: WithholdingLike): number {
        return withholdingAmount(withholding, subtotal.value, iva.value);
    }

    const retencionesTotal = computed(() => (form.value.withholdings || []).reduce((acc, w) => acc + getWithholdingAmount(w), 0));
    const totalPagar = computed(() => total.value - retencionesTotal.value);

    return { subtotal, iva, total, retencionesTotal, totalPagar, getWithholdingAmount };
}
