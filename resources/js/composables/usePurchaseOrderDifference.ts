import { computed, type Ref } from 'vue';

interface OrderItemSnapshot {
    price?: number | string | null;
    quantity?: number | string | null;
    already_invoiced_qty?: number | string | null;
}

interface InvoiceItemLike {
    price?: number | string | null;
    quantity?: number | string | null;
    original_order_item?: OrderItemSnapshot | null;
}

/** Precio sugerido de un ítem: el de la orden de compra original (null si no viene de una orden). */
function suggestedPrice(item: InvoiceItemLike): number | null {
    if (!item.original_order_item) return null;
    const price = Number(item.original_order_item.price);
    return Number.isNaN(price) ? null : price;
}

/** Cantidad sugerida: lo pendiente por facturar de la orden (cantidad menos lo ya facturado en facturas parciales). */
function suggestedQuantity(item: InvoiceItemLike): number | null {
    const original = item.original_order_item;
    if (!original) return null;
    const quantity = Number(original.quantity);
    if (Number.isNaN(quantity)) return null;
    const alreadyInvoiced = Number(original.already_invoiced_qty ?? 0);
    return alreadyInvoiced > 0 ? Math.max(0, quantity - alreadyInvoiced) : quantity;
}

/**
 * Diferencia de una factura frente a su orden de compra: hay diferencia cuando el total cambió y además
 * se modificó el precio o la cantidad de algún ítem respecto a lo sugerido por la orden.
 * Compartido por las facturas de compra normal y de tela.
 */
export function usePurchaseOrderDifference(form: Ref<{ items?: InvoiceItemLike[] | null }>, orderTotal: Ref<number | null>, total: Ref<number>) {
    const amountDifference = computed(() => (orderTotal.value != null ? Number(total.value) - orderTotal.value : 0));

    const hasModifiedPriceOrQuantity = computed(() =>
        (Array.isArray(form.value.items) ? form.value.items : []).some((item) => {
            const price = suggestedPrice(item);
            const quantity = suggestedQuantity(item);
            return (price !== null && Number(item.price) !== price) || (quantity !== null && Number(item.quantity) !== quantity);
        }),
    );

    const hasDifference = computed(() => orderTotal.value != null && Math.abs(amountDifference.value) > 0.01 && hasModifiedPriceOrQuantity.value);

    return { amountDifference, hasModifiedPriceOrQuantity, hasDifference };
}
