<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import Input from '@/components/ui/input/Input.vue';
import SelectField from '@/components/SelectField.vue';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import Label from '@/components/ui/label/Label.vue';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Plus } from 'lucide-vue-next';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import AlertBox from '@/components/AlertBox.vue';
import axios from 'axios';
import DeleteButton from '@/components/common/DeleteButton.vue';
import type { Article } from '@/types';

const props = defineProps<{
  isOpen: boolean;
  dialogType: 'create' | 'edit';
  adjustment: any | null;
}>();

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'saved'): void;
}>();

type Line = {
  id: number;
  articleId: number | null;
  price: number;
  tax: number;
  quantity: number;
  description: string;
};

const documentNumber = ref<string>('Automatico');
const adjustmentType = ref<'entrada' | 'salida' | ''>('');
const date = ref<string>('');
const observation = ref<string>('');
const productionOrder = ref<string>('');
const taxes = ref<any[]>([]);
const articles = ref<Article[]>([]);
const alertMessage = ref<string>('');
const isSubmitting = ref(false);

const lines = ref<Line[]>([
  { id: 1, articleId: null, price: 0, tax: 0, quantity: 1, description: '' },
]);

const adjustmentTypeOptions = [
  { id: 'entrada', name: 'Entrada de inventario' },
  { id: 'salida', name: 'Salida de inventario' },
];

async function fetchTaxes() {
  try {
    const { data } = await axios.get('/impuestos/data', { params: { per_page: 100, status: true } });
    taxes.value = (data.data ?? []).filter((t: any) => t.status);
  } catch (e) {
    console.error('Error cargando impuestos', e);
  }
}

async function fetchArticles() {
  try {
    const response = await axios.get('/articulos/data', {
      params: { per_page: 1000, status: 1 },
    });
    articles.value = response.data?.data ?? [];
  } catch (e) {
    console.error('Error cargando articulos', e);
    articles.value = [];
  }
}

onMounted(() => {
  fetchTaxes();
  fetchArticles();
});

function resetForm() {
  documentNumber.value = 'Automatico';
  adjustmentType.value = '';
  date.value = '';
  observation.value = '';
  productionOrder.value = '';
  lines.value = [{ id: 1, articleId: null, price: 0, tax: 0, quantity: 1, description: '' }];
  alertMessage.value = '';
}

function fillFromAdjustment(data: any) {
  documentNumber.value = data.document_number || 'Automatico';
  adjustmentType.value = data.type || '';
  date.value = data.date ? String(data.date).slice(0, 10) : '';
  observation.value = data.observation || '';
  productionOrder.value = data.production_order || '';
  lines.value = Array.isArray(data.lines)
    ? data.lines.map((line: any, idx: number) => ({
        id: idx + 1,
        articleId: Number(line.article_id),
        price: Number(line.price) || 0,
        tax: Number(line.tax) || 0,
        quantity: Number(line.quantity) || 0,
        description: line.description || '',
      }))
    : [{ id: 1, articleId: null, price: 0, tax: 0, quantity: 1, description: '' }];
}

watch(
  () => props.isOpen,
  async (open) => {
    if (!open) return;
    alertMessage.value = '';
    if (props.dialogType === 'edit' && props.adjustment?.id) {
      try {
        const { data } = await axios.get(`/ajustes-inventario/${props.adjustment.id}`);
        fillFromAdjustment(data);
      } catch {
        alertMessage.value = 'No se pudo cargar el ajuste seleccionado.';
        resetForm();
      }
    } else {
      resetForm();
    }
  }
);

function addLine() {
  const nextId = lines.value.length ? Math.max(...lines.value.map(l => l.id)) + 1 : 1;
  lines.value.push({
    id: nextId,
    articleId: null,
    price: 0,
    tax: 0,
    quantity: 1,
    description: '',
  });
}

function removeLine(id: number) {
  if (lines.value.length === 1) return;
  lines.value = lines.value.filter(l => l.id !== id);
}

const lineTotal = (line: Line) => {
  const price = Number(line.price ?? 0);
  const qty = Number(line.quantity ?? 0);
  const base = price * qty;
  const tax = Number(line.tax ?? 0);
  const taxAmount = (base * tax) / 100;
  return base + taxAmount;
};

const subtotal = computed(() =>
  lines.value.reduce((acc, l) => acc + Number(l.price ?? 0) * Number(l.quantity ?? 0), 0),
);

const ivaTotal = computed(() =>
  lines.value.reduce((acc, l) => {
    const base = Number(l.price ?? 0) * Number(l.quantity ?? 0);
    const tax = Number(l.tax ?? 0);
    return acc + (base * tax) / 100;
  }, 0),
);

