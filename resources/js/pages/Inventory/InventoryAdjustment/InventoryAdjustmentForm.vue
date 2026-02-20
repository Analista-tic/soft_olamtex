<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import DatePickerField from '@/components/DatePickerField.vue';
import Input from '@/components/ui/input/Input.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import Label from '@/components/ui/label/Label.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/components/ui/dialog';
import AlertBox from '@/components/AlertBox.vue';
import axios from 'axios';
import DeleteButton from '@/components/common/DeleteButton.vue';
import InventoryDetailTableLayout from '@/components/InventoryDetailTableLayout.vue';
import type { Item, Warehouse, Tax } from '@/domain/Inventory';
import { formatRequiredFieldsMessage } from '@/lib/alert-format';
import { DEFAULT_TAX_LABEL, formatTaxOptionLabel, isDefaultTax } from '@/lib/taxes';
import { useCompanyInfo } from '@/composables/useCompanyInfo';

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
  itemId: number | null;
  movementReason: string;
  warehouseId: number | null;
  price: number;
  taxId: number | null;
  tax: number;
  quantity: number;
  description: string;
};

const defaultMovementReason: 'entrada' | 'salida' = 'entrada';
const { company, logoUrl } = useCompanyInfo();

const adjustmentTypeOptions = [
  { id: 'entrada', name: 'Entrada', direction: 'entrada' },
  { id: 'salida', name: 'Salida', direction: 'salida' },
];

const currencyFormatter = new Intl.NumberFormat('es-CO', {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
});

const formatCurrency = (value: number | string | null | undefined) =>
  currencyFormatter.format(Number(value ?? 0));

const resolveMovementReason = (value?: string): 'entrada' | 'salida' =>
  value === 'salida' ? 'salida' : defaultMovementReason;

const documentNumber = ref<string>('Automatico');
const date = ref<string | null>(null);
const observation = ref<string>('');
const productionOrder = ref<string>('');
const taxes = ref<Tax[]>([]);
const items = ref<Item[]>([]);
const warehouses = ref<Warehouse[]>([]);
const selectedWarehouseId = ref<number | null>(null);
const lastSelectedWarehouseId = ref<number | null>(null);
const alertMessage = ref<string>('');
const alertDetails = ref<string[]>([]);
const alertType = ref<'success' | 'error' | 'info' | 'warning'>('error');
const isSubmitting = ref(false);

const lines = ref<Line[]>([
  {
    id: 1,
    itemId: null,
    movementReason: defaultMovementReason,
    warehouseId: null,
    price: 0,
    taxId: null,
    tax: 0,
    quantity: 1,
    description: '',
  },
]);


const itemSelectOptions = computed(() =>
  items.value.map(item => ({
    id: item.id,
    name: item.reference ? `${item.name} (${item.reference})` : item.name,
  })),
);

const nonDefaultTaxes = computed(() =>
  taxes.value.filter((tax): tax is Tax => !isDefaultTax(tax)),
);

const taxSelectOptions = computed(() => [
  { id: 0, name: DEFAULT_TAX_LABEL },
  ...nonDefaultTaxes.value.map((tax) => ({
    id: tax.id,
    name: formatTaxOptionLabel(tax),
  })),
]);

const warehouseSelectOptions = computed(() =>
  warehouses.value
    .filter((w): w is Warehouse & { id: number } => typeof w.id === 'number')
    .map(w => ({
      id: w.id,
      name: w.name,
    })),
);

async function fetchTaxes() {
  try {
    const { data } = await axios.get('/impuestos/data', { params: { per_page: 100, status: true } });
    taxes.value = (data.data ?? []).filter((t: any) => t.status);
  } catch (e) {
    console.error('Error cargando impuestos', e);
  }
}

async function fetchItems() {
  try {
    const response = await axios.get('/item/data', {
      params: { per_page: 1000, status: 1, item_type: 'product' },
    });
    items.value = response.data?.data ?? [];
  } catch (e) {
    console.error('Error cargando items', e);
    items.value = [];
  }
}

async function fetchWarehouses() {
  try {
    const { data } = await axios.get('/bodegas/data', {
      params: { per_page: 200, page: 1 },
    });
    warehouses.value = data?.data ?? [];
    if (!selectedWarehouseId.value && warehouses.value.length) {
      selectedWarehouseId.value = warehouses.value[0].id ?? null;
    }
  } catch (e) {
    console.error('Error cargando bodegas', e);
    warehouses.value = [];
  }
}

