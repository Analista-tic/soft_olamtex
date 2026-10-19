<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Flag } from 'lucide-vue-next';
import type { BatchControlRow } from '../../types';

defineProps<{
    open: boolean;
    row: BatchControlRow | null;
    noveltyToProcess: string;
    noveltyDescription: string;
    previousProcesses: string[];
    isReportingNovelty: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', v: boolean): void;
    (e: 'update:noveltyToProcess', v: string): void;
    (e: 'update:noveltyDescription', v: string): void;
    (e: 'confirm'): void;
    (e: 'close'): void;
}>();
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="flex max-h-[90vh] flex-col gap-0 overflow-hidden p-0 sm:max-w-lg">
            <!-- Header -->
            <DialogHeader class="shrink-0 border-b border-orange-100 bg-orange-50/60 px-6 py-5 pr-14">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-orange-100">
                        <Flag class="h-4 w-4 text-orange-600" />
                    </div>
                    <div class="min-w-0">
                        <DialogTitle class="text-base font-semibold leading-tight text-orange-900">Reportar novedad</DialogTitle>
                        <DialogDescription class="mt-0.5 truncate text-xs text-orange-700/80">
                            Orden <span class="font-mono font-semibold">{{ row?.op_code }}</span> · {{ row?.referencia }}
                        </DialogDescription>
                    </div>
                </div>
                <p class="mt-2 text-xs text-orange-700/70">
                    El estado seleccionado será notificado para corregir el problema. La orden pasará al proceso anterior.
                </p>
            </DialogHeader>

            <!-- Body -->
            <div class="min-h-0 flex-1 overflow-y-auto space-y-5 px-6 py-5">
                <!-- Estado actual -->
                <div class="flex items-center gap-3 rounded-lg border border-border/50 bg-muted/20 px-4 py-3">
                    <span class="text-xs text-muted-foreground">Proceso actual</span>
                    <Badge variant="outline" class="ml-auto font-mono">{{ row?.current_process }}</Badge>
                </div>

                <!-- Reportar a -->
                <div class="space-y-1.5">
                    <label class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Reportar a (proceso anterior)</label>
                    <Select :model-value="noveltyToProcess" @update:model-value="emit('update:noveltyToProcess', $event as string)">
                        <SelectTrigger class="h-9">
                            <SelectValue placeholder="Selecciona el proceso a notificar" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="p in previousProcesses" :key="p" :value="p">
                                {{ p }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <!-- Descripción -->
                <div class="space-y-1.5">
                    <label class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                        Descripción <span class="text-red-500">*</span>
                    </label>
                    <Textarea data-no-uppercase
                        :model-value="noveltyDescription"
                        placeholder="Describe qué falta, qué debe corregirse o la razón de la devolución..."
                        :rows="4"
                        class="resize-none text-sm"
                        @update:model-value="emit('update:noveltyDescription', String($event))"
                    />
                    <p class="text-xs text-muted-foreground">
                        Detalla claramente el problema para que el responsable pueda corregirlo.
                    </p>
                </div>
            </div>

            <!-- Footer -->
            <DialogFooter class="shrink-0 border-t border-border/50 px-6 py-4">
                <Button
                    type="button"
                    variant="outline"
                    size="pill"
                    class="border-primary/40 text-primary shadow-sm hover:bg-primary/10"
                    :disabled="isReportingNovelty"
                    @click="emit('close')"
                >
                    Cancelar
                </Button>
                <Button
                    type="button"
                    size="pill"
                    class="gap-1.5 bg-orange-600 shadow-sm hover:bg-orange-700"
                    :disabled="!noveltyToProcess || !noveltyDescription.trim() || isReportingNovelty"
                    @click="emit('confirm')"
                >
                    <Flag class="h-3.5 w-3.5" />
                    {{ isReportingNovelty ? 'Reportando...' : 'Reportar novedad' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
