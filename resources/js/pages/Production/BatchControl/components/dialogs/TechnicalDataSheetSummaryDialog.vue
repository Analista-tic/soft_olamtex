<script setup lang="ts">
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useOpDetail } from '@/composables/useOpDetail';
import type { BatchControlRow } from '../../types';
import TechnicalDataSheetSummary from '@/pages/Production/TechnicalDataSheet/tabs/TechnicalDataSheetSummary.vue';
import { watch } from 'vue';

const props = defineProps<{
    open: boolean;
    row: BatchControlRow | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', v: boolean): void;
}>();

const {
    opDetail,
    isLoading,
    loadError,
    drawingUrl,
    rawMaterials,
    rollState,
    processes,
    costs,
    supplies,
    threads,
    additionalInfo,
    operationalList,
    cuttingPieces,
    load,
    reset,
} = useOpDetail();

watch(
    () => props.open,
    (open) => {
        if (!open) {
            reset();
            return;
        }
        if (props.row?.id) {
            load(props.row.id);
        }
    },
);

watch(
    () => props.row?.id,
    (id) => { if (props.open) load(id); },
    { immediate: true },
);
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-5xl p-0">
            <div class="flex flex-col" style="max-height: 90dvh;">
                <DialogHeader class="flex-none px-6 pt-6 pr-14">
                    <DialogTitle>Resumen de Ficha Tecnica</DialogTitle>
                    <DialogDescription class="sr-only">Resumen completo de ficha tecnica y datos de produccion.</DialogDescription>
                </DialogHeader>

                <div class="min-h-0 flex-1 overflow-y-auto px-6 pb-6">
                    <div v-if="isLoading" class="py-12 text-center">
                        <p class="text-muted-foreground">Cargando informacion...</p>
                    </div>

                    <div v-else-if="loadError" class="py-12 text-center">
                        <p class="text-destructive">{{ loadError }}</p>
                    </div>

                    <TechnicalDataSheetSummary
                        v-else-if="opDetail"
                        :op="opDetail"
                        :raw-materials="rawMaterials"
                        :roll-state="rollState"
                        :processes="processes"
                        :costs="costs"
                        :supplies="supplies"
                        :threads="threads"
                        :additional-info="additionalInfo"
                        :operational-list="operationalList"
                        :cutting-pieces="cuttingPieces"
                        :drawing-url="drawingUrl"
                    />

                    <div v-else class="py-12 text-center">
                        <p class="text-muted-foreground">No hay datos disponibles.</p>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