onMounted(() => {
  fetchTaxes();
  fetchItems();
  fetchWarehouses();
});

watch(selectedWarehouseId, (val) => {
  const previous = lastSelectedWarehouseId.value;
  if (previous === val) {
    return;
  }

    lines.value = lines.value.map(line => ({
      ...line,
      warehouseId: line.warehouseId === previous ? val ?? null : line.warehouseId,
    }));

  lastSelectedWarehouseId.value = val ?? null;
});

watch(
  lines,
  (current) => {
    current.forEach(line => {
      if (!line.movementReason) {
        line.movementReason = defaultMovementReason;
      }
    });
  },
  { deep: true },
);

function resetForm() {
  documentNumber.value = 'Automatico';
  date.value = null;
  observation.value = '';
  productionOrder.value = '';
  selectedWarehouseId.value = warehouses.value[0]?.id ?? null;
  lines.value = [{
    id: 1,
    itemId: null,
    movementReason: defaultMovementReason,
    warehouseId: selectedWarehouseId.value,
    price: 0,
    taxId: null,
    tax: 0,
    quantity: 1,
    description: '',
  }];
  alertMessage.value = '';
  alertDetails.value = [];
  alertType.value = 'error';
}

async function loadDocumentNumber() {
  documentNumber.value = 'Cargando...';
  try {
    const { data } = await axios.get('/ajustes-inventario/next-number');
    documentNumber.value = data?.document_number || 'Automatico';
  } catch {
    documentNumber.value = 'Automatico';
  }
}

function fillFromAdjustment(data: any) {
  documentNumber.value = data.document_number || 'Automatico';
  date.value = data.date ? String(data.date).slice(0, 10) : null;
  observation.value = data.observation || '';
  productionOrder.value = data.production_order || '';
  const firstLineWarehouse = Array.isArray(data.lines) && data.lines.length ? data.lines[0].warehouse_id ?? null : null;
  selectedWarehouseId.value = firstLineWarehouse ?? selectedWarehouseId.value ?? warehouses.value[0]?.id ?? null;
  const resolvedMovementReason = resolveMovementReason(data.movement_reason || data.type);
  lines.value = Array.isArray(data.lines)
    ? data.lines.map((line: any, idx: number) => ({
        id: idx + 1,
        itemId: Number(line.item_id),
        movementReason: resolvedMovementReason,
        warehouseId: line.warehouse_id ?? null,
        price: Number(line.price) || 0,
        taxId: line.tax_id ?? line.taxId ?? null,
        tax: Number(line.tax) || 0,
        quantity: Number(line.quantity) || 0,
        description: line.description || '',
      }))
    : [{
      id: 1,
      itemId: null,
      movementReason: resolvedMovementReason,
      warehouseId: selectedWarehouseId.value,
      price: 0,
      taxId: null,
      tax: 0,
      quantity: 1,
      description: '',
    }];
}

watch(
  () => props.isOpen,
  async (open) => {
    if (!open) return;
    fetchTaxes();
    fetchWarehouses();
    alertMessage.value = '';
    alertDetails.value = [];
    alertType.value = 'error';
    if (props.dialogType === 'edit' && props.adjustment?.id) {
      try {
        const { data } = await axios.get(`/ajustes-inventario/${props.adjustment.id}`);
        fillFromAdjustment(data);
      } catch {
        alertType.value = 'error';
        alertMessage.value = 'No se pudo cargar el ajuste seleccionado.';
        resetForm();
      }
    } else {
      resetForm();
      await loadDocumentNumber();
    }
  }
);

