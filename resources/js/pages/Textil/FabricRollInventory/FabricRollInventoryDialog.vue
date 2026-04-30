<script setup lang="ts">
import FormDialogContent from '@/components/FormDialogContent.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
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

const avatarInitials = computed(() => {
    if (!props.roll?.roll) return 'RL';
    const letters = props.roll.roll.replace(/[^a-zA-Z]/g, '');
    return letters.slice(0, 2).toUpperCase() || 'RL';
});

const statusLabel = computed(() => (props.roll?.status === 'available' ? 'Disponible' : 'Agotado'));
const statusVariant = computed(() => (props.roll?.status === 'available' ? 'default' : 'destructive'));

const infoRows = computed(() => {
    if (!props.roll) return [];
    return [
        { label: 'N. Pedido', value: props.roll.order_number || '—' },
        { label: 'Proveedor', value: props.roll.provider || '—' },
        { label: 'Color', value: props.roll.color || '—' },
        { label: 'Ubicación', value: props.roll.location || '—' },
        { label: 'Fecha de creación', value: props.roll.created_at || '—' },
        { label: 'Usuario creador', value: props.roll.creator || '—' },
    ];
});

const metricCards = computed(() => {
    if (!props.roll) return [];
    return [
        { label: 'Ancho', value: props.roll.width },
        { label: 'Cant. rollo', value: props.roll.roll_qty },
        { label: 'Salida parcial', value: props.roll.partial_output },
        { label: 'Disp. parcial', value: props.roll.partial_availability },
        { label: 'Cant. salida', value: props.roll.exit_quantity },
        { label: 'Disp. real', value: props.roll.real_availability, highlight: true },
    ];
});
</script>

<template>
    <Dialog :open="props.modelValue" modal @update:open="handleOpenChange">
        <FormDialogContent class="flex max-h-[95vh] max-w-2xl flex-col px-2 py-2">
            <!-- Header -->
            <DialogHeader class="shrink-0 border-b border-sidebar-border/70 bg-muted/20 px-5 py-4 dark:border-sidebar-border">
                <div class="flex items-start gap-4">
                    <!-- Avatar de iniciales -->
                    <div
                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-base font-bold tracking-wide text-primary"
                        aria-hidden="true"
                    >
                        {{ avatarInitials }}
                    </div>

                    <!-- Nombre + tela + badge -->
                    <div class="min-w-0 flex-1 space-y-0.5">
                        <div class="flex flex-wrap items-center gap-2">
                            <DialogTitle class="truncate text-base font-semibold leading-tight text-foreground">
                                {{ props.roll?.roll ?? '—' }}
                            </DialogTitle>
                            <Badge :variant="statusVariant" class="shrink-0 text-[11px]">
                                {{ statusLabel }}
                            </Badge>
                        </div>
                        <DialogDescription class="truncate text-xs text-muted-foreground">
                            {{ props.roll?.fabric ?? 'Sin tela asignada' }}
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <!-- Cuerpo scrollable -->
            <div class="flex-1 overflow-y-auto scroll-smooth px-5 py-5" style="scrollbar-width: thin">
                <div v-if="props.roll" class="space-y-5">
                    <!-- Información general -->
                    <section>
                        <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Información general</p>
                        <div class="rounded-xl border border-border bg-card shadow-sm">
                            <dl>
                                <div
                                    v-for="(row, index) in infoRows"
                                    :key="row.label"
                                    class="grid grid-cols-2 gap-x-4 px-4 py-2.5 text-sm"
                                    :class="index % 2 === 0 ? 'bg-muted/30' : 'bg-transparent'"
                                >
                                    <dt class="font-medium text-muted-foreground">{{ row.label }}</dt>
                                    <dd class="truncate text-right font-medium text-foreground">{{ row.value }}</dd>
                                </div>
                            </dl>
                        </div>
                    </section>

                    <!-- Medidas y disponibilidad -->
                    <section>
                        <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Medidas y disponibilidad</p>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            <div
                                v-for="card in metricCards"
                                :key="card.label"
                                class="flex flex-col items-center justify-center gap-1 rounded-xl border px-3 py-4 text-center shadow-sm"
                                :class="
                                    card.highlight
                                        ? 'border-primary/30 bg-primary/5 dark:bg-primary/10'
                                        : 'border-border bg-card'
                                "
                            >
                                <span class="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">{{ card.label }}</span>
                                <span
                                    class="text-xl font-bold leading-tight"
                                    :class="card.highlight ? 'text-primary' : 'text-foreground'"
                                >
                                    {{ card.value }}
                                </span>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- Estado vacío -->
                <div v-else class="flex h-40 items-center justify-center text-sm text-muted-foreground">
                    No hay información disponible.
                </div>
            </div>

            <!-- Footer -->
            <DialogFooter class="shrink-0 border-t border-border px-5 py-4">
                <DialogClose as-child>
                    <Button variant="outline" size="pill" class="mx-auto flex w-full max-w-[140px] justify-center">
                        Cerrar
                    </Button>
                </DialogClose>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
</template>
