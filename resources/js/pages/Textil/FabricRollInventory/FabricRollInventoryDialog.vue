<script setup lang="ts">
import FormDialogContent from '@/components/FormDialogContent.vue';
import FormHeader from '@/components/FormHeader.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogFooter } from '@/components/ui/dialog';
import type { FabricRollView } from '@/domain/Textil';
import { computed } from 'vue';

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
        { label: 'N. Pedido', value: props.roll.order_number },
        { label: 'Proveedor', value: props.roll.provider },
        { label: 'Fecha de creación', value: props.roll.created_at },
        { label: 'Usuario creador', value: props.roll.creator },
    ];
});

function closeDialog() {
    emit('update:modelValue', false);
}
</script>

<template>
    <Dialog :open="props.modelValue" modal @openChange="handleOpenChange">
        <FormDialogContent class="flex max-h-[95vh] max-w-[85vh] flex-col px-2 py-2">
            <FormHeader title="Detalle del rollo" description="Verifica medidas clave, origen y disponibilidad real." class="mb-4 flex-shrink-0" />

            <div class="flex-1 overflow-y-auto scroll-smooth pr-2" style="scrollbar-width: thin">
                <div v-if="props.roll" class="space-y-5 text-sm text-muted-foreground">
                    <div class="space-y-6 rounded-2xl border border-muted-foreground/40 bg-card p-4 shadow-sm">
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
                                    <p class="text-sm font-medium text-slate-900">{{ props.roll.color }}</p>
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
                                    <p class="text-sm font-medium text-slate-900">{{ props.roll.roll_qty }}</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-xs text-muted-foreground">Salida parcial</p>
                                    <p class="text-sm font-medium text-slate-900">{{ props.roll.partial_output }}</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-xs text-muted-foreground">Disponibilidad parcial</p>
                                    <p class="text-sm font-medium text-slate-900">{{ props.roll.partial_availability }}</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-xs text-muted-foreground">Cantidad salida</p>
                                    <p class="text-sm font-medium text-slate-900">{{ props.roll.exit_quantity }}</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-xs text-muted-foreground">Disponibilidad real</p>
                                    <p class="text-sm font-medium text-slate-900">{{ props.roll.real_availability }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <DialogFooter class="mt-4 flex flex-shrink-0 flex-col gap-2 border-t pt-4 md:flex-row md:justify-end">
                <div class="w-full md:w-auto">
                    <Button variant="outline" size="pill" class="mx-auto flex w-full max-w-[140px] justify-center" @click="closeDialog">
                        Cerrar
                    </Button>
                </div>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
</template>
