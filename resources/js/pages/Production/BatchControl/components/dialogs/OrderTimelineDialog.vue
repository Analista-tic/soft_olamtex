<script setup lang="ts">
import FormDialogContent from '@/components/FormDialogContent.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';
import { Textarea } from '@/components/ui/textarea';
import { CheckCircle2, Flag, GitCommitVertical, MessageSquareWarning, Send } from 'lucide-vue-next';
import { computed, nextTick, watch } from 'vue';
import type { BatchControlRow, TimelineEvent } from '../../types';

const props = defineProps<{
    open: boolean;
    row: BatchControlRow | null;
    events: TimelineEvent[];
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

/** Ordenado del más antiguo al más reciente para leer la historia en orden. */
const sortedEvents = computed(() =>
    [...props.events].sort((a, b) => new Date(a.timestamp).getTime() - new Date(b.timestamp).getTime()),
);

const pendingCount = computed(() => props.events.filter((e) => e.event_type === 'novelty' && e.status === 'pending').length);

/** Id de la primera novedad pendiente (para anclar el scroll) */
const firstPendingId = computed(() => {
    const ev = sortedEvents.value.find((e) => e.event_type === 'novelty' && e.status === 'pending');
    return ev ? ev.id : null;
});

function scrollToFirstPending() {
    if (!firstPendingId.value) return;
    const el = document.getElementById(`novelty-${firstPendingId.value}`);
    el?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

/** Cuando termina de cargar y hay pendientes, hacer scroll automático */
watch(
    () => props.isLoading,
    (loading) => {
        if (!loading && pendingCount.value > 0) {
            nextTick(() => scrollToFirstPending());
        }
    },
);
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <FormDialogContent class="!max-w-xl overflow-hidden" :scrollable="true">
            <!-- Header -->
            <DialogHeader class="shrink-0 border-b border-border/50 bg-muted/20 px-6 py-5 pr-14">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-muted/60">
                            <GitCommitVertical class="h-4 w-4 text-muted-foreground" />
                        </div>
                        <div>
                            <DialogTitle class="text-sm font-semibold leading-tight">Línea de tiempo</DialogTitle>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                <span class="font-mono font-medium text-foreground">{{ row?.op_code }}</span>
                                <span class="mx-1">·</span>
                                {{ row?.referencia }}
                            </p>
                        </div>
                    </div>
                    <div v-if="pendingCount > 0" class="shrink-0 rounded-full border border-orange-200 bg-orange-50 px-2.5 py-1 text-xs font-medium text-orange-600">
                        {{ pendingCount }} pendiente{{ pendingCount > 1 ? 's' : '' }}
                    </div>
                </div>
                <!-- Proceso actual -->
                <div v-if="row?.current_process" class="mt-3 flex items-center gap-2 text-xs text-muted-foreground">
                    <span>Proceso actual:</span>
                    <span class="rounded-md border border-border/50 bg-background px-2 py-0.5 font-mono font-medium text-foreground">
                        {{ row.current_process }}
                    </span>
                </div>
            </DialogHeader>

            <!-- Timeline body -->
            <div class="min-h-0 flex-1 overflow-y-auto px-6 py-5">
                <!-- Loading skeletons -->
                <div v-if="isLoading" class="space-y-4">
                    <div v-for="i in 3" :key="i" class="flex gap-3">
                        <Skeleton class="h-5 w-5 shrink-0 rounded-full" />
                        <Skeleton class="h-16 flex-1 rounded-lg" />
                    </div>
                </div>

                <!-- Empty -->
                <div v-else-if="sortedEvents.length === 0" class="flex flex-col items-center gap-3 py-10 text-center">
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-muted/40">
                        <GitCommitVertical class="h-6 w-6 text-muted-foreground/40" />
                    </div>
                    <p class="text-sm text-muted-foreground">Sin movimientos registrados para esta orden.</p>
                </div>

                <template v-else>
                    <!-- Banner de novedades pendientes -->
                    <div
                        v-if="pendingCount > 0"
                        class="mb-4 flex items-center justify-between gap-3 rounded-lg border border-orange-200 bg-orange-50 px-4 py-3"
                    >
                        <div class="flex items-center gap-2">
                            <MessageSquareWarning class="h-4 w-4 shrink-0 text-orange-600" />
                            <span class="text-xs font-medium text-orange-800">
                                {{ pendingCount }} novedad{{ pendingCount > 1 ? 'es' : '' }} pendiente{{ pendingCount > 1 ? 's' : '' }} de resolución
                            </span>
                        </div>
                        <button
                            type="button"
                            class="text-xs font-semibold text-orange-600 underline underline-offset-2 hover:no-underline"
                            @click="scrollToFirstPending"
                        >
                            Ir a resolver
                        </button>
                    </div>

                    <!-- Events list -->
                    <ol class="relative space-y-0 pl-7">
                        <!-- Vertical connector line -->
                        <div class="absolute bottom-3 left-[9px] top-3 w-px bg-border/50" />

                        <li
                            v-for="(event, idx) in sortedEvents"
                            :id="event.event_type === 'novelty' ? `novelty-${event.id}` : undefined"
                            :key="`${event.event_type}-${event.id}`"
                            class="relative pb-4 last:pb-0"
                        >
                            <!-- Icon node -->
                            <span
                                :class="[
                                    'absolute -left-7 flex h-[18px] w-[18px] items-center justify-center rounded-full border-2 bg-background',
                                    event.event_type === 'delivery'
                                        ? 'border-blue-400 text-blue-500'
                                        : event.status === 'pending'
                                          ? 'border-orange-400 text-orange-500'
                                          : 'border-emerald-400 text-emerald-500',
                                ]"
                            >
                                <Send v-if="event.event_type === 'delivery'" class="h-2 w-2" />
                                <Flag v-else-if="event.status === 'pending'" class="h-2 w-2" />
                                <CheckCircle2 v-else class="h-2 w-2" />
                            </span>

                            <!-- Event card -->
                            <div
                                :class="[
                                    'rounded-lg border px-4 py-3 text-sm',
                                    event.event_type === 'delivery'
                                        ? 'border-blue-100 bg-blue-50/50'
                                        : event.status === 'pending'
                                          ? 'border-orange-200 bg-orange-50/60'
                                          : 'border-border/40 bg-muted/10',
                                ]"
                            >
                                <!-- Row: tipo + procesos + hora -->
                                <div class="mb-2 flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                                    <div class="flex items-center gap-1.5 text-xs font-semibold">
                                        <template v-if="event.event_type === 'delivery'">
                                            <span class="text-blue-700">Entrega</span>
                                            <span class="text-border">·</span>
                                            <span class="rounded bg-blue-100 px-1.5 py-0.5 font-mono font-normal text-blue-800">{{ event.from_process ?? '—' }}</span>
                                            <span class="text-muted-foreground">→</span>
                                            <span class="rounded bg-blue-600 px-1.5 py-0.5 font-mono font-normal text-white">{{ event.to_process }}</span>
                                        </template>
                                        <template v-else>
                                            <span :class="event.status === 'pending' ? 'text-orange-700' : 'text-emerald-700'">
                                                Novedad{{ event.status === 'resolved' ? ' (resuelta)' : '' }}
                                            </span>
                                            <span class="text-border">·</span>
                                            <span class="rounded bg-orange-100 px-1.5 py-0.5 font-mono font-normal text-orange-800">{{ event.from_process }}</span>
                                            <span class="text-muted-foreground">→</span>
                                            <span class="rounded bg-orange-200 px-1.5 py-0.5 font-mono font-normal text-orange-900">{{ event.to_process }}</span>
                                        </template>
                                    </div>
                                    <span class="text-[11px] tabular-nums text-muted-foreground">
                                        {{ event.event_type === 'delivery' ? event.delivered_at : event.reported_at }}
                                    </span>
                                </div>

                                <!-- Delivery content -->
                                <template v-if="event.event_type === 'delivery'">
                                    <p :class="['text-xs leading-relaxed', event.observations ? 'text-foreground' : 'italic text-muted-foreground']">
                                        {{ event.observations ?? 'Sin observaciones' }}
                                    </p>
                                    <p class="mt-1.5 text-[11px] text-muted-foreground">
                                        Entregado por: <span class="font-medium text-foreground">{{ event.delivered_by_name }}</span>
                                    </p>
                                </template>

                                <!-- Novelty content -->
                                <template v-else>
                                    <p class="text-xs leading-relaxed text-foreground">{{ event.description }}</p>
                                    <p class="mt-1.5 text-[11px] text-muted-foreground">
                                        Reportado por: <span class="font-medium text-foreground">{{ event.reported_by_name }}</span>
                                    </p>

                                    <!-- Resolved badge -->
                                    <div v-if="event.status === 'resolved'" class="mt-2.5 rounded-md border border-emerald-200 bg-emerald-50/60 px-3 py-2">
                                        <p class="flex items-center gap-1 text-[11px] font-medium text-emerald-700">
                                            <CheckCircle2 class="h-3 w-3 shrink-0" />
                                            Resuelta por <span class="font-semibold">{{ event.resolved_by_name }}</span> · {{ event.resolved_at }}
                                        </p>
                                        <p v-if="event.resolution_notes" class="mt-1 text-[11px] leading-relaxed text-emerald-800">
                                            {{ event.resolution_notes }}
                                        </p>
                                    </div>

                                    <!-- Resolve action -->
                                    <div v-if="event.status === 'pending'" class="mt-3">
                                        <template v-if="resolvingNoveltyId === event.id">
                                            <div class="space-y-2 rounded-lg border border-emerald-200 bg-emerald-50/40 p-3">
                                                <p class="text-[11px] font-medium text-emerald-700">Notas de resolución (opcional)</p>
                                                <Textarea data-no-uppercase
                                                    :model-value="resolveNotes"
                                                    placeholder="Describe cómo se resolvió el problema..."
                                                    :rows="2"
                                                    class="resize-none text-xs"
                                                    @update:model-value="emit('update:resolveNotes', String($event))"
                                                />
                                                <div class="flex gap-2">
                                                    <Button size="sm" class="h-8 gap-1.5 bg-emerald-600 px-3 text-xs hover:bg-emerald-700" @click="emit('confirmResolve', event.id)">
                                                        <CheckCircle2 class="h-3.5 w-3.5" />
                                                        Confirmar resolución
                                                    </Button>
                                                    <Button size="sm" variant="outline" class="h-8 px-3 text-xs" @click="emit('cancelResolve')">
                                                        Cancelar
                                                    </Button>
                                                </div>
                                            </div>
                                        </template>
                                        <template v-else>
                                            <Button
                                                size="sm"
                                                class="h-8 w-full gap-1.5 bg-emerald-600 text-xs hover:bg-emerald-700"
                                                @click="emit('startResolve', event.id)"
                                            >
                                                <CheckCircle2 class="h-3.5 w-3.5" />
                                                Marcar como resuelta
                                            </Button>
                                        </template>
                                    </div>
                                </template>
                            </div>

                            <!-- "Ahora" indicator after last event -->
                            <div
                                v-if="idx === sortedEvents.length - 1 && row?.current_process"
                                class="mt-3 flex items-center gap-2 text-[11px] text-muted-foreground"
                            >
                                <span class="h-px flex-1 bg-border/40" />
                                <span class="rounded-full border border-dashed border-border/50 bg-background px-2.5 py-1 font-mono">
                                    {{ row.current_process }} — ahora
                                </span>
                                <span class="h-px flex-1 bg-border/40" />
                            </div>
                        </li>
                    </ol>
                </template>
            </div>

            <!-- Footer -->
            <DialogFooter class="shrink-0 border-t border-border/50 bg-muted/20 px-6 py-4">
                <Button variant="outline" size="sm" @click="emit('close')">Cerrar</Button>
            </DialogFooter>
        </FormDialogContent>
    </Dialog>
</template>
