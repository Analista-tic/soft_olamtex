<script setup lang="ts">
import FormDialogContent from '@/components/FormDialogContent.vue';
import FormHeader from '@/components/FormHeader.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogFooter } from '@/components/ui/dialog';
import { Shirt } from 'lucide-vue-next';
import { computed } from 'vue';

type LaundryData = {
    type?: string | null;
    instructions?: string | null;
};

const LAUNDRY_TYPE_LABELS: Record<string, string> = {
    LAVADO_EN_SECO: 'Lavado en seco',
    LAVADO_A_MANO: 'Lavado a mano',
    LAVADO_A_MAQUINA: 'Lavado a máquina',
    NO_LAVAR: 'No lavar',
    NO_APLICA: 'No aplica',
};

const props = defineProps<{
    open: boolean;
    data: LaundryData;
    opCode?: string | null;
    referencia?: string | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const laundryTypeLabel = computed(() => {
    const raw = String(props.data.type ?? '').trim().toUpperCase();
    if (!raw) return null;
    return LAUNDRY_TYPE_LABELS[raw] ?? raw.charAt(0) + raw.slice(1).toLowerCase().replace(/_/g, ' ');
});

const instructions = computed(() => String(props.data.instructions ?? '').trim() || null);
</script>

<template>
    <Dialog :open="open" @update:open="(v) => !v && emit('close')">
        <FormDialogContent
            overlay-class="backdrop-blur-0"
            class="flex max-h-[80vh] max-w-[560px] flex-col overflow-hidden px-2 py-2 data-[state=closed]:animate-none data-[state=open]:animate-none"
        >
            <FormHeader
                title="Instrucciones de lavandería"
                :description="opCode ? `Orden ${opCode}${referencia ? ` — ${referencia}` : ''}` : 'Cuidados y observaciones de lavado.'"
            />

            <div class="min-h-0 flex-1 overflow-y-auto pr-1 pt-4 space-y-5">
                <!-- Tipo de lavado -->
                <div>
                    <p class="mb-2 text-sm font-semibold text-foreground">Tipo de lavado</p>
                    <div
                        v-if="laundryTypeLabel"
                        class="inline-flex items-center gap-2 rounded-full border border-sky-200 bg-sky-50 px-4 py-1.5 text-sm font-medium text-sky-800"
                    >
                        <Shirt class="h-4 w-4 shrink-0" />
                        {{ laundryTypeLabel }}
                    </div>
                    <p v-else class="text-sm text-muted-foreground">Sin tipo de lavado definido.</p>
                </div>

                <!-- Instrucciones de lavado / cuidados -->
                <div>
                    <p class="mb-2 text-sm font-semibold text-foreground">Instrucciones de lavado y cuidados</p>
                    <div
                        v-if="instructions"
                        class="min-h-[100px] rounded-md border border-border bg-muted/20 px-4 py-3 text-sm text-foreground whitespace-pre-wrap leading-relaxed"
                    >
                        {{ instructions }}
                    </div>
                    <div v-else class="flex min-h-[80px] items-center justify-center rounded-md border border-dashed border-border bg-muted/10 px-3 py-3">
                        <p class="text-sm text-muted-foreground">Sin instrucciones de lavado registradas.</p>
                    </div>
                </div>

                <!-- Estado general -->
                <div
                    v-if="!laundryTypeLabel && !instructions"
                    class="rounded-lg border border-dashed border-muted-foreground/25 bg-muted/10 px-4 py-6 text-center"
                >
                    <Shirt class="mx-auto mb-2 h-8 w-8 text-muted-foreground/50" />
                    <p class="text-sm text-muted-foreground">No hay información de lavandería registrada para esta orden.</p>
                    <p class="mt-1 text-xs text-muted-foreground/70">Configúrala en la ficha técnica → Información adicional.</p>
                </div>
            </div>

            <DialogFooter class="mt-4 shrink-0 border-t bg-background px-1 pt-4">
                <Button type="button" variant="outline" class="rounded-full px-6" @click="emit('close')">Cerrar</Button>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
</template>