const totalAdjustment = computed(() => subtotal.value + ivaTotal.value);

async function handleSubmit() {
  alertMessage.value = '';
  const requiredErrors: string[] = [];

  if (!adjustmentType.value) {
    requiredErrors.push('Tipo de ajuste');
  }
  if (!date.value) {
    requiredErrors.push('Fecha');
  }

  if (!lines.value.length) {
    requiredErrors.push('Al menos una linea de detalle');
  } else {
    lines.value.forEach((line) => {
      if (!line.articleId) {
        requiredErrors.push('Articulo');
      }
      if (!line.quantity || line.quantity <= 0) {
        requiredErrors.push('Cantidad');
      }
      if (line.price === null || line.price === undefined || line.price < 0) {
        requiredErrors.push('Precio');
      }
    });
  }

  if (requiredErrors.length > 0) {
    const unique = Array.from(new Set(requiredErrors));
    alertMessage.value = 'Por favor completa todos los campos obligatorios marcados con (*). ' + `Campos incompletos: ${unique.join(', ')}.`;
    return;
  }

  const payload = {
    document_number: documentNumber.value === 'Automatico' ? null : documentNumber.value,
    date: date.value,
    type: adjustmentType.value as 'entrada' | 'salida',
    observation: observation.value || null,
    production_order: productionOrder.value || null,
    subtotal: subtotal.value,
    iva: ivaTotal.value,
    total: totalAdjustment.value,
    lines: lines.value.map(line => ({
      article_id: line.articleId,
      warehouse_id: null,
      price: line.price ?? 0,
      tax: line.tax ?? 0,
      quantity: line.quantity ?? 0,
      description: line.description || null,
    })),
  };

  try {
    isSubmitting.value = true;
    if (props.dialogType === 'edit' && props.adjustment?.id) {
      await axios.put(`/ajustes-inventario/${props.adjustment.id}`, payload);
    } else {
      await axios.post('/ajustes-inventario', payload);
    }
    emit('saved');
    emit('close');
  } catch (error: any) {
    const responseMessage = error?.response?.data?.message || 'No se pudo guardar el ajuste.';
    alertMessage.value = responseMessage;
  } finally {
    isSubmitting.value = false;
  }
}
</script>

