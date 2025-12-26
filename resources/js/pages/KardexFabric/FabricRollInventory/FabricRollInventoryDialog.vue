<script setup lang="ts">
import { Dialog, DialogContent, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import FormHeader from '@/components/FormHeader.vue';
import { computed } from 'vue';
import type { FabricRollView } from '@/types';

const props = withDefaults(
    defineProps<{
        modelValue?: boolean;
        roll?: FabricRollView | null;
    }>(),
    { modelValue: false, roll: null },
);

const emit = defineEmits<{
    (event: 'update:modelValue', value: boolean): void;
}>();

function handleOpenChange(open: boolean) {
    emit('update:modelValue', open);
}

const detailRows = computed(() => {
    if (!props.roll) return [];
    return [
        { label: 'N. Pedido', value: props.roll.orderNumber },
        { label: 'Proveedor', value: props.roll.provider },
        { label: 'Fecha de creación', value: props.roll.createdAt },
        { label: 'Usuario creador', value: props.roll.creator },
    ];
});

function closeDialog() {
    emit('update:modelValue', false);
}
</script>

<template>
            <Dialog :open="props.modelValue" @openChange="handleOpenChange">
        <DialogContent class="max-w-2xl rounded-3xl border border-muted-foreground/40 bg-white p-6 shadow-lg">
            <FormHeader
                title="Detalle del rollo"
                description="Verifica medidas clave, origen y disponibilidad real."
                class="mb-4"
            />

            <div v-if="props.roll" class="space-y-5 text-sm text-muted-foreground">
                <div class="rounded-2xl border border-muted-foreground/40 bg-white p-4 shadow-sm space-y-6">
                    <div>
                        <p class="text-sm font-semibold text-muted-foreground">Detalle</p>
                        <div class="mt-3 space-y-2">
                            <div v-for="row in detailRows" :key="row.label" class="flex items-center justify-between">
                                <p class="text-xs text-muted-foreground">{{ row.label }}</p>
                                <p class="text-sm font-medium text-slate-900">{{ row.value }}</p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <p class="text-sm font-semibold text-muted-foreground">Características</p>
                        <div class="mt-3 grid gap-3">
                            <div class="flex items-center justify-between">
                                <p class="text-xs text-muted-foreground">Rollo</p>
                                <p class="text-sm font-medium text-slate-900">{{ props.roll.roll }}</p>
                            </div>
                            <div class="flex items-center justify-between">
                                <p class="text-xs text-muted-foreground">Tela</p>
                                <p class="text-sm font-medium text-slate-900">{{ props.roll.fabric }}</p>
                            </div>
                            <div class="flex items-center justify-between">
                                <p class="text-xs text-muted-foreground">Color</p>
                                <p class="text-sm font-medium text-slate-900 flex items-center gap-2">
                                    <span class="h-3 w-3 rounded-full border" :style="{ backgroundColor: props.roll.colorCode }" />
                                    {{ props.roll.color }}
                                </p>
                            </div>
                            <div class="flex items-center justify-between">
                                <p class="text-xs text-muted-foreground">Cod. color</p>
                                <p class="text-sm font-medium text-slate-900">{{ props.roll.colorCode }}</p>
                            </div>
                            <div class="flex items-center justify-between">
                                <p class="text-xs text-muted-foreground">Ubicación</p>
                                <p class="text-sm font-medium text-slate-900">{{ props.roll.location }}</p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <p class="text-sm font-semibold text-muted-foreground">Medidas y disponibilidad</p>
                        <div class="mt-3 grid gap-4 md:grid-cols-3">
                            <div class="text-center">
                                <p class="text-xs text-muted-foreground">Ancho</p>
                                <p class="text-sm font-medium text-slate-900">{{ props.roll.width }}</p>
                            </div>
                            <div class="text-center">
                                <p class="text-xs text-muted-foreground">Cant. rollo</p>
                                <p class="text-sm font-medium text-slate-900">{{ props.roll.rollQty }}</p>
                            </div>
                            <div class="text-center">
                                <p class="text-xs text-muted-foreground">Salida parcial</p>
                                <p class="text-sm font-medium text-slate-900">{{ props.roll.partialOutput }}</p>
                            </div>
                            <div class="text-center">
                                <p class="text-xs text-muted-foreground">Disponibilidad parcial</p>
                                <p class="text-sm font-medium text-slate-900">{{ props.roll.partialAvailability }}</p>
                            </div>
                            <div class="text-center">
                                <p class="text-xs text-muted-foreground">Cantidad salida</p>
                                <p class="text-sm font-medium text-slate-900">{{ props.roll.exitQuantity }}</p>
                            </div>
                            <div class="text-center">
                                <p class="text-xs text-muted-foreground">Disponibilidad real</p>
                                <p class="text-sm font-medium text-slate-900">{{ props.roll.realAvailability }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <DialogFooter class="mt-4 flex justify-end">
                <Button variant="outline" class="w-full md:w-auto" @click="closeDialog">Cerrar</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
