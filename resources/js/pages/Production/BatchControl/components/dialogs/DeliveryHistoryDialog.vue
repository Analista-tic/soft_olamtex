<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';
import type { BatchControlRow, DeliveryHistoryItem } from '../../types';

defineProps<{
    open: boolean;
    row: BatchControlRow | null;
    items: DeliveryHistoryItem[];
    isLoading: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', v: boolean): void;
    (e: 'close'): void;
}>();
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-xl">
            <DialogHeader class="pr-14">
                <DialogTitle>Historial de entregas</DialogTitle>
                <DialogDescription>
                    Orden <span class="font-mono font-semibold">{{ row?.op_code }}</span> — {{ row?.referencia }}
                </DialogDescription>
            </DialogHeader>
            <div class="max-h-[400px] space-y-3 overflow-y-auto py-4">
                <div v-if="isLoading" class="space-y-3">
                    <Skeleton v-for="i in 3" :key="i" class="h-16 w-full rounded-lg" />
                </div>
                <div v-else-if="items.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                    No hay entregas registradas para esta orden.
                </div>
                <div v-for="item in items" v-else :key="item.id" class="rounded-lg border border-border/40 bg-muted/10 p-4">
                    <div class="mb-2 flex items-center justify-between">
                        <div class="flex items-center gap-2 text-sm">
                            <Badge variant="secondary" class="text-xs">{{ item.from_process }}</Badge>
                            <span class="text-muted-foreground">→</span>
                            <Badge variant="default" class="text-xs">{{ item.to_process }}</Badge>
                        </div>
                        <span class="text-xs text-muted-foreground">{{ item.delivered_at }}</span>
                    </div>
                    <div v-if="item.observations" class="mb-1 text-sm text-foreground">
                        {{ item.observations }}
                    </div>
                    <div v-else class="mb-1 text-xs italic text-muted-foreground">Sin observaciones</div>
                    <div class="text-xs text-muted-foreground">
                        Entregado por: <span class="font-medium">{{ item.delivered_by_name }}</span>
                    </div>
                </div>
            </div>
            <DialogFooter>
                <Button variant="outline" @click="emit('close')">Cerrar</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