<template>
  <Dialog :open="isOpen" @update:open="emit('close')">
    <DialogContent class="max-w-5xl max-h-[90vh] overflow-y-auto">
      <DialogHeader>
        <DialogTitle>{{ props.dialogType === 'edit' ? 'Editar ajuste de inventario' : 'Nuevo ajuste de inventario' }}</DialogTitle>
        <DialogDescription>
          Registra un movimiento de entrada o salida de inventario.
        </DialogDescription>
      </DialogHeader>

      <transition name="fade">
        <div
          v-if="alertMessage"
          class="pointer-events-none absolute left-1/2 top-6 z-30 w-full max-w-xl -translate-x-1/2"
        >
          <AlertBox
            type="error"
            title="Error"
            :message="alertMessage"
            class="pointer-events-auto"
            @close="alertMessage = ''"
          />
        </div>
      </transition>

      <form class="space-y-4" novalidate @submit.prevent="handleSubmit">
        <div class="rounded-xl border border-sidebar-border/70 bg-white p-4 dark:border-sidebar-border">
          <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div>
              <Label># Documento</Label>
              <Input v-model="documentNumber" class="mt-1" readonly />
            </div>
            <div class="relative flex h-full items-center justify-center rounded p-4">
              <img
                src="/images/olamtex.png"
                alt="Logo empresa"
                class="max-h-12 object-contain"
              />
            </div>
            <div>
              <div class=" font-semibold">Datos empresa</div>
              <p class="text-xs">Razon Social: <span class="font-normal">OLAMTEX S.A.S.</span></p>
              <p class="text-xs">NIT: <span class="font-normal">900123456-7</span></p>
              <p class="text-xs">Correo: <span class="font-normal">info@olamtex.com</span></p>
            </div>
          </div>
          <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3 items-end">
            <div>
              <SelectField
                v-model="adjustmentType"
                label="Tipo de ajuste"
                :options="adjustmentTypeOptions"
                placeholder="Selecciona tipo de ajuste"
                required
              />
            </div>

            <div>
              <Label>
                Fecha <span class="text-destructive">*</span>
              </Label>
              <Input
                v-model="date"
                type="date"
                class="w-full"
                required
              />
            </div>
            <div>
              <Label>Observacion</Label>
              <Input
                v-model="observation"
                class="mt-1"
                placeholder="Ingrese una observacion"
              />
            </div>
          </div>
          <div class="mt-4">
            <Label>Orden de produccion</Label>
            <Input
              v-model="productionOrder"
              class="mt-1"
              placeholder="Ingrese la orden de produccion asociada"
            />
          </div>

          <div class="mt-6">
            <div class="mb-2 flex items-center justify-between">
              <span class="text-sm font-semibold">Detalle de lineas</span>
              <Button type="button" variant="outline" size="sm" class="flex items-center gap-1" @click="addLine">
                <Plus class="h-4 w-4" />
                Crear linea
              </Button>
            </div>

            <div class="overflow-x-auto rounded-lg border">
              <Table class="min-w-full text-center table-fixed">
                <TableHeader>
                  <TableRow>
                    <TableHead class="w-[18%]">
                      Articulo <span class="text-destructive">*</span>
                    </TableHead>
                    <TableHead class="w-[18%]">
                      Precio <span class="text-destructive">*</span>
                    </TableHead>
                    <TableHead class="w-[18%]">
                      Impuesto
                    </TableHead>
                    <TableHead class="w-[18%]">
                      Cantidad <span class="text-destructive">*</span>
                    </TableHead>
                    <TableHead class="w-[18%]">
                      Descripcion
                    </TableHead>
                    <TableHead class="w-[8%]">
                      Total
                    </TableHead>
                    <TableHead class="w-[4%]"></TableHead>
                  </TableRow>
                </TableHeader>

                <TableBody>
                  <TableRow v-for="line in lines" :key="line.id">
                    <TableCell>
                      <Select
                        :modelValue="line.articleId ? String(line.articleId) : ''"
                        @update:modelValue="val => { line.articleId = val ? Number(val) : null; }"
                      >
                        <SelectTrigger class="h-8 w-full">
                          <SelectValue placeholder="Articulo" />
                        </SelectTrigger>

                        <SelectContent>
                          <SelectItem
                            v-for="article in articles"
                            :key="article.id"
                            :value="String(article.id)"
                          >
                            {{ article.name }}
                            <span v-if="article.reference"> ({{ article.reference }})</span>
                          </SelectItem>
                        </SelectContent>
                      </Select>
                    </TableCell>

                    <TableCell>
                      <Input
                        v-model.number="line.price"
                        type="number"
                        min="0"
                        step="0.01"
                        placeholder="0.00"
                        class="h-8 w-full"
                      />
                    </TableCell>

                    <TableCell>
                      <Select
                        :modelValue="String(line.tax)"
                        @update:modelValue="val => (line.tax = Number(val))"
                      >
                        <SelectTrigger class="h-8 w-full">
                          <SelectValue placeholder="%" />
                        </SelectTrigger>

                        <SelectContent>
                          <SelectItem
                            v-for="tax in taxes"
                            :key="tax.id"
                            :value="String(tax.percentage)"
                          >
                            {{ tax.name }} ({{ Math.trunc(tax.percentage) }}%)
                          </SelectItem>
                        </SelectContent>
                      </Select>
                    </TableCell>

                    <TableCell>
                      <Input
                        v-model.number="line.quantity"
                        type="number"
                        min="1"
                        step="1"
                        placeholder="0"
                        class="h-8 w-full"
                      />
                    </TableCell>

                    <TableCell>
                      <Input
                        v-model="line.description"
                        placeholder="Descripcion"
                        class="h-8 w-full"
                      />
                    </TableCell>

                    <TableCell>
                      {{ lineTotal(line).toFixed(2) }}
                    </TableCell>

                    <TableCell class="text-center">
                      <DeleteButton
                        class="mx-auto"
                        title="Eliminar"
                        @click="removeLine(line.id)"
                      />
                    </TableCell>
                  </TableRow>

                  <TableRow v-if="lines.length === 0">
                    <TableCell colspan="7" class="text-center text-gray-400">
                      No hay lineas agregadas.
                    </TableCell>
                  </TableRow>
                </TableBody>
              </Table>
            </div>
          </div>

          <div class="mt-4 flex flex-col items-end gap-1 text-sm">
            <div>Subtotal: <span class="font-semibold">{{ subtotal.toFixed(2) }}</span></div>
            <div>IVA total: <span class="font-semibold">{{ ivaTotal.toFixed(2) }}</span></div>
            <div>Total ajuste: <span class="font-semibold">{{ totalAdjustment.toFixed(2) }}</span></div>
          </div>
        </div>

        <div class="flex justify-end gap-2">
          <Button type="button" variant="outline" @click="emit('close')">
            Cancelar
          </Button>
          <Button type="submit" class="px-6" :disabled="isSubmitting">
            {{ isSubmitting ? 'Guardando...' : props.dialogType === 'edit' ? 'Actualizar' : 'Guardar' }}
          </Button>
        </div>
      </form>
    </DialogContent>
  </Dialog>
</template>
