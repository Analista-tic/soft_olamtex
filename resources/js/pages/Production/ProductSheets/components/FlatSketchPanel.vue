/**
 * Componente: FlatSketchPanel
 *
 * Panel lateral para gestión del dibujo plano de la sábana de productos.
 * Incluye preview, carga, eliminación y zoom (via Teleport).
 */
<script setup lang="ts">
import HelpTooltip from '@/components/HelpTooltip.vue';
import Label from '@/components/ui/label/Label.vue';
import { Image as ImageIcon, Minus, Plus, Trash, Upload, X, ZoomIn } from 'lucide-vue-next';
import { ref, computed } from 'vue';

const props = defineProps<{
    visualUrl: string | null;
    canClear: boolean;
    isReadonly: boolean;
    tooltip: string;
}>();

const emit = defineEmits<{
    (e: 'change', event: Event): void;
    (e: 'clear', event?: MouseEvent): void;
    (e: 'image-error'): void;
}>();

const fileInputRef = ref<HTMLInputElement | null>(null);
const isZoomOpen = ref(false);
const zoomLevel = ref(1);

const MIN_ZOOM = 1;
const MAX_ZOOM = 4;
const ZOOM_STEP = 0.25;

const zoomPercent = computed(() => `${Math.round(zoomLevel.value * 100)}%`);

function clampZoom(v: number): number {
    return Math.min(MAX_ZOOM, Math.max(MIN_ZOOM, v));
}

function triggerFilePicker(event?: MouseEvent): void {
    event?.stopPropagation();
    if (props.isReadonly) return;
    fileInputRef.value?.click();
}

function openZoom(event?: MouseEvent): void {
    event?.stopPropagation();
    if (!props.visualUrl) return;
    zoomLevel.value = 1;
    isZoomOpen.value = true;
}

function closeZoom(event?: MouseEvent): void {
    event?.stopPropagation();
    isZoomOpen.value = false;
    zoomLevel.value = 1;
}

function zoomIn(event?: MouseEvent): void {
    event?.stopPropagation();
    zoomLevel.value = clampZoom(zoomLevel.value + ZOOM_STEP);
}

function zoomOut(event?: MouseEvent): void {
    event?.stopPropagation();
    zoomLevel.value = clampZoom(zoomLevel.value - ZOOM_STEP);
}

function handleZoomWheel(event: WheelEvent): void {
    event.preventDefault();
    const next = event.deltaY < 0 ? zoomLevel.value + ZOOM_STEP : zoomLevel.value - ZOOM_STEP;
    zoomLevel.value = clampZoom(next);
}
</script>

