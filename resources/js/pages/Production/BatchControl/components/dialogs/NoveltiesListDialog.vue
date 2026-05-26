<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';
import { Textarea } from '@/components/ui/textarea';
import { CheckCircle2, MessageSquareWarning } from 'lucide-vue-next';
import type { BatchControlRow, NoveltyItem } from '../../types';

defineProps<{
    open: boolean;
    row: BatchControlRow | null;
    items: NoveltyItem[];
    isLoading: boolean;
    resolvingNoveltyId: number | null;
    resolveNotes: string;
}>();

const emit = defineEmits<{
    (e: 'update:open', v: boolean): void;
    (e: 'update:resolveNotes', v: string): void;
    (e: 'startResolve', noveltyId: number): void;
    (e: 'cancelResolve'): void;
    (e: 'confirmResolve', noveltyId: number): void;
    (e: 'close'): void;
}>();
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-2xl">
            <DialogHeader class="pr-14">
                <DialogTitle class="flex items-center gap-2">
                    <MessageSquareWarning class="h-5 w-5 text-orange-500" />
                    Novedades
                </DialogTitle>
                <DialogDescription>
                    Orden <span class="font-mono font-semibold">{{ row?.op_code }}</span> — {{ row?.referencia }}
                </DialogDescription>
            </DialogHeader>
            <div class="max-h-[450px] space-y-3 overflow-y-auto py-4">
                <div v-if="isLoading" class="space-y-3">
                    <Skeleton v-for="i in 3" :key="i" class="h-20 w-full rounded-lg" />
                </div>
                <div v-else-if="items.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                    No hay novedades registradas para esta orden.
                </div>
                <div
                    v-for="item in items"
                    v-else
                    :key="item.id"
                    :class="[
                        'rounded-lg border p-4',
                        item.status === 'pending' ? 'border-orange-200 bg-orange-50/50' : 'border-border/40 bg-muted/10',
                    ]"
                >
                    <div class="mb-2 flex items-center justify-between">
                        <div class="flex items-center gap-2 text-sm">
                            <Badge
                                :variant="item.status === 'pending' ? 'destructive' : 'secondary'"
                                :class="item.status === 'pending' ? 'bg-orange-500 hover:bg-orange-600' : ''"
                                class="text-xs"
                            >
                                {{ item.status === 'pending' ? 'Pendiente' : 'Resuelta' }}
                            </Badge>
                            <span class="text-muted-foreground">De</span>
                            <Badge variant="outline" class="text-xs">{{ item.from_process }}</Badge>
                            <span class="text-muted-foreground">→</span>
                            <Badge variant="outline" class="text-xs">{{ item.to_process }}</Badge>
                        </div>
                        <span class="text-xs text-muted-foreground">{{ item.reported_at }}</span>
                    </div>
                    <div class="mb-2 text-sm text-foreground">{{ item.description }}</div>
                    <div class="text-xs text-muted-foreground">
                        Reportado por: <span class="font-medium">{{ item.reported_by_name }}</span>
                    </div>
                    <div v-if="item.status === 'resolved'" class="mt-2 rounded-md border border-green-200 bg-green-50/50 p-2">
                        <div class="flex items-center gap-1 text-xs font-medium text-green-700">
                            <CheckCircle2 class="h-3.5 w-3.5" />
                            Resuelta por {{ item.resolved_by_name }} el {{ item.resolved_at }}
                        </div>
                        <div v-if="item.resolution_notes" class="mt-1 text-xs text-green-800">
                            {{ item.resolution_notes }}
                        </div>
                    </div>
                    <div v-if="item.status === 'pending'" class="mt-3">
                        <template v-if="resolvingNoveltyId === item.id">
                            <div class="space-y-2">
                                <Textarea
                                    :model-value="resolveNotes"
                                    placeholder="Notas de resolución (opcional)..."
                                    :rows="2"
                                    class="resize-none text-sm"
                                    @update:model-value="emit('update:resolveNotes', String($event))"
                                />
                                <div class="flex gap-2">
                                    <Button
                                        size="sm"
                                        variant="default"
                                        class="gap-1 bg-green-600 text-xs hover:bg-green-700"
                                        @click="emit('confirmResolve', item.id)"
                                    >
                                        <CheckCircle2 class="h-3.5 w-3.5" />
                                        Confirmar resolución
                                    </Button>
                                    <Button size="sm" variant="outline" class="text-xs" @click="emit('cancelResolve')"> Cancelar </Button>
                                </div>
                            </div>
                        </template>
                        <template v-else>
                            <Button
                                size="sm"
                                variant="outline"
                                class="gap-1 border-green-300 text-xs text-green-600 hover:bg-green-50 hover:text-green-700"
                                @click="emit('startResolve', item.id)"
                            >
                                <CheckCircle2 class="h-3.5 w-3.5" />
                                Marcar como resuelta
                            </Button>
                        </template>
                    </div>
                </div>
            </div>
            <DialogFooter>
                <Button variant="outline" @click="emit('close')">Cerrar</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
