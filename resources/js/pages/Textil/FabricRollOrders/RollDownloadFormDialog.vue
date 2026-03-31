<script setup lang="ts">
import FormDialogContent from '@/components/FormDialogContent.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { FabricRollView } from '@/domain/Textil';
import { computed, reactive, watch } from 'vue';

type FormValues = {
    largoTrazo: string;
    largoLiquidacion: string;
    largoEspiga: string;

    capasPrincipal: string;
    capasLiquidacion: string;
    capasEspiga: string;

    capasPrincipalLargo: string;
    capasLiquidacionLargo: string;
    capasEspigaLargo: string;

    retazo1: string;
    retazo2: string;
    retazo3: string;

    consumoTotalManual: string;
    restanteManual: string;

    ultimoDescargue: boolean;
};

const props = withDefaults(
    defineProps<{
        modelValue?: boolean;
        roll?: FabricRollView | null;
    }>(),
    {
        modelValue: false,
        roll: null,
    },
);

const emit = defineEmits<{
    (event: 'update:modelValue', value: boolean): void;
    (
        event: 'submit',
        payload: {
            roll: FabricRollView | null;
            data: FormValues & {
                tallas: Record<string, string>;
                consumoTotal: number;
                restante: number;
            };
        },
    ): void;
}>();

/** Utils */
const toNumber = (value: number | string | null | undefined) => {
    if (value === null || value === undefined) return 0;
    const numeric = Number(value);
    return Number.isNaN(numeric) ? 0 : numeric;
};

/** Constantes UI */
const SIZE_KEYS = ['S', 'M', 'L', 'XL'] as const;
type SizeKey = (typeof SIZE_KEYS)[number];

type LargoKey = 'largoTrazo' | 'largoLiquidacion' | 'largoEspiga';
const LARGO_FIELDS: Array<{ key: LargoKey; label: string; placeholder: string; step?: string }> = [
    { key: 'largoTrazo', label: 'Largo trazo', placeholder: '0.00', step: '0.01' },
    { key: 'largoLiquidacion', label: 'Largo liquidación', placeholder: '0.00', step: '0.01' },
    { key: 'largoEspiga', label: 'Largo espiga', placeholder: '0.00', step: '0.01' },
];

type ConsumoKey = 'capasPrincipal' | 'capasLiquidacion' | 'capasEspiga' | 'retazo1' | 'retazo2' | 'retazo3';

const CONSUMO_FIELDS: Array<{ key: ConsumoKey; label: string; placeholder: string }> = [
    { key: 'capasPrincipal', label: 'Capas principal', placeholder: '0' },
    { key: 'capasLiquidacion', label: 'Capas liquidación', placeholder: '0' },
    { key: 'capasEspiga', label: 'Capas espiga', placeholder: '0' },
    { key: 'retazo1', label: 'Retazo 1', placeholder: '0' },
    { key: 'retazo2', label: 'Retazo 2', placeholder: '0' },
    { key: 'retazo3', label: 'Retazo 3', placeholder: '0' },
];

/** Estado */
const createInitialForm = (roll?: FabricRollView | null): FormValues => ({
    largoTrazo: roll?.quantity_value ? String(roll.quantity_value) : '',
    largoLiquidacion: '',
    largoEspiga: '',

    capasPrincipal: '',
    capasLiquidacion: '',
    capasEspiga: '',

    capasPrincipalLargo: '',
    capasLiquidacionLargo: '',
    capasEspigaLargo: '',

    retazo1: '',
    retazo2: '',
    retazo3: '',

    consumoTotalManual: '',
    restanteManual: '',

    ultimoDescargue: false,
});

const createInitialTallas = (): Record<SizeKey, string> => ({
    S: '',
    M: '',
    L: '',
    XL: '',
});

const form = reactive<FormValues>(createInitialForm(props.roll));
const tallas = reactive<Record<SizeKey, string>>(createInitialTallas());

/** Cálculos (misma lógica) */
const consumoTotal = computed(() => {
    return CONSUMO_FIELDS.reduce((sum, field) => sum + toNumber(form[field.key]), 0);
});

const restante = computed(() => {
    const quantity = toNumber(props.roll?.quantity_value);
    return Math.max(0, quantity - consumoTotal.value);
});

/** Acciones */
function resetForm() {
    Object.assign(form, createInitialForm(props.roll));
    Object.assign(tallas, createInitialTallas());
}

watch(
    () => props.modelValue,
    (open) => {
        if (open) resetForm();
    },
);

function handleOpenChange(open: boolean) {
    emit('update:modelValue', open);
}

function submitForm() {
    emit('submit', {
        roll: props.roll ?? null,
        data: {
            ...form,
            tallas: { ...tallas },
            consumoTotal: consumoTotal.value,
            restante: restante.value,
        },
    });
    handleOpenChange(false);
}
</script>

