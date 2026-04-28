<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { useCompanyInfo } from '@/composables/useCompanyInfo';
import type { PurchaseOrder } from '@/domain/Expenses';
import { computed, ref, watch, nextTick } from 'vue';
import printElement from '@/utils/print';

const props = defineProps<{
    isOpen: boolean;
    order: PurchaseOrder | null;
}>();
const emit = defineEmits<{
    (e: 'close'): void;
}>();

const dialogOpen = ref(props.isOpen);

watch(
    () => props.isOpen,
    (val) => {
        dialogOpen.value = val;
    },
);
watch(dialogOpen, (val) => {
    if (!val) emit('close');
});

const { company, logoUrl } = useCompanyInfo();
const printDocumentRef = ref<HTMLElement | null>(null);

// Subtotal: suma de (Precio SIN Impuesto * cantidad) de cada ítem
    const subtotal = computed(() => (props.order?.items || []).reduce((acc, item) => acc + (Number(item.price) * Number(item.quantity) || 0), 0));
// IVA total: suma de ((Precio SIN Impuesto * cantidad) * (tax/100)) de cada ítem
const iva = computed(() =>
    (props.order?.items || []).reduce((acc, item) => acc + (Number(item.price) * Number(item.quantity) * (Number(item.tax) || 0)) / 100, 0),
);
// Total: suma de (Precio SIN Impuesto * cantidad + IVA) de cada ítem, o simplemente subtotal + iva
const total = computed(() => subtotal.value + iva.value);

