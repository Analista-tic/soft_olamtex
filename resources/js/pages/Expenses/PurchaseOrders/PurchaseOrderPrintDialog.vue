<script setup lang="ts">
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import type { PurchaseOrder } from '@/domain/purchases';
import { computed, watch, ref } from 'vue';
import { useCompanyInfo } from '@/composables/useCompanyInfo';

const props = defineProps<{
    isOpen: boolean;
    order: PurchaseOrder | null;
}>();
const emit = defineEmits<{
    (e: 'close'): void;
}>();

const dialogOpen = ref(props.isOpen);

watch(() => props.isOpen, (val) => {
    dialogOpen.value = val;
});
watch(dialogOpen, (val) => {
    if (!val) emit('close');
});

const { company, logoUrl } = useCompanyInfo();

// Subtotal: suma de (Precio SIN Impuesto * cantidad) de cada ítem
const subtotal = computed(() =>
    (props.order?.items || []).reduce((acc, item) => acc + (Number(item.price) * Number(item.quantity) || 0), 0)
);
// IVA total: suma de ((Precio SIN Impuesto * cantidad) * (tax/100)) de cada ítem
const iva = computed(() =>
    (props.order?.items || []).reduce((acc, item) => acc + ((Number(item.price) * Number(item.quantity) * (Number(item.tax) || 0)) / 100), 0)
);
// Total: suma de (Precio SIN Impuesto * cantidad + IVA) de cada ítem, o simplemente subtotal + iva
const total = computed(() => subtotal.value + iva.value);

function print() {
    window.print();
}
</script>

<template>
    <Dialog v-model:open="dialogOpen">
        <DialogContent
            class="max-h-screen overflow-y-auto sm:max-w-5xl min-h-[297mm] min-w-[210mm] print:!max-w-none print:!w-[210mm] print:!h-[297mm] print:!rounded-none print:!border-0 print:!shadow-none print:!p-0"
        >
            <DialogHeader class="print:hidden">
                <DialogTitle>Orden de Compra</DialogTitle>
            </DialogHeader>
            <div class="p-4 print:p-12 print:bg-white print:h-[297mm] print:w-[210mm] flex flex-col justify-between" style="min-height: 277mm;">
                <div>
                    <div class="grid grid-cols-3 items-start gap-4 mb-4">
                        <div>
                            <div class="font-bold"># Orden de Compra</div>
                            <div>{{ props.order?.id }}</div>
                        </div>
                        <div class="flex h-full items-center justify-center rounded p-4">
                            <img
                                :src="logoUrl"
                                alt="Logotipo de la empresa"
                                class="max-h-24 max-w-[160px] w-auto object-contain"
                            />
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
                    <div class="grid grid-cols-3 gap-4 mb-4">
                        <div>
                            <div class="font-bold">Proveedor</div>
                            <div>{{ props.order?.contact?.full_name }}</div>
                        </div>
                        <div>
                            <div class="font-bold">Identificación</div>
                            <div>{{ props.order?.contact?.id_number }}</div>
                        </div>
                        <div>
                            <div class="font-bold">Teléfono</div>
                            <div>{{ props.order?.contact?.phone }}</div>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-4 mb-4">
                        <div>
                            <div class="font-bold">Fecha de creación</div>
                            <div>{{ props.order?.date }}</div>
                        </div>
                        <div>
                            <div class="font-bold">Fecha de entrega</div>
                            <div>{{ props.order?.delivery_date }}</div>
                        </div>
                        <div>
                            <div class="font-bold"># pedido proveedor</div>
                            <div>{{ props.order?.supplier_order_number }}</div>
                        </div>
                    </div>
                    <div class="mb-4">
                        <div class="font-bold mb-2">Ítems</div>
                        <table class="w-full text-sm border-collapse print:border-none">
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
                                <tr v-for="(item, idx) in props.order?.items || []" :key="idx">
                                    <td class="border px-2 py-1 print:border print:border-gray-300">{{ idx + 1 }}</td>
                                    <td class="border px-2 py-1 print:border print:border-gray-300">
                                        {{ item.article?.name || '' }}
                                    </td>
                                    <td class="border px-2 py-1 print:border print:border-gray-300">{{ item.description }}</td>
                                    <td class="border px-2 py-1 print:border print:border-gray-300 text-center">{{ item.quantity }}</td>
                                    <td class="border ps-0 py-1 pe-10 print:border print:border-gray-300 text-right">{{ Number(item.price).toLocaleString('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 }) }}</td>
                                    <td class="border px-2 py-1 print:border print:border-gray-300 text-center">{{ Math.round(item.tax) }} %</td>
                                    <td class="border ps-0 py-1 pe-10 print:border print:border-gray-300 text-right">{{ Number(item.total).toLocaleString('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 }) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="flex flex-col items-end space-y-0 text-sm mb-4 mt-2">
                    <div class="flex gap-1">
                        <div class="flex flex-col items-end">
                            <span class="font-semibold">Subtotal:</span>
                            <span class="font-semibold">IVA total:</span>
                            <span class="font-semibold">Total orden de compra:</span>
                        </div>
                        <div class="flex min-w-[120px] flex-col items-end text-right pe-10">
                            <span>{{ Number(subtotal || 0).toLocaleString('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 }) }}</span>
                            <span>{{ Number(iva || 0).toLocaleString('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 }) }}</span>
                            <span class="font-bold">{{ Number(total || 0).toLocaleString('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 }) }}</span>
                        </div>
                    </div>
                </div>
                <div>
                    <div class="font-bold">Observaciones</div>
                    <div>{{ props.order?.description }}</div>
                </div>
                <div class="flex-1"></div>
                <div class="flex justify-between items-end mt-12 print:mt-24">
                    <div class="flex flex-col items-center">
                        <span class="border-t border-gray-400 w-48 block"></span>
                        <span class="text-xs mt-1">Firma Autorizada</span>
                    </div>
                    <div class="flex flex-col items-center">
                        <span class="border-t border-gray-400 w-48 block"></span>
                        <span class="text-xs mt-1">Firma Proveedor</span>
                    </div>
                </div>
            </div>
            <DialogFooter class="mt-4 flex justify-end gap-4 print:hidden">
                <Button variant="outline" type="button" @click="dialogOpen = false">Cerrar</Button>
                <Button variant="default" type="button" @click="print">Imprimir</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
@media print {
    :root, html, body {
        width: 210mm !important;
        height: 297mm !important;
        margin: 0 !important;
        padding: 0 !important;
        background: #fff !important;
    }
    .print\:hidden { display: none !important; }
    .print\:max-w-none { max-width: none !important; }
    .print\:w-\[210mm\] { width: 210mm !important; }
    .print\:h-\[297mm\] { height: 297mm !important; }
    .print\:rounded-none { border-radius: 0 !important; }
    .print\:border-0 { border: 0 !important; }
    .print\:shadow-none { box-shadow: none !important; }
    .print\:p-0 { padding: 0 !important; }
    .print\:p-12 { padding: 12mm !important; }
    .print\:bg-white { background: #fff !important; }
    .print\:overflow-visible { overflow: visible !important; }
    .print\:mt-24 { margin-top: 48mm !important; }
    .border, .print\:border { border: 1px solid #d1d5db !important; }
    .print\:border-none, .print\:border-0 { border: none !important; }
    .print\:bg-transparent { background: transparent !important; }
    .DialogContent, .DialogHeader, .DialogTitle, .DialogFooter {
        box-shadow: none !important;
        border: none !important;
    }
}
</style>
