<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useCompanyInfo } from '@/composables/useCompanyInfo';
import { getInventoryAdjustmentReasonLabel } from '@/lib/inventoryAdjustmentReasons';
import { computed, ref, watch } from 'vue';

type AdjustmentLine = {
    article?: { name?: string; reference?: string };
    price?: number | string;
    tax?: number | string;
    quantity?: number | string;
    description?: string | null;
    line_total?: number | string;
};

type InventoryAdjustment = {
    id: number;
    document_number: string | null;
    date: string;
    type: 'entrada' | 'salida';
    movement_reason?: string | null;
    observation?: string | null;
    production_order?: string | null;
    subtotal?: number | string;
    iva?: number | string;
    total?: number | string;
    lines?: AdjustmentLine[];
};

const props = defineProps<{
    isOpen: boolean;
    adjustment: InventoryAdjustment | null;
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

const subtotal = computed(() =>
    (props.adjustment?.lines || []).reduce((acc, line) => {
        const price = Number(line.price ?? 0);
        const qty = Number(line.quantity ?? 0);
        return acc + price * qty;
    }, 0),
);

const iva = computed(() =>
    (props.adjustment?.lines || []).reduce((acc, line) => {
        const price = Number(line.price ?? 0);
        const qty = Number(line.quantity ?? 0);
        const tax = Number(line.tax ?? 0);
        return acc + (price * qty * tax) / 100;
    }, 0),
);

const total = computed(() => subtotal.value + iva.value);

function formatCurrency(value: number | string | null | undefined) {
    const num = Number(value ?? 0);
    return num.toLocaleString('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 });
}

function print() {
    window.print();
}

const movementReasonLabel = computed(() => getInventoryAdjustmentReasonLabel(props.adjustment?.movement_reason ?? props.adjustment?.type));
</script>

<template>
    <Dialog v-model:open="dialogOpen">
        <DialogContent
            class="max-h-screen min-h-[297mm] min-w-[210mm] overflow-y-auto sm:max-w-5xl print:!h-[297mm] print:!w-[210mm] print:!max-w-none print:!rounded-none print:!border-0 print:!p-0 print:!shadow-none"
        >
            <DialogHeader class="print:hidden">
                <DialogTitle>Ajuste de Inventario</DialogTitle>
            </DialogHeader>
            <div class="flex min-h-[277mm] flex-col justify-between p-4 print:h-[297mm] print:w-[210mm] print:bg-white print:p-12">
                <div>
                    <div class="mb-4 grid grid-cols-3 items-start gap-4">
                        <div>
                            <div class="font-bold">N° Documento</div>
                            <div>{{ props.adjustment?.document_number || 'Sin número' }}</div>
                        </div>
                        <div class="flex h-full items-center justify-center rounded p-4">
                            <img :src="logoUrl" alt="Logotipo de la empresa" class="max-h-24 w-auto max-w-[160px] object-contain" />
                        </div>
                        <div>
                            <div class="text-sm font-semibold">Datos empresa</div>
                            <p class="text-xs">
                                Razón social:
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
                            <div class="font-bold">Fecha</div>
                            <div>{{ props.adjustment?.date }}</div>
                        </div>
                        <div>
                            <div class="font-bold">Motivo economico</div>
                            <div>{{ movementReasonLabel }}</div>
                        </div>
                        <div>
                            <div class="font-bold">Orden de producción</div>
                            <div>{{ props.adjustment?.production_order || '-' }}</div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="font-bold">Observación</div>
                        <div>{{ props.adjustment?.observation || '-' }}</div>
                    </div>

                    <div class="mb-4">
                        <div class="mb-2 font-bold">Líneas</div>
                        <table class="w-full border-collapse text-sm print:border-none">
                            <thead>
                                <tr class="bg-gray-100 print:bg-transparent">
                                    <th class="border px-2 py-1 print:border print:border-gray-300">#</th>
                                    <th class="border px-2 py-1 print:border print:border-gray-300">Artículo</th>
                                    <th class="border px-2 py-1 print:border print:border-gray-300">Descripción</th>
                                    <th class="border px-2 py-1 print:border print:border-gray-300">Cantidad</th>
                                    <th class="border px-2 py-1 print:border print:border-gray-300">Precio unitario</th>
                                    <th class="border px-2 py-1 print:border print:border-gray-300">Impuesto</th>
                                    <th class="border px-2 py-1 print:border print:border-gray-300">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(line, idx) in props.adjustment?.lines || []" :key="idx">
                                    <td class="border px-2 py-1 text-center print:border print:border-gray-300">{{ idx + 1 }}</td>
                                    <td class="border px-2 py-1 print:border print:border-gray-300">
                                        {{ line.article?.name || '' }}
                                        <span v-if="line.article?.reference"> ({{ line.article.reference }})</span>
                                    </td>
                                    <td class="border px-2 py-1 print:border print:border-gray-300">{{ line.description || '' }}</td>
                                    <td class="border px-2 py-1 text-center print:border print:border-gray-300">{{ line.quantity }}</td>
                                    <td class="border py-1 pe-10 ps-0 text-right print:border print:border-gray-300">
                                        {{ formatCurrency(line.price) }}
                                    </td>
                                    <td class="border px-2 py-1 text-center print:border print:border-gray-300">
                                        {{ Math.round(Number(line.tax ?? 0)) }} %
                                    </td>
                                    <td class="border py-1 pe-10 ps-0 text-right print:border print:border-gray-300">
                                        {{ formatCurrency(line.line_total ?? Number(line.price ?? 0) * Number(line.quantity ?? 0)) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mb-4 mt-2 flex flex-col items-end space-y-0 text-sm">
                    <div class="flex gap-1">
                        <div class="flex flex-col items-end">
                            <span class="font-semibold">Subtotal:</span>
                            <span class="font-semibold">IVA total:</span>
                            <span class="font-semibold">Total ajuste:</span>
                        </div>
                        <div class="flex min-w-[140px] flex-col items-end pe-10 text-right">
                            <span>{{ formatCurrency(props.adjustment?.subtotal ?? subtotal) }}</span>
                            <span>{{ formatCurrency(props.adjustment?.iva ?? iva) }}</span>
                            <span class="font-bold">{{ formatCurrency(props.adjustment?.total ?? total) }}</span>
                        </div>
                    </div>
                </div>

                <div class="mt-12 flex items-end justify-between print:mt-24">
                    <div class="flex flex-col items-center">
                        <span class="block w-48 border-t border-gray-400"></span>
                        <span class="mt-1 text-xs">Firma Autorizada</span>
                    </div>
                    <div class="flex flex-col items-center">
                        <span class="block w-48 border-t border-gray-400"></span>
                        <span class="mt-1 text-xs">Recibido por</span>
                    </div>
                </div>
            </div>

            <DialogFooter class="mt-4 flex flex-col gap-2 md:flex-row md:justify-end print:hidden">
                <div class="w-full md:w-auto">
                    <Button variant="outline" size="pill" class="mx-auto flex w-full max-w-[140px] justify-center" @click="dialogOpen = false">
                        Cerrar
                    </Button>
                </div>
                <div class="w-full md:w-auto">
                    <Button
                        variant="default"
                        type="button"
                        class="mx-auto flex w-full max-w-[140px] items-center justify-center rounded-full px-5 py-2 text-sm font-semibold shadow-sm"
                        @click="print"
                    >
                        Imprimir
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
@media print {
    :root,
    html,
    body {
        width: 210mm !important;
        height: 297mm !important;
        margin: 0 !important;
        padding: 0 !important;
        background: #fff !important;
    }
    .print\:hidden {
        display: none !important;
    }
    .print\:max-w-none {
        max-width: none !important;
    }
    .print\:w-\[210mm\] {
        width: 210mm !important;
    }
    .print\:h-\[297mm\] {
        height: 297mm !important;
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
}
</style>
