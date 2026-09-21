<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { ArrowRight, CheckCircle2, Send } from 'lucide-vue-next';
import type { BatchControlRow } from '../../types';

defineProps<{
    open: boolean;
    row: BatchControlRow | null;
    deliverToProcess: string;
    deliverObservations: string;
    otherProcesses: string[];
    nextProcess: string | null;
    isDelivering: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', v: boolean): void;
    (e: 'update:deliverToProcess', v: string): void;
    (e: 'update:deliverObservations', v: string): void;
    (e: 'confirm'): void;
    (e: 'close'): void;
}>();
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="gap-0 overflow-hidden p-0 sm:max-w-lg">
            <!-- Header -->
            <DialogHeader class="border-b border-border/50 bg-primary/5 px-6 py-5 pr-14">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary/10">
                        <Send class="h-4 w-4 text-primary" />
                    </div>
                    <div class="min-w-0">
                        <DialogTitle class="text-base font-semibold leading-tight">Entregar orden</DialogTitle>
                        <DialogDescription class="mt-0.5 truncate text-xs text-muted-foreground">
                            <span class="font-mono font-semibold text-foreground">{{ row?.op_code }}</span>
                            <span class="mx-1">·</span>
                            {{ row?.referencia }}
                        </DialogDescription>
                    </div>
                </div>
                <div class="mt-3 flex items-center gap-2 text-xs">
                    <span class="text-muted-foreground">Estado actual</span>
                    <Badge variant="outline" class="border-primary/30 bg-primary/5 text-primary">{{ row?.current_process }}</Badge>
                </div>
            </DialogHeader>

            <!-- Body -->
            <div class="space-y-5 px-6 py-5">
                <!-- Entregar a -->
                <div class="space-y-1.5">
                    <label class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Entregar a</label>
                    <Select :model-value="deliverToProcess" @update:model-value="emit('update:deliverToProcess', $event as string)">
                        <SelectTrigger class="h-9">
                            <SelectValue placeholder="Selecciona el siguiente proceso" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="p in otherProcesses" :key="p" :value="p">
                                {{ p }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="nextProcess && deliverToProcess === nextProcess" class="flex items-center gap-1 text-xs text-emerald-600">
                        <CheckCircle2 class="h-3 w-3 shrink-0" />
                        Siguiente proceso en el flujo
                    </p>
                    <div v-if="deliverToProcess && row?.current_process" class="flex items-center gap-1.5 text-xs text-muted-foreground">
                        <ArrowRight class="h-3 w-3 shrink-0" />
                        <span class="font-mono">{{ row?.current_process }}</span>
                        <span>→</span>
                        <span class="font-mono font-medium text-foreground">{{ deliverToProcess }}</span>
                    </div>
                </div>

                <!-- Observaciones -->
                <div class="space-y-1.5">
                    <label class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Observaciones <span class="lowercase">(opcional)</span></label>
                    <Textarea data-no-uppercase
                        :model-value="deliverObservations"
                        placeholder="Estado del proceso, novedades o lo que el siguiente responsable debe saber..."
                        :rows="4"
                        class="resize-none text-sm"
                        @update:model-value="emit('update:deliverObservations', String($event))"
                    />
                </div>
            </div>

            <!-- Footer -->
            <DialogFooter class="border-t border-border/50 px-6 py-4">
                <Button
                    type="button"
                    variant="outline"
                    size="pill"
                    class="border-primary/40 text-primary shadow-sm hover:bg-primary/10"
                    :disabled="isDelivering"
                    @click="emit('close')"
                >
                    Cancelar
                </Button>
                <Button
                    type="button"
                    size="pill"
                    class="shadow-sm"
                    :disabled="!deliverToProcess || isDelivering"
                    @click="emit('confirm')"
                >
                    <Send class="mr-1.5 h-3.5 w-3.5" />
                    {{ isDelivering ? 'Entregando...' : 'Confirmar entrega' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
