<script setup lang="ts">
import { ref, computed } from 'vue';
import Input from '@/components/ui/input/Input.vue';
import TextareaField from '@/components/TextareaField.vue';
import SelectField from '@/components/SelectField.vue';
import Label from '@/components/ui/label/Label.vue';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Plus, Trash2 } from 'lucide-vue-next';

import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';

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

const lines = ref<Line[]>([
    { id: 1, article: '', price: 0, tax: 0, quantity: 0, description: '' },
]);

const adjustmentTypeOptions = [
    { id: 'entrada', name: 'Entrada' },
    { id: 'salida', name: 'Salida' },
];

const taxOptions = [
    { id: 0, name: 'Exento (0%)' },
    { id: 19, name: 'IVA 19% (19%)' },
];

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
    console.log('Guardar ajuste', {
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

            <form class="space-y-4" @submit.prevent="handleSubmit">
                <div class="rounded-xl border border-sidebar-border/70 bg-white p-4 dark:border-sidebar-border">
                    <!-- Primera fila -->
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div>
                            <Label># Documento</Label>
                            <Input v-model="documentNumber" class="mt-1" readonly />
                            <p class="mt-1 text-xs text-muted-foreground">Automático</p>
                        </div>

                        <div
                            class="flex items-center justify-center rounded-lg border border-dashed text-sm text-muted-foreground"
                        >
                            Espacio para logo
                        </div>

                        <div class="space-y-1 text-sm">
                            <p class="font-semibold">Datos empresa</p>
                            <p>Razón social: Demo S.A.S.</p>
                            <p>NIT: 000.000.000-0</p>
                            <p>Correo: demo@empresa.com</p>
                        </div>
                    </div>

                    <!-- Segunda fila -->
                    <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
                        <SelectField
                            v-model="adjustmentType"
                            label="Tipo de ajuste *"
                            :options="adjustmentTypeOptions"
                            placeholder="Selecciona tipo de ajuste"
                            required
                        />

                        <div>
                            <Label>Fecha *</Label>
                            <Input v-model="date" type="date" class="mt-1" required />
                        </div>

                        <div class="md:col-span-1">
                            <TextareaField
                                v-model="observation"
                                label="Observación"
                            />
                        </div>
                    </div>

                    <!-- Orden de producción -->
                    <div class="mt-4">
                        <Label>Orden de producción</Label>
                        <Input
                            v-model="productionOrder"
                            class="mt-1"
                            placeholder="Ingrese la orden de producción asociada (opcional)"
                        />
                    </div>

                    <!-- Tabla de líneas -->
                    <div class="mt-6">
                        <div class="mb-2 flex items-center justify-between">
                            <span class="text-sm font-semibold">Detalle de líneas</span>
                            <Button type="button" variant="outline" size="sm" class="flex items-center gap-1" @click="addLine">
                                <Plus class="h-4 w-4" />
                                Crear línea
                            </Button>
                        </div>

                        <div class="overflow-x-auto rounded-lg border">
                            <Table class="min-w-full text-center">
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Artículo *</TableHead>
                                        <TableHead>Precio *</TableHead>
                                        <TableHead>Impuesto</TableHead>
                                        <TableHead>Cantidad *</TableHead>
                                        <TableHead>Descripción</TableHead>
                                        <TableHead>Total</TableHead>
                                        <TableHead></TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="line in lines" :key="line.id">
                                        <TableCell>
                                            <Input
                                                v-model="line.article"
                                                placeholder="Artículo"
                                                class="h-8"
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <Input
                                                v-model.number="line.price"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                placeholder="0.00"
                                                class="h-8"
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <SelectField
                                                v-model="line.tax"
                                                :options="taxOptions"
                                                label="Impuesto"
                                                placeholder="%"
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <Input
                                                v-model.number="line.quantity"
                                                type="number"
                                                min="0"
                                                step="1"
                                                placeholder="0"
                                                class="h-8"
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <Input
                                                v-model="line.description"
                                                placeholder="Descripción"
                                                class="h-8"
                                            />
                                        </TableCell>
                                        <TableCell>
                                            {{ lineTotal(line).toFixed(2) }}
                                        </TableCell>
                                        <TableCell>
                                            <button
                                                type="button"
                                                class="rounded-full p-1 hover:bg-red-50"
                                                @click="removeLine(line.id)"
                                            >
                                                <Trash2 class="h-4 w-4 text-red-500" />
                                            </button>
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

                    <!-- Totales -->
                    <div class="mt-4 flex flex-col items-end gap-1 text-sm">
                        <div>Subtotal: <span class="font-semibold">{{ subtotal.toFixed(2) }}</span></div>
                        <div>IVA total: <span class="font-semibold">{{ ivaTotal.toFixed(2) }}</span></div>
                        <div>Total ajuste: <span class="font-semibold">{{ totalAdjustment.toFixed(2) }}</span></div>
                    </div>
                </div>

                <!-- Botones -->
                <div class="flex justify-end gap-2">
                    <Button type="button" variant="outline" @click="emit('close')">
                        Cancelar
                    </Button>
                    <Button type="submit" class="px-6">
                        Guardar ajuste
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