function addLine() {
  const nextId = lines.value.length ? Math.max(...lines.value.map(l => l.id)) + 1 : 1;
  lines.value.push({
    id: nextId,
    itemId: null,
    movementReason: lines.value[0]?.movementReason || defaultMovementReason,
    warehouseId: selectedWarehouseId.value,
    price: 0,
    taxId: null,
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

function normalizeDecimalInput(event: Event) {
  const target = event.target as HTMLInputElement | null;
  if (!target) return;
  const normalized = target.value.replace(/,/g, '.');
  if (normalized !== target.value) {
    target.value = normalized;
  }
}

type InventoryAdjustmentSavedEventDetail = {
  itemIds: number[];
};

function notifyInventoryAdjustmentSaved(itemIds: number[]) {
  if (typeof window === 'undefined' || !itemIds?.length) return;
  const detail: InventoryAdjustmentSavedEventDetail = {
    itemIds,
  };
  window.dispatchEvent(new CustomEvent<InventoryAdjustmentSavedEventDetail>('inventory-adjustment-saved', { detail }));
}

async function handleSubmit() {
  alertMessage.value = '';
  alertDetails.value = [];
  alertType.value = 'error';
  const requiredErrors: string[] = [];

  if (!date.value) {
    requiredErrors.push('Fecha');
  }
  if (!selectedWarehouseId.value) {
    requiredErrors.push('Bodega');
  }
  if (!lines.value.length) {
    requiredErrors.push('Al menos una linea de detalle');
  } else {
    lines.value.forEach((line) => {
      if (!line.itemId) {
        requiredErrors.push('Item');
      }
      if (!line.warehouseId && !selectedWarehouseId.value) {
        requiredErrors.push('Bodega');
      }
      if (!line.quantity || line.quantity <= 0) {
        requiredErrors.push('Cantidad');
      }
      if (line.price === null || line.price === undefined || line.price < 0) {
        requiredErrors.push('Precio SIN Impuesto');
      }
      const trimmedDescription = (line.description ?? '').trim();
      if (!trimmedDescription) {
        requiredErrors.push('Descripción');
      } else if (trimmedDescription !== line.description) {
        line.description = trimmedDescription;
      }
      if (!line.movementReason) {
        line.movementReason = defaultMovementReason;
      }
    });
  }

  if (requiredErrors.length > 0) {
    const unique = Array.from(new Set(requiredErrors));
    alertType.value = 'warning';
    alertMessage.value = formatRequiredFieldsMessage(
      'Por favor completa todos los campos obligatorios',
      unique,
    );
    alertDetails.value = [];
    const dialogEl = document.querySelector('[role="dialog"]');
    dialogEl?.scrollTo({ top: 0, behavior: 'smooth' });
    return;
  }

  const primaryMovementReason = lines.value[0]?.movementReason || defaultMovementReason;
  const payload = {
    document_number: documentNumber.value === 'Automatico' ? null : documentNumber.value,
    date: date.value,
    type: primaryMovementReason,
    movement_reason: primaryMovementReason,
    observation: observation.value || null,
    production_order: productionOrder.value || null,
    subtotal: subtotal.value,
    iva: ivaTotal.value,
    total: totalAdjustment.value,
    lines: lines.value.map(line => ({
      item_id: line.itemId,
      warehouse_id: line.warehouseId || selectedWarehouseId.value,
      movement_reason: line.movementReason || primaryMovementReason,
      price: line.price ?? 0,
      tax: line.tax ?? 0,
      tax_id: line.taxId ?? null,
      quantity: line.quantity ?? 0,
      description: line.description || null,
    })),
  };

  let response: any;
  try {
    isSubmitting.value = true;
    if (props.dialogType === 'edit' && props.adjustment?.id) {
      response = await axios.put(`/ajustes-inventario/${props.adjustment.id}`, payload);
      alertType.value = 'success';
      alertMessage.value = 'Ajuste actualizado correctamente.';
    } else {
      response = await axios.post('/ajustes-inventario', payload);
      alertType.value = 'success';
      alertMessage.value = 'Ajuste creado correctamente.';
    }
    const savedLines = response?.data?.data?.lines;
    if (Array.isArray(savedLines)) {
      const itemIds = Array.from(new Set(
        savedLines
          .map((line: any) => Number(line.item_id))
          .filter((id: number) => Number.isFinite(id)),
      ));
      notifyInventoryAdjustmentSaved(itemIds);
    }
    alertDetails.value = [];
    emit('saved');
    emit('close');
  } catch (error: any) {
    const responseMessage = error?.response?.data?.message || 'No se pudo guardar el ajuste.';
    const responseErrors = error?.response?.data?.errors;
    // Si hay validaciones del backend, mostramos solo un bloque de advertencia con la lista
    alertType.value = 'warning';
    const mergedErrors = responseErrors
      ? Array.from(new Set(Object.values(responseErrors).flat().map((msg: any) => String(msg))))
      : [];
    alertMessage.value = mergedErrors.length
      ? `${responseMessage}: ${mergedErrors.join(', ')}`
      : responseMessage;
    alertDetails.value = [];
    const dialogEl = document.querySelector('[role="dialog"]');
    dialogEl?.scrollTo({ top: 0, behavior: 'smooth' });
  } finally {
    isSubmitting.value = false;
  }
}
</script>

<template>
  <Dialog :open="isOpen" @update:open="emit('close')">
    <DialogContent class="max-w-[200vh] max-h-[90vh] flex flex-col p-0">
      <DialogHeader class="pb-5 flex-shrink-0 px-4 pt-4">
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
              :type="alertType"
              :title="alertType === 'warning' ? 'Advertencia' : alertType === 'success' ? 'Éxito' : 'Error'"
              :message="alertMessage"
              class="pointer-events-auto"
              @close="alertMessage = ''"
            />
            <div
              v-if="alertDetails.length"
              class="pointer-events-auto mt-2 p-1 text-sm text-amber-900"
            >
              <ul class="list-disc ps-5">
                <li v-for="(item, idx) in alertDetails" :key="idx">{{ item }}</li>
              </ul>
            </div>
          </div>
        </transition>

        <div class="overflow-y-auto flex-1 px-4 scroll-smooth" style="scrollbar-width: thin;">
        <form class="space-y-4" novalidate @submit.prevent="handleSubmit">
          <div >
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
              <div>
                <Label># Documento</Label>
                <Input
                  v-model="documentNumber"
                  class="mt-1 pointer-events-none select-none"
                  readonly
                  tabindex="-1"
                />
              </div>
              <div class="relative flex h-full items-center justify-center rounded p-4">
                <img
                  :src="logoUrl"
                  alt="Logotipo de la empresa"
                  class="max-h-24 max-w-[160px] w-auto object-contain"
                />
              </div>
              <div>
                <div class="font-semibold">Datos empresa</div>
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
            <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3 items-end">
              <div>
                <SelectField
                  v-model="selectedWarehouseId"
                  label="Bodega"
                  tooltipText="Selecciona la bodega que refleja dónde se realiza el ajuste para mantener la trazabilidad del stock."
                  :options="warehouseSelectOptions"
                  placeholder="Selecciona bodega"
                  :searchable="false"
                  required
                />
              </div>

              <div>
                <DatePickerField
                  v-model="date"
                  label="Fecha"
                  helpText="Registra la fecha real del ajuste para que los reportes del inventario y las conciliaciones coincidan."
                  required
                  class="w-full"
                />
              </div>
              <div>
                <Label class="flex items-center gap-1.5">
                  <span>Observación</span>
                  <HelpTooltip
                    text="Describe el motivo del ajuste (ej. pérdida, conteo físico, rotura) para facilitar auditorías."
                  />
                </Label>
                <Input
                  v-model="observation"
                  class="mt-1"
                  placeholder="Ingrese una observación"
                />
              </div>
            </div>
            <div class="mt-4 pb-4">
              <Label>Orden de producción</Label>
              <Input
                v-model="productionOrder"
                class="mt-1"
                placeholder="Ingrese la orden de producción asociada"
              />
            </div>

            <InventoryDetailTableLayout @create-line="addLine">
              <Table
                class="min-w-full text-center border-separate border-spacing-x-2 border-spacing-y-1 table-fixed"
                style="overflow: visible; max-height: none;"
              >
                  <TableHeader>
                  <TableRow class="border-b border-border/60">
                    <TableHead class="min-w-[150px] text-left">Item <span class="text-destructive">*</span></TableHead>
                    <TableHead class="min-w-[160px] text-left">Tipo de ajuste <span class="text-destructive">*</span></TableHead>
                    <TableHead class="min-w-[160px] text-left">
                      Descripcion <span class="text-destructive">*</span>
                    </TableHead>
                    <TableHead class="min-w-[120px] text-left">Precio SIN Impuesto <span class="text-destructive">*</span></TableHead>
                    <TableHead class="min-w-[140px] text-left">Impuesto</TableHead>
                    <TableHead class="min-w-[110px] text-left">Cantidad <span class="text-destructive">*</span></TableHead>
                    <TableHead class="min-w-[90px] text-left">Total</TableHead>
                    <TableHead class="w-[60px]"></TableHead>
                  </TableRow>
                </TableHeader>

                <TableBody>
                  <TableRow v-for="(line, idx) in lines" :key="line.id">
                    <TableCell class="p-1 text-left">
                      <SelectField
                        v-model="line.itemId"
                        :options="itemSelectOptions"
                        label=""
                        :searchable=true
                        placeholder="Selecciona"
                        class="w-full"
                      />
                    </TableCell>

                    <TableCell class="p-1 text-left">
                      <SelectField
                        v-model="line.movementReason"
                        :options="adjustmentTypeOptions"
                        label=""
                        placeholder="Selecciona"
                        class="w-full"
                      />
                    </TableCell>

                    <TableCell class="p-1">
                      <Input
                        v-model="line.description"
                        placeholder="Descripcion"
                        required
                        class="h-10 w-full"
                      />
                    </TableCell>

                    <TableCell class="p-1">
                      <InputField
                        label=""
                        v-model.number="line.price"
                        type="currency"
                        :min="0"
                        :step="0.01"
                        :decimals="2"
                        placeholder="0.00"
                        class="h-10 w-full"
                        @input="normalizeDecimalInput"
                      />
                    </TableCell>

                    <TableCell class="p-1">
                      <SelectField
                        v-model="line.taxId"
                        :options="taxSelectOptions"
                        label=""
                        placeholder="Selecciona"
                        class="w-full"
                        @update:modelValue="val => {
                          const selected = taxes.find(t => String(t.id) === String(val));
                          line.taxId = selected ? selected.id : null;
                          line.tax = selected ? Number(selected.percentage) : 0;
                        }"
                      />
                    </TableCell>

                    <TableCell class="p-1">
                      <Input
                        v-model.number="line.quantity"
                        type="number"
                        min="1"
                        step="1"
                        placeholder="0"
                        class="h-10 w-full"
                      />
                    </TableCell>

                    <TableCell class="p-1">
                      {{ formatCurrency(lineTotal(line)) }}
                    </TableCell>

                    <TableCell class="p-1 text-center">
                      <DeleteButton
                        v-if="lines.length > 1 && idx > 0"
                        class="mx-auto"
                        title="Eliminar"
                        @click="removeLine(line.id)"
                      />
                    </TableCell>
                  </TableRow>

                  <TableRow v-if="lines.length === 0">
                    <TableCell colspan="8" class="text-center text-gray-400">
                      No hay lineas agregadas.
                    </TableCell>
                  </TableRow>
                </TableBody>
              </Table>
            </InventoryDetailTableLayout>

            <div class="mt-4 flex flex-col items-end gap-1 text-sm">
              <div>Subtotal: <span class="font-semibold">{{ formatCurrency(subtotal) }}</span></div>
              <div>IVA total: <span class="font-semibold">{{ formatCurrency(ivaTotal) }}</span></div>
              <div>Total ajuste: <span class="font-semibold">{{ formatCurrency(totalAdjustment) }}</span></div>
            </div>
          </div>

          <DialogFooter class="flex-shrink-0 border-t pt-4 mt-4 flex flex-col gap-2 md:flex-row md:justify-end px-4 pb-4">
            <div class="w-full md:w-auto">
              <button
                type="button"
                class="mx-auto flex w-full max-w-[140px] items-center justify-center gap-2 rounded-full border border-primary/40 bg-white px-5 py-2 text-sm font-semibold text-primary transition hover:bg-primary/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary"
                @click="emit('close')"
              >
                Cancelar
              </button>
            </div>
            <div class="w-full md:w-auto">
              <Button
                type="submit"
                variant="default"
                class="mx-auto flex w-full max-w-[140px] items-center justify-center rounded-full px-5 py-2 text-sm font-semibold shadow-sm"
                :disabled="isSubmitting"
              >
                {{ isSubmitting ? 'Guardando...' : props.dialogType === 'edit' ? 'Actualizar' : 'Guardar' }}
              </Button>
            </div>
          </DialogFooter>
        </form>
        </div>
    </DialogContent>
  </Dialog>
</template>