<template>
    <Dialog :open="props.modelValue" @openChange="handleOpenChange">
        <FormDialogContent class="flex max-h-[95vh] max-w-[130vh] flex-col px-2 py-2">
            <DialogHeader class="flex-shrink-0">
                <DialogTitle>Registro de descarga</DialogTitle>
                <DialogDescription> Completa los largos, consumos, retazos y tallas antes de procesar el rollo. </DialogDescription>
            </DialogHeader>

            <div class="flex-1 overflow-y-auto scroll-smooth pr-2 pt-5" style="scrollbar-width: thin">
                <div class="space-y-6">
                    <!-- RESUMEN DEL ROLLO -->
                    <section class="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
                        <div class="overflow-x-auto">
                            <Table class="text-center">
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Rollo</TableHead>
                                        <TableHead>Tela</TableHead>
                                        <TableHead>Cantidad disponible</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow class="text-center font-medium">
                                        <TableCell>
                                            {{ props.roll?.roll ?? '-' }}
                                        </TableCell>
                                        <TableCell>
                                            {{ props.roll?.fabric ?? '-' }}
                                        </TableCell>
                                        <TableCell>
                                            {{ toNumber(props.roll?.quantity_value).toFixed(2) }}
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </section>

                    <!-- LARGOS -->
                    <section class="space-y-2">
                        <p class="text-sm font-semibold text-card-foreground">Largos</p>

                        <div class="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
                            <div class="overflow-x-auto">
                                <Table class="text-center">
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead v-for="field in LARGO_FIELDS" :key="field.key">
                                                {{ field.label }}
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow>
                                            <TableCell v-for="field in LARGO_FIELDS" :key="field.key">
                                                <Input v-model="form[field.key]" type="number" :placeholder="field.placeholder" :step="field.step" />
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>
                    </section>

                    <!-- CONSUMOS (ahora sí usa CONSUMO_FIELDS -> se quita el warning) -->
                    <section class="space-y-2">
                        <p class="text-sm font-semibold text-card-foreground">Consumos</p>

                        <div class="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
                            <div class="overflow-x-auto">
                                <Table class="text-center">
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead v-for="field in CONSUMO_FIELDS" :key="field.key">
                                                {{ field.label }}
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow>
                                            <TableCell v-for="field in CONSUMO_FIELDS" :key="field.key">
                                                <Input v-model="form[field.key]" type="number" :placeholder="field.placeholder" />
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>
                    </section>

                    <!-- TALLAS ESPIGA -->
                    <section class="space-y-2">
                        <p class="text-sm font-semibold text-card-foreground">Tallas espiga</p>

                        <div class="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
                            <div class="overflow-x-auto">
                                <Table class="text-center">
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead v-for="size in SIZE_KEYS" :key="size">{{ size }}</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow>
                                            <TableCell v-for="size in SIZE_KEYS" :key="size">
                                                <Input v-model="tallas[size]" type="number" min="0" placeholder="0" class="text-center" />
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>
                    </section>

                    <!-- TOTALES -->
                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="rounded-2xl border border-border bg-card p-4 shadow-sm">
                            <p class="text-rm font-semibold text-muted-foreground">Consumo total</p>
                            <Input v-model="form.consumoTotalManual" type="number" placeholder="0" />
                            <p class="mt-1 text-xs text-muted-foreground">Calculado: {{ consumoTotal }} unidades</p>
                        </div>

                        <div class="rounded-2xl border border-border bg-card p-4 shadow-sm">
                            <p class="text-rm font-semibold text-muted-foreground">Restante rollo</p>
                            <Input v-model="form.restanteManual" type="number" placeholder="0" />
                            <p class="mt-1 text-xs text-muted-foreground">Cantidad real - consumo total: {{ restante }}</p>
                        </div>

                        <div class="rounded-2xl border border-border bg-card p-4 shadow-sm">
                            <p class="text-rm font-semibold text-muted-foreground">Último descargue</p>

                            <label class="inline-flex items-center gap-2">
                                <input
                                    v-model="form.ultimoDescargue"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border border-border text-primary focus:ring-2 focus:ring-primary"
                                />
                                <span class="text-xs text-muted-foreground">Marcar si este es el último descargue</span>
                            </label>

                            <p class="mt-1 text-xs text-muted-foreground">Si se marca, el restante pasa a ser faltante rollo.</p>
                        </div>
                    </div>
                </div>
            </div>

            <DialogFooter class="mt-6 flex flex-shrink-0 flex-col gap-2 border-t pt-4 md:flex-row md:justify-end">
                <div class="w-full md:w-auto">
                    <Button variant="outline" size="pill" class="mx-auto flex w-full max-w-[140px] justify-center" @click="handleOpenChange(false)">
                        Cancelar
                    </Button>
                </div>
                <div class="w-full md:w-auto">
                    <Button
                        variant="default"
                        class="mx-auto flex w-full max-w-[140px] items-center justify-center rounded-full px-5 py-2 text-sm font-semibold shadow-sm"
                        @click="submitForm"
                    >
                        Guardar y cerrar
                    </Button>
                </div>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
</template>
