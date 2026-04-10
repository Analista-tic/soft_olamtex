<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useCompanyInfo } from '@/composables/useCompanyInfo';
import type { PurchaseInvoice } from '@/domain/Expenses';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    isOpen: boolean;
    invoice: PurchaseInvoice | null;
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

const subtotal = computed(() => (props.invoice?.items || []).reduce((acc, item) => acc + (Number(item.total) || 0), 0));
const iva = computed(() => (props.invoice?.items || []).reduce((acc, item) => acc + (Number(item.total_tax) || 0), 0));
const total = computed(() => subtotal.value + iva.value);

/** Base efectiva: si base_amount es null se asume el subtotal. */
function getWithholdingBase(w: { base_amount?: number | null; percentage?: number }): number {
    const b = w.base_amount;
    if (b != null && Number.isFinite(Number(b))) return Number(b);
    return Number(subtotal.value) || 0;
}
function getWithholdingAmount(w: { base_amount?: number | null; percentage?: number }): number {
    return (getWithholdingBase(w) * (Number(w.percentage) || 0)) / 100;
}
const retencionesTotal = computed(() => (props.invoice?.withholdings || []).reduce((acc, w) => acc + getWithholdingAmount(w), 0));
const totalPagar = computed(() => Number(total.value) - Number(retencionesTotal.value));

function print() {
    window.print();
}
</script>