function formatDate(value?: string | null): string {
    if (!value) return '-';
    const date = new Date(value);
    if (isNaN(date.getTime())) return String(value);
    return date.toLocaleDateString('es-CO', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}






async function print() {
    await nextTick();
    await new Promise((res) => requestAnimationFrame(res));

    const el = printDocumentRef.value || document.getElementById('print-document');
    if (!el) {
        console.error('No printable element found');
        return;
    }
    if (!el.innerHTML || el.innerHTML.trim().length === 0) {
        console.error('Printable element is empty');
        return;
    }

    try {
        await printElement(el, { title: `Orden-${props.order?.document_number || props.order?.id}` });
    } catch (e) {
        console.error('printElement failed', e);
    }
}
</script>

<template>
    <Dialog v-model:open="dialogOpen">
        <DialogContent :hideClose="true" class="flex max-h-[90vh] w-[680px] max-w-[680px] flex-col gap-0 overflow-hidden p-0 sm:max-w-[680px]">

            <!-- Header del dialog (oculto al imprimir) -->
            <div class="flex items-center justify-between border-b px-6 py-4 print:hidden">
                <h2 class="text-base font-semibold">Orden de Compra</h2>
                <div class="flex gap-2">
                    <Button variant="outline" size="sm" @click="dialogOpen = false">Cerrar</Button>
                    <Button variant="default" size="sm" @click="print">
                        Imprimir
                    </Button>
                </div>
            </div>

            <!-- Documento imprimible -->
                <div id="print-document" ref="printDocumentRef" class="overflow-y-auto bg-white p-8 text-sm text-gray-900">

                <!-- Cabecera: N° documento | Logo | Datos empresa -->
                <div class="mb-6 grid grid-cols-3 items-start gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Orden de Compra</p>
                        <p class="mt-1 text-2xl font-bold text-gray-900">{{ props.order?.document_number || props.order?.id }}</p>
                    </div>
                    <div class="flex justify-center">
                        <img :src="logoUrl" alt="Logo" class="max-h-16 max-w-[140px] object-contain" />
                    </div>
                    <div class="text-right text-xs text-gray-600">
                        <p class="font-semibold text-gray-800">{{ company?.name }}</p>
                        <p>NIT: {{ company?.nit }}</p>
                        <p>{{ company?.email }}</p>
                    </div>
                </div>

                <hr class="mb-4 border-gray-200" />

                <!-- Proveedor -->
                <div class="mb-4 grid grid-cols-3 gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Proveedor</p>
                        <p class="mt-1 font-medium">{{ props.order?.supplier?.full_name || props.order?.contact?.full_name || '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Identificación</p>
                        <p class="mt-1">{{ props.order?.supplier?.id_number || props.order?.contact?.id_number || '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Teléfono</p>
                        <p class="mt-1">{{ props.order?.supplier?.phone || props.order?.contact?.phone || '-' }}</p>
                    </div>
                </div>

                <!-- Fechas -->
                <div class="mb-4 grid grid-cols-3 gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Fecha de creación</p>
                        <p class="mt-1">{{ formatDate(props.order?.date) }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Fecha de entrega</p>
                        <p class="mt-1">{{ formatDate(props.order?.delivery_date) }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">N° Orden proveedor</p>
                        <p class="mt-1">{{ props.order?.supplier_order_number || '-' }}</p>
                    </div>
                </div>

                <hr class="mb-4 border-gray-200" />

                <!-- Tabla de ítems -->
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Ítems</p>
                <table class="mb-4 w-full border-collapse text-xs">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="border border-gray-200 px-2 py-2 text-left">#</th>
                            <th class="border border-gray-200 px-2 py-2 text-left">Ítem</th>
                            <th class="border border-gray-200 px-2 py-2 text-left">Descripción</th>
                            <th class="border border-gray-200 px-2 py-2 text-right">Cantidad</th>
                            <th class="border border-gray-200 px-2 py-2 text-right">Precio s/imp.</th>
                            <th class="border border-gray-200 px-2 py-2 text-right">IVA</th>
                            <th class="border border-gray-200 px-2 py-2 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(item, idx) in props.order?.items || []" :key="idx" class="even:bg-gray-50">
                            <td class="border border-gray-200 px-2 py-1.5">{{ idx + 1 }}</td>
                            <td class="border border-gray-200 px-2 py-1.5">{{ item.item?.name || '-' }}</td>
                            <td class="border border-gray-200 px-2 py-1.5 text-gray-500">{{ item.description || '-' }}</td>
                            <td class="border border-gray-200 px-2 py-1.5 text-right">{{ item.quantity }}</td>
                            <td class="border border-gray-200 px-2 py-1.5 text-right">{{ Number(item.price).toLocaleString('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 }) }}</td>
                            <td class="border border-gray-200 px-2 py-1.5 text-right">{{ Math.round(item.tax ?? 0) }}%</td>
                            <td class="border border-gray-200 px-2 py-1.5 text-right font-medium">{{ Number(item.total).toLocaleString('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 }) }}</td>
                        </tr>
                    </tbody>
                </table>

                <!-- Totales -->
                <div class="mb-4 flex justify-end">
                    <div class="w-56 space-y-1 rounded-md border border-gray-200 bg-gray-50 p-3 text-xs">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Subtotal</span>
                            <span>{{ Number(subtotal).toLocaleString('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 }) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">IVA</span>
                            <span>{{ Number(iva).toLocaleString('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 }) }}</span>
                        </div>
                        <div class="flex justify-between border-t border-gray-200 pt-1 font-bold">
                            <span>Total</span>
                            <span>{{ Number(total).toLocaleString('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 }) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Observaciones -->
                <div v-if="props.order?.description" class="mb-6">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Observaciones</p>
                    <p class="mt-1 text-gray-700">{{ props.order?.description }}</p>
                </div>

                <!-- Firmas -->
                <div class="mt-10 flex justify-between">
                    <div class="text-center">
                        <div class="mb-1 w-40 border-t border-gray-400"></div>
                        <p class="text-xs text-gray-500">Firma Autorizada</p>
                    </div>
                    <div class="text-center">
                        <div class="mb-1 w-40 border-t border-gray-400"></div>
                        <p class="text-xs text-gray-500">Firma Proveedor</p>
                    </div>
                </div>

            </div>
        </DialogContent>
    </Dialog>
</template>

<style>
/* Sin scoped — necesita afectar el DOM global al imprimir */
@media print {
    /* Ocultar todo el body */
    body * {
        visibility: hidden !important;
    }

    /* Mostrar solo el documento (o su clon) y sus hijos */
    #print-document,
    #print-document-clone,
    #print-document *,
    #print-document-clone * {
        visibility: visible !important;
    }

    /* Posicionar el documento en la esquina superior izquierda */
    #print-document,
    #print-document-clone {
        position: absolute !important;
        top: 0 !important;
        left: 0 !important;
        width: 100% !important;
        padding: 15mm !important;
        background: white !important;
    }
}
</style>
