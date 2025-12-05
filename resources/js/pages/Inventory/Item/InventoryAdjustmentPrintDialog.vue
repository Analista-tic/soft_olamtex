<script setup lang="ts">
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { computed, ref, watch } from 'vue';
import { useCompanyInfo } from '@/composables/useCompanyInfo';

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
  }
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
  }, 0)
);

const iva = computed(() =>
  (props.adjustment?.lines || []).reduce((acc, line) => {
    const price = Number(line.price ?? 0);
    const qty = Number(line.quantity ?? 0);
    const tax = Number(line.tax ?? 0);
    return acc + (price * qty * tax) / 100;
  }, 0)
);

const total = computed(() => subtotal.value + iva.value);

function formatCurrency(value: number | string | null | undefined) {
  const num = Number(value ?? 0);
  return num.toLocaleString('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 });
}

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
        <DialogTitle>Ajuste de Inventario</DialogTitle>
      </DialogHeader>
      <div class="p-4 print:p-12 print:bg-white print:h-[297mm] print:w-[210mm] flex flex-col justify-between min-h-[277mm]">
        <div>
          <div class="grid grid-cols-3 items-start gap-4 mb-4">
            <div>
              <div class="font-bold"># Documento</div>
              <div>{{ props.adjustment?.document_number || 'Sin numero' }}</div>
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
                Razon Social:
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
              <div class="font-bold">Fecha</div>
              <div>{{ props.adjustment?.date }}</div>
            </div>
            <div>
              <div class="font-bold">Tipo</div>
              <div class="capitalize">{{ props.adjustment?.type }}</div>
            </div>
            <div>
              <div class="font-bold">Orden de produccion</div>
              <div>{{ props.adjustment?.production_order || '-' }}</div>
            </div>
          </div>

          <div class="mb-4">
            <div class="font-bold">Observacion</div>
            <div>{{ props.adjustment?.observation || '-' }}</div>
          </div>

          <div class="mb-4">
            <div class="font-bold mb-2">Lineas</div>
            <table class="w-full text-sm border-collapse print:border-none">
              <thead>
                <tr class="bg-gray-100 print:bg-transparent">
                  <th class="border px-2 py-1 print:border print:border-gray-300">#</th>
                  <th class="border px-2 py-1 print:border print:border-gray-300">Articulo</th>
                  <th class="border px-2 py-1 print:border print:border-gray-300">Descripcion</th>
                  <th class="border px-2 py-1 print:border print:border-gray-300">Cantidad</th>
                  <th class="border px-2 py-1 print:border print:border-gray-300">Precio unitario</th>
                  <th class="border px-2 py-1 print:border print:border-gray-300">Impuesto</th>
                  <th class="border px-2 py-1 print:border print:border-gray-300">Total</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(line, idx) in props.adjustment?.lines || []" :key="idx">
                  <td class="border px-2 py-1 print:border print:border-gray-300 text-center">{{ idx + 1 }}</td>
                  <td class="border px-2 py-1 print:border print:border-gray-300">
                    {{ line.article?.name || '' }}
                    <span v-if="line.article?.reference"> ({{ line.article.reference }})</span>
                  </td>
                  <td class="border px-2 py-1 print:border print:border-gray-300">{{ line.description || '' }}</td>
                  <td class="border px-2 py-1 print:border print:border-gray-300 text-center">{{ line.quantity }}</td>
                  <td class="border ps-0 py-1 pe-10 print:border print:border-gray-300 text-right">
                    {{ formatCurrency(line.price) }}
                  </td>
                  <td class="border px-2 py-1 print:border print:border-gray-300 text-center">
                    {{ Math.round(Number(line.tax ?? 0)) }} %
                  </td>
                  <td class="border ps-0 py-1 pe-10 print:border print:border-gray-300 text-right">
                    {{ formatCurrency(line.line_total ?? (Number(line.price ?? 0) * Number(line.quantity ?? 0))) }}
                  </td>
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
              <span class="font-semibold">Total ajuste:</span>
            </div>
            <div class="flex min-w-[140px] flex-col items-end text-right pe-10">
              <span>{{ formatCurrency(props.adjustment?.subtotal ?? subtotal) }}</span>
              <span>{{ formatCurrency(props.adjustment?.iva ?? iva) }}</span>
              <span class="font-bold">{{ formatCurrency(props.adjustment?.total ?? total) }}</span>
            </div>
          </div>
        </div>

        <div class="flex justify-between items-end mt-12 print:mt-24">
          <div class="flex flex-col items-center">
            <span class="border-t border-gray-400 w-48 block"></span>
            <span class="text-xs mt-1">Firma Autorizada</span>
          </div>
          <div class="flex flex-col items-center">
            <span class="border-t border-gray-400 w-48 block"></span>
            <span class="text-xs mt-1">Recibido por</span>
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