<template>
    <Dialog v-model:open="dialogOpen">
        <DialogContent
            class="sm:max-w-5xl print:!h-[279mm] print:!w-[216mm] print:!max-w-none print:!overflow-visible print:!rounded-none print:!border-0 print:!p-0 print:!shadow-none"
            :style="{ overflow: 'visible', width: '216mm', minWidth: '216mm', height: '279mm', minHeight: '279mm' }"
        >
            <div class="min-h-[270mm] min-w-[216mm] bg-white print:min-h-[279mm] print:min-w-[216mm] print:overflow-visible">
                <DialogHeader class="print:hidden">
                    <DialogTitle>Factura de Compra</DialogTitle>
                </DialogHeader>
                <div
                    class="flex min-h-[270mm] flex-col justify-between p-4 print:h-[279mm] print:min-h-[270mm] print:w-[216mm] print:overflow-visible print:bg-white print:p-12"
                >
                    <div>
                        <div class="mb-4 grid grid-cols-3 items-start gap-4">
                            <div>
                                <div class="font-bold"># Factura de Compra</div>
                                <div>{{ props.invoice?.id }}</div>
                            </div>
                            <div class="flex h-full items-center justify-center rounded p-4">
                                <img :src="logoUrl" alt="Logotipo de la empresa" class="max-h-24 w-auto max-w-[160px] object-contain" />
                            </div>
                            <div>
                                <div class="text-sm font-semibold">Datos empresa</div>
                                <p class="text-xs">
                                    Razón Social:
                                    <span class="font-normal">{{ company?.name || 'Sin nombre' }}</span>
                                </p>
                                <p class="text-xs">
                                    NIT:
                                    <span class="font-normal">{{ company?.nit || 'Sin NIT' }}</span>
                                </p>
                                <p class="text-xs">
                                    Correo:
                                    <span class="font-normal">{{ company?.email || 'Sin correo' }}</span>
                                </p>
                            </div>
                        </div>
                        <div class="mb-4 grid grid-cols-3 gap-4">
                            <div>
                                <div class="font-bold">Proveedor</div>
                                <div>{{ props.invoice?.contact?.full_name }}</div>
                            </div>
                            <div>
                                <div class="font-bold">Identificación</div>
                                <div>{{ props.invoice?.contact?.id_number }}</div>
                            </div>
                            <div>
                                <div class="font-bold">Teléfono</div>
                                <div>{{ props.invoice?.contact?.phone }}</div>
                            </div>
                        </div>
                        <div class="mb-4 grid grid-cols-3 gap-4">
                            <div>
                                <div class="font-bold">Fecha de factura</div>
                                <div>{{ props.invoice?.date }}</div>
                            </div>
                            <div>
                                <div class="font-bold">Fecha de entrega</div>
                                <div>{{ props.invoice?.delivery_date }}</div>
                            </div>
                            <div>
                                <div class="font-bold"># Factura proveedor</div>
                                <div>{{ props.invoice?.supplier_invoice_number }}</div>
                            </div>
                        </div>
                        <div class="mb-4">
                            <div class="mb-2 font-bold">Ítems</div>
                            <table class="w-full border-collapse text-sm print:border-none">
                                <thead>
                                    <tr class="bg-gray-100 print:bg-transparent">
                                        <th class="border px-2 py-1 print:border print:border-gray-300">#</th>
                                        <th class="border px-2 py-1 print:border print:border-gray-300">Item</th>
                                        <th class="border px-2 py-1 print:border print:border-gray-300">Descripción</th>
                                        <th class="border px-2 py-1 print:border print:border-gray-300">Cantidad</th>
                                        <th class="border px-2 py-1 print:border print:border-gray-300">Precio SIN Impuesto unitario</th>
                                        <th class="border px-2 py-1 print:border print:border-gray-300">IVA</th>
                                        <th class="border px-2 py-1 print:border print:border-gray-300">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(item, idx) in props.invoice?.items || []" :key="idx">
                                        <td class="border px-2 py-1 print:border print:border-gray-300">{{ idx + 1 }}</td>
                                        <td class="border px-2 py-1 print:border print:border-gray-300">
                                            {{ item.item?.name || '' }}
                                        </td>
                                        <td class="border px-2 py-1 print:border print:border-gray-300">{{ item.description }}</td>
                                        <td class="border px-2 py-1 text-center print:border print:border-gray-300">{{ item.quantity }}</td>
                                        <td class="border py-1 pe-10 ps-0 text-right print:border print:border-gray-300">
                                            {{
                                                Number(item.price).toLocaleString('es-CO', {
                                                    style: 'currency',
                                                    currency: 'COP',
                                                    minimumFractionDigits: 0,
                                                })
                                            }}
                                        </td>
                                        <td class="border px-2 py-1 text-center print:border print:border-gray-300">{{ Math.round(item.tax) }} %</td>
                                        <td class="border py-1 pe-10 ps-0 text-right print:border print:border-gray-300">
                                            {{
                                                Number(item.total).toLocaleString('es-CO', {
                                                    style: 'currency',
                                                    currency: 'COP',
                                                    minimumFractionDigits: 0,
                                                })
                                            }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <!-- Resumen de totales simplificado como en el dialog -->
                    <div class="mb-4 mt-2 flex w-full flex-col items-end space-y-0 text-sm">
                        <div class="w-full max-w-xs space-y-1 rounded bg-white p-2">
                            <div class="flex items-center justify-between">
                                <span class="font-semibold text-gray-700">Subtotal</span>
                                <span>{{
                                    Number(subtotal || 0).toLocaleString('es-CO', {
                                        style: 'currency',
                                        currency: 'COP',
                                        minimumFractionDigits: 0,
                                        maximumFractionDigits: 0,
                                    })
                                }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="font-semibold text-gray-700">IVA 19%</span>
                                <span>{{
                                    Number(iva || 0).toLocaleString('es-CO', {
                                        style: 'currency',
                                        currency: 'COP',
                                        minimumFractionDigits: 0,
                                        maximumFractionDigits: 0,
                                    })
                                }}</span>
                            </div>
                            <div class="flex items-center justify-between pt-1">
                                <span class="font-bold text-gray-800">Total orden de compra</span>
                                <span class="font-bold text-gray-800">{{
                                    Number(total || 0).toLocaleString('es-CO', {
                                        style: 'currency',
                                        currency: 'COP',
                                        minimumFractionDigits: 0,
                                        maximumFractionDigits: 0,
                                    })
                                }}</span>
                            </div>
                            <!-- Mostrar cada retención individual -->
                            <template v-for="(w, idx) in props.invoice?.withholdings || []" :key="idx">
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-600">- {{ w.name }}</span>
                                    <span class="text-red-700"
                                        >-{{
                                            getWithholdingAmount(w).toLocaleString('es-CO', {
                                                style: 'currency',
                                                currency: 'COP',
                                                minimumFractionDigits: 0,
                                                maximumFractionDigits: 0,
                                            })
                                        }}</span
                                    >
                                </div>
                            </template>
                            <div v-if="(props.invoice?.withholdings || []).length" class="flex items-center justify-between pt-1">
                                <span class="font-semibold text-gray-700">Total retenciones</span>
                                <span class="text-red-700">{{
                                    Number(retencionesTotal || 0).toLocaleString('es-CO', {
                                        style: 'currency',
                                        currency: 'COP',
                                        minimumFractionDigits: 0,
                                        maximumFractionDigits: 0,
                                    })
                                }}</span>
                            </div>
                            <div class="mt-1 flex items-center justify-between pt-2">
                                <span class="text-lg font-bold text-green-700">Total a pagar</span>
                                <span class="text-lg font-bold text-green-700">{{
                                    Number(totalPagar || 0).toLocaleString('es-CO', {
                                        style: 'currency',
                                        currency: 'COP',
                                        minimumFractionDigits: 0,
                                        maximumFractionDigits: 0,
                                    })
                                }}</span>
                            </div>
                        </div>
                    </div>
                    <div>
                        <div class="font-bold">Observaciones</div>
                        <div>{{ props.invoice?.description }}</div>
                    </div>
                    <div class="flex-1"></div>
                    <div class="mt-12 flex items-end justify-between print:mt-24">
                        <div class="flex flex-col items-center">
                            <span class="block w-48 border-t border-gray-400"></span>
                            <span class="mt-1 text-xs">Firma Autorizada</span>
                        </div>
                        <div class="flex flex-col items-center">
                            <span class="block w-48 border-t border-gray-400"></span>
                            <span class="mt-1 text-xs">Firma Proveedor</span>
                        </div>
                    </div>
                </div>
                <DialogFooter class="mt-4 flex justify-end gap-3 print:hidden">
                    <Button variant="outline" size="pill" @click="dialogOpen = false"> Cerrar </Button>
                    <Button variant="default" size="pill" @click="print">
                        Imprimir
                    </Button>
                </DialogFooter>
            </div>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
@media print {
    :root,
    html,
    body {
        width: 216mm !important;
        height: 279mm !important;
        margin: 0 !important;
        padding: 0 !important;
        background: #fff !important;
        overflow: visible !important;
    }
    body,
    #app,
    .DialogContent,
    .print\:bg-white {
        width: 216mm !important;
        height: 279mm !important;
        min-width: 216mm !important;
        min-height: 279mm !important;
        max-width: 216mm !important;
        max-height: 279mm !important;
        overflow: visible !important;
        background: #fff !important;
    }
    .print\:hidden,
    .DialogContent > button,
    .DialogContent > .absolute,
    .DialogContent > .fixed {
        display: none !important;
    }
    .print\:max-w-none {
        max-width: none !important;
    }
    .print\:w-\[216mm\] {
        width: 216mm !important;
    }
    .print\:h-\[279mm\] {
        height: 279mm !important;
    }
    .print\:rounded-none {
        border-radius: 0 !important;
    }
    .print\:border-0 {
        border: 0 !important;
    }
    .print\:shadow-none {
        box-shadow: none !important;
    }
    .print\:p-0 {
        padding: 0 !important;
    }
    .print\:p-12 {
        padding: 12mm !important;
    }
    .print\:bg-white {
        background: #fff !important;
    }
    .print\:overflow-visible {
        overflow: visible !important;
    }
    .print\:mt-24 {
        margin-top: 48mm !important;
    }
    .border,
    .print\:border {
        border: 1px solid #d1d5db !important;
    }
    .print\:border-none,
    .print\:border-0 {
        border: none !important;
    }
    .print\:bg-transparent {
        background: transparent !important;
    }
    .DialogContent,
    .DialogHeader,
    .DialogTitle,
    .DialogFooter {
        box-shadow: none !important;
        border: none !important;
    }
    /* Oculta scrollbars */
    ::-webkit-scrollbar {
        display: none !important;
        width: 0 !important;
    }
    * {
        -ms-overflow-style: none !important;
        scrollbar-width: none !important;
    }
}
</style>
