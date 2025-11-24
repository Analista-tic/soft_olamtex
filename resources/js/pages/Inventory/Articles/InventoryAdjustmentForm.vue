<script setup lang="ts">
import { ref, computed, onMounted  } from 'vue';
import Input from '@/components/ui/input/Input.vue';
import SelectField from '@/components/SelectField.vue';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import Label from '@/components/ui/label/Label.vue';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Plus, Trash2 } from 'lucide-vue-next';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import AlertBox from '@/components/AlertBox.vue';
import axios from 'axios';
import DeleteButton from '@/components/common/DeleteButton.vue';

const { isOpen } = defineProps<{
  isOpen: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

type Line = {
    id: number;
    article?: string;
    price: number;
    tax: number;
    quantity: number;
    description: string;
};

const documentNumber = ref<string>('Automático');
const adjustmentType = ref<'entrada' | 'salida' | ''>('');
const date = ref<string>('');
const observation = ref<string>('');
const productionOrder = ref<string>('');
const taxes = ref<any[]>([]);
const dateInputRef = ref<HTMLInputElement | null>(null);

const lines = ref<Line[]>([
    { id: 1, article: '', price: 0, tax: 0, quantity: 0, description: '' },
]);

const adjustmentTypeOptions = [
    { id: 'sinFactura', name: 'Entrada de inventario sin factura' },
    { id: 'despacho', name: 'En bodega para despacho' },
];

async function fetchTaxes() {
  try {
    const { data } = await axios.get('/impuestos/data', { params: { per_page: 100, status: true } });
    taxes.value = (data.data ?? []).filter((t: any) => t.status);
  } catch (e) {
    console.error('Error cargando impuestos', e);
  }
}

onMounted(() => {
  fetchTaxes();
});

const alertMessage = ref<string>('');
const company = {
  name: 'Demo S.A.S.',
  nit: '000.000.000-0',
  email: 'demo@empresa.com',
};
// const props = defineProps<{ company: { name: string; nit: string; email: string } }>();
// const company = props.company;

function addLine() {
    const nextId = lines.value.length ? Math.max(...lines.value.map(l => l.id)) + 1 : 1;
    lines.value.push({
        id: nextId,
        article: '',
        price: 0,
        tax: 0,
        quantity: 0,
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

function handleSubmit() {
    alertMessage.value = '';
    const requiredErrors: string[] = [];

    if (!adjustmentType.value) {
        requiredErrors.push('Tipo de ajuste');
    }
    if (!date.value) {
        requiredErrors.push('Fecha');
    }

    if (!lines.value.length) {
        requiredErrors.push('Al menos una línea de detalle');
    } else {
        lines.value.forEach((line) => {
            if (!line.article || String(line.article).trim() === '') {
                requiredErrors.push("Artículo");
            }
            if (!line.quantity || line.quantity <= 0) {
                requiredErrors.push("Cantidad");
            }
            if (line.price === null || line.price === undefined || line.price < 0) {
                requiredErrors.push("Precio");
            }
        });
    }

    if (requiredErrors.length > 0) {
        const unique = Array.from(new Set(requiredErrors));
        alertMessage.value = 'Por favor completa todos los campos obligatorios marcados con (*). ' + ` Campos incompletos: ${unique.join(', ')}.`;

        const dialogEl = document.querySelector('[role="dialog"]');
        dialogEl?.scrollTo({ top: 0, behavior: 'smooth' });

        return;
    }

    console.log('Guardar', {
        documentNumber: documentNumber.value,
        adjustmentType: adjustmentType.value,
        date: date.value,
        observation: observation.value,
        productionOrder: productionOrder.value,
        lines: lines.value,
        subtotal: subtotal.value,
        iva: ivaTotal.value,
        total: totalAdjustment.value,
    });

    emit('close');
}
</script>

<template>
    <Dialog :open="isOpen" @update:open="emit('close')">
        <DialogContent class="max-w-5xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>Nuevo ajuste de inventario</DialogTitle>
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
                            <p class="mt-1 text-xs text-muted-foreground"></p>
                        </div>

                        <div
                            class="flex items-center justify-center rounded-lg border border-dashed text-sm text-muted-foreground"
                        >
                            Espacio para logo
                        </div>

                        <div class="space-y-1 text-sm">
                            <p class="font-semibold">{{ company.name }}</p>
                            <p>Razón social: {{ company.name }}</p>
                            <p>NIT: {{ company.nit }}</p>
                            <p>Correo: {{ company.email }}</p>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-12 items-end">
                        <div class="md:col-span-4">
                            <SelectField
                                v-model="adjustmentType"
                                label="Tipo de ajuste"
                                :options="adjustmentTypeOptions"
                                placeholder="Selecciona tipo de ajuste"
                                required
                            />
                        </div>

                        <div class="md:col-span-3">
                            <Label>
                                Fecha <span class="text-destructive">*</span>
                            </Label>
                            <Input
                                ref="dateInputRef"
                                v-model="date"
                                type="date"
                                class="w-full"
                                required
                            />
                        </div>


                        <div class="md:col-span-5">
                            <Label>Observación</Label>
                            <Input
                                v-model="observation"
                                class="mt-1"
                                placeholder="Ingrese una observación"
                            />
                        </div>
                    </div>

                    <div class="mt-4">
                        <Label>Orden de producción</Label>
                        <Input
                            v-model="productionOrder"
                            class="mt-1"
                            placeholder="Ingrese la orden de producción asociada"
                        />
                    </div>

                    <div class="mt-6">
                        <div class="mb-2 flex items-center justify-between">
                            <span class="text-sm font-semibold">Detalle de líneas</span>
                            <Button type="button" variant="outline" size="sm" class="flex items-center gap-1" @click="addLine">
                                <Plus class="h-4 w-4" />
                                Crear línea
                            </Button>
                        </div>

                        <div class="overflow-x-auto rounded-lg border">
                            <Table class="min-w-full text-center table-fixed">
                                <TableHeader>
                                    <TableRow>
                                    <TableHead class="w-[18%]">
                                        Artículo <span class="text-destructive">*</span>
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
                                        Descripción
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
                                        <Input
                                        v-model="line.article"
                                        placeholder="Artículo"
                                        class="h-8 w-full"
                                        />
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
                                        min="0"
                                        step="1"
                                        placeholder="0"
                                        class="h-8 w-full"
                                        />
                                    </TableCell>

                                    <TableCell>
                                        <Input
                                        v-model="line.description"
                                        placeholder="Descripción"
                                        class="h-8 w-full"
                                        />
                                    </TableCell>

                                    <TableCell>
                                        {{ lineTotal(line).toFixed(2) }}
                                    </TableCell>

                                    <TableCell class="text-center">
                                        <DeleteButton
                                            class="mx-auto"
                                            title="Eliminar línea"
                                            @click="removeLine(line.id)"
                                        />
                                    </TableCell>
                                    </TableRow>

                                    <TableRow v-if="lines.length === 0">
                                    <TableCell colspan="7" class="text-center text-gray-400">
                                        No hay líneas agregadas.
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
                    <Button type="submit" class="px-6">
                        Guardar
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