<template>
    <div class="w-full min-w-0 sm:w-[320px]">
        <div class="space-y-4">
            <div class="flex items-center gap-2">
                <Label class="text-sm font-medium">Dibujo plano</Label>
                <HelpTooltip :text="tooltip" aria-label="Ayuda sobre Dibujo plano" />
            </div>

            <!-- Input oculto real -->
            <input
                v-if="!isReadonly"
                ref="fileInputRef"
                id="flat-sketch-panel-input"
                name="flat_sketch"
                type="file"
                accept="image/jpeg,image/png,image/webp"
                class="sr-only"
                @change="emit('change', $event)"
            />

            <div class="relative overflow-hidden rounded-xl border border-border/80 bg-muted/20">
                <div
                    class="group relative h-[clamp(280px,40vh,500px)] w-full overflow-hidden"
                    :class="!isReadonly && !visualUrl ? 'cursor-pointer' : ''"
                    @click="!visualUrl && !isReadonly ? triggerFilePicker() : undefined"
                >
                    <!-- Imagen cargada -->
                    <template v-if="visualUrl">
                        <img
                            :src="visualUrl"
                            alt="Previsualización dibujo plano"
                            class="h-full w-full object-contain"
                            @error="emit('image-error')"
                        />

                        <!-- Overlay hover -->
                        <div class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center opacity-0 transition-opacity duration-200 group-hover:opacity-100">
                            <div class="pointer-events-auto flex items-center gap-2">
                                <button
                                    v-if="!isReadonly"
                                    type="button"
                                    class="inline-flex items-center gap-1.5 rounded-full bg-background/90 px-3 py-1.5 text-xs font-medium text-foreground shadow ring-1 ring-border/60 transition hover:bg-background"
                                    title="Cambiar imagen"
                                    @click.stop="triggerFilePicker"
                                >
                                    <Upload class="h-3.5 w-3.5" />
                                    Cambiar
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 rounded-full bg-background/90 px-3 py-1.5 text-xs font-medium text-foreground shadow ring-1 ring-border/60 transition hover:bg-background"
                                    title="Ver en zoom"
                                    @click.stop="openZoom"
                                >
                                    <ZoomIn class="h-3.5 w-3.5" />
                                    Zoom
                                </button>
                            </div>
                        </div>

                        <!-- Botón eliminar pequeño -->
                        <button
                            v-if="!isReadonly && canClear"
                            type="button"
                            class="absolute right-2 top-2 z-20 inline-flex h-6 w-6 items-center justify-center rounded-full bg-background/80 text-muted-foreground shadow ring-1 ring-border/50 transition hover:bg-destructive/10 hover:text-destructive"
                            title="Quitar imagen"
                            @click.stop="emit('clear', $event)"
                        >
                            <Trash class="h-3 w-3" />
                        </button>
                    </template>

                    <!-- Estado vacío -->
                    <template v-else>
                        <div class="flex h-full w-full flex-col items-center justify-center gap-2 text-muted-foreground">
                            <ImageIcon class="h-10 w-10 opacity-40" />
                            <div class="text-center">
                                <p class="text-sm font-medium">{{ isReadonly ? 'Sin dibujo plano' : 'Cargar dibujo plano' }}</p>
                                <p v-if="!isReadonly" class="text-xs opacity-70">JPG, PNG o WEBP</p>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <!-- Visor de zoom fuera del dialog -->
    <Teleport to="body">
        <div
            v-if="isZoomOpen && visualUrl"
            class="fixed inset-0 z-[99999] flex items-center justify-center bg-black/75 p-4"
            @click="closeZoom"
        >
            <div
                class="relative flex max-h-[95vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl border bg-background shadow-2xl"
                @click.stop
            >
                <div class="flex shrink-0 items-center justify-between border-b px-4 py-2">
                    <span class="text-xs font-medium text-muted-foreground">{{ zoomPercent }}</span>
                    <div class="flex items-center gap-1">
                        <button
                            type="button"
                            class="inline-flex h-7 w-7 items-center justify-center rounded-full border border-border/70 text-foreground transition hover:bg-muted disabled:opacity-40"
                            title="Alejar"
                            :disabled="zoomLevel <= MIN_ZOOM"
                            @click="zoomOut"
                        >
                            <Minus class="h-3.5 w-3.5" />
                        </button>
                        <button
                            type="button"
                            class="inline-flex h-7 w-7 items-center justify-center rounded-full border border-border/70 text-foreground transition hover:bg-muted disabled:opacity-40"
                            title="Acercar"
                            :disabled="zoomLevel >= MAX_ZOOM"
                            @click="zoomIn"
                        >
                            <Plus class="h-3.5 w-3.5" />
                        </button>
                        <button
                            type="button"
                            class="ml-2 inline-flex h-7 w-7 items-center justify-center rounded-full border border-border/70 text-foreground transition hover:bg-muted"
                            title="Cerrar"
                            @click="closeZoom"
                        >
                            <X class="h-3.5 w-3.5" />
                        </button>
                    </div>
                </div>
                <div class="flex-1 overflow-auto bg-muted/30 p-3" style="cursor: zoom-in" @wheel.prevent="handleZoomWheel">
                    <img
                        :src="visualUrl"
                        alt="Dibujo plano"
                        class="mx-auto block max-w-full select-none transition-transform duration-100"
                        :style="{ transform: `scale(${zoomLevel})`, transformOrigin: 'center top' }"
                        draggable="false"
                    />
                </div>
            </div>
        </div>
    </Teleport>
</template>
