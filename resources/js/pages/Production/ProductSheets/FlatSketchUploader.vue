<script setup lang="ts">
import HelpTooltip from '@/components/HelpTooltip.vue';
import Label from '@/components/ui/label/Label.vue';
import { Image as ImageIcon, Maximize2, Minimize2, Trash, Upload, X } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps<{
    modelValue: string;
    readonly?: boolean;
    tooltip: string;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
    (e: 'error', message: string): void;
}>();

const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

// ─── Constantes de lupa ───────────────────────────────────────────────────────
const MAGNIFIER_SIZE = 160; // px – diámetro del círculo de lupa
const MAGNIFIER_ZOOM = 2.8; // factor de amplificación

// ─── Estado de archivo ───────────────────────────────────────────────────────
const file = ref<File | null>(null);
const previewUrl = ref<string | null>(null);
const loadFailed = ref(false);
const fileInputRef = ref<HTMLInputElement | null>(null);
const isDragging = ref(false);

// ─── Estado lupa en miniatura ─────────────────────────────────────────────────
const isMagnifying = ref(false);
const magPos = ref({ x: 0, y: 0, containerW: 1, containerH: 1 });

// ─── Estado visor modal ───────────────────────────────────────────────────────
const isZoomOpen = ref(false);
const isFullscreen = ref(false);
const zoomLevel = ref(1);
const zoomOriginX = ref(50); // % dentro del contenedor del modal
const zoomOriginY = ref(50);

const MIN_ZOOM = 1;
const MAX_ZOOM = 5;
const ZOOM_STEP = 0.5;

function resolveStorageUrl(rawPath: string): string | null {
    const path = rawPath.trim();
    if (!path) return null;
    if (/^https?:\/\//i.test(path)) return path;
    if (path.startsWith('/storage-file/')) return path;
    if (path.startsWith('/')) return `/storage-file/${path.replace(/^\/+(?:storage\/)?/, '')}`;
    return `/storage-file/${path.replace(/^\/?storage\//, '')}`;
}

const visualUrl = computed<string | null>(() => {
    if (previewUrl.value) return previewUrl.value;
    if (loadFailed.value) return null;
    return resolveStorageUrl(props.modelValue);
});

const canClear = computed(() => Boolean(file.value || props.modelValue));
const zoomPercent = computed(() => `${Math.round(zoomLevel.value * 100)}%`);
const isZoomed = computed(() => zoomLevel.value > MIN_ZOOM);

// ─── Lupa en miniatura ────────────────────────────────────────────────────────
function handleMagMove(event: MouseEvent): void {
    if (!visualUrl.value || isZoomOpen.value) return;
    const rect = (event.currentTarget as HTMLElement).getBoundingClientRect();
    magPos.value = {
        x: event.clientX - rect.left,
        y: event.clientY - rect.top,
        containerW: rect.width,
        containerH: rect.height,
    };
    isMagnifying.value = true;
}

function handleMagLeave(): void {
    isMagnifying.value = false;
}

// ─── Hover-to-pan en modal ────────────────────────────────────────────────────
function handleModalMouseMove(event: MouseEvent): void {
    const rect = (event.currentTarget as HTMLElement).getBoundingClientRect();
    zoomOriginX.value = ((event.clientX - rect.left) / rect.width) * 100;
    zoomOriginY.value = ((event.clientY - rect.top) / rect.height) * 100;
}

// ─── Zoom rueda ───────────────────────────────────────────────────────────────
function clampZoom(value: number): number {
    return Math.min(MAX_ZOOM, Math.max(MIN_ZOOM, value));
}

function handleZoomWheel(event: WheelEvent): void {
    event.preventDefault();
    const delta = event.deltaY < 0 ? ZOOM_STEP * 0.6 : -ZOOM_STEP * 0.6;
    zoomLevel.value = clampZoom(zoomLevel.value + delta);
}

function openZoom(event?: MouseEvent): void {
    event?.stopPropagation();
    if (!visualUrl.value) return;
    zoomLevel.value = 1;
    zoomOriginX.value = 50;
    zoomOriginY.value = 50;
    isZoomOpen.value = true;
    isMagnifying.value = false;
}

function closeZoom(event?: MouseEvent): void {
    event?.stopPropagation();
    isZoomOpen.value = false;
    isFullscreen.value = false;
    zoomLevel.value = 1;
}

function toggleFullscreen(): void {
    isFullscreen.value = !isFullscreen.value;
}

// Doble clic en modal: alterna zoom rápido anclado al cursor
function handleModalDblClick(event: MouseEvent): void {
    const rect = (event.currentTarget as HTMLElement).getBoundingClientRect();
    zoomOriginX.value = ((event.clientX - rect.left) / rect.width) * 100;
    zoomOriginY.value = ((event.clientY - rect.top) / rect.height) * 100;
    zoomLevel.value = isZoomed.value ? 1 : 2.5;
}

// ─── Archivo ──────────────────────────────────────────────────────────────────
function revokePreview(): void {
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
        previewUrl.value = null;
    }
}

function reset(): void {
    revokePreview();
    file.value = null;
    loadFailed.value = false;
    isZoomOpen.value = false;
    zoomLevel.value = 1;
    isDragging.value = false;
    isMagnifying.value = false;
    if (fileInputRef.value) fileInputRef.value.value = '';
}

function inferExtension(mimeType: string): string {
    const m = mimeType.toLowerCase();
    if (m.includes('png')) return 'png';
    if (m.includes('webp')) return 'webp';
    return 'jpg';
}

async function getFileForUpload(): Promise<File | null> {
    if (file.value instanceof File) return file.value;
    const url = previewUrl.value ?? '';
    if (!url.startsWith('blob:')) return null;
    try {
        const res = await fetch(url);
        const blob = await res.blob();
        if (!blob?.type.startsWith('image/')) return null;
        return new File([blob], `flat-sketch.${inferExtension(blob.type)}`, { type: blob.type });
    } catch {
        return null;
    }
}

function processFile(selected: File | null | undefined): void {
    if (!selected) return;
    if (!ALLOWED_MIME_TYPES.includes(selected.type)) {
        emit('error', 'Formato no válido. Solo JPG, PNG o WEBP.');
        return;
    }
    revokePreview();
    loadFailed.value = false;
    file.value = selected;
    previewUrl.value = URL.createObjectURL(selected);
}

function handleChange(event: Event): void {
    const input = event.target as HTMLInputElement;
    processFile(input?.files?.[0]);
}

function handleDragOver(event: DragEvent): void {
    if (props.readonly) return;
    event.preventDefault();
    isDragging.value = true;
}

function handleDragLeave(): void {
    isDragging.value = false;
}

function handleDrop(event: DragEvent): void {
    if (props.readonly) return;
    event.preventDefault();
    isDragging.value = false;
    processFile(event.dataTransfer?.files?.[0]);
}

function triggerFilePicker(event?: MouseEvent): void {
    event?.stopPropagation();
    if (props.readonly) return;
    fileInputRef.value?.click();
}

function clearImage(event?: MouseEvent): void {
    event?.stopPropagation();
    reset();
    emit('update:modelValue', '');
}

function handleImageError(): void {
    if (!previewUrl.value) loadFailed.value = true;
}

// ─── Teclado global ───────────────────────────────────────────────────────────
function handleKeydown(event: KeyboardEvent): void {
    if (!isZoomOpen.value) return;
    if (event.key === 'Escape') { if (isFullscreen.value) { isFullscreen.value = false; } else { closeZoom(); } }
    if (event.key === '+' || event.key === '=') zoomLevel.value = clampZoom(zoomLevel.value + ZOOM_STEP);
    if (event.key === '-') zoomLevel.value = clampZoom(zoomLevel.value - ZOOM_STEP);
    if (event.key === '0') zoomLevel.value = 1;
    if (event.key === 'f' || event.key === 'F') toggleFullscreen();
}

onMounted(() => window.addEventListener('keydown', handleKeydown));
onUnmounted(() => window.removeEventListener('keydown', handleKeydown));

defineExpose({ reset, getFileForUpload });
</script>

<template>
    <div class="space-y-2">
        <div class="flex items-center gap-2">
            <Label class="text-sm font-medium">Dibujo plano</Label>
            <HelpTooltip :text="tooltip" aria-label="Ayuda sobre Dibujo plano" />
        </div>

        <!-- Input oculto -->
        <input
            v-if="!readonly"
            ref="fileInputRef"
            id="flat-sketch-input"
            name="flat_sketch"
            type="file"
            accept="image/jpeg,image/png,image/webp"
            class="sr-only"
            @change="handleChange"
        />

        <!-- Contenedor principal -->
        <div
            class="group relative overflow-hidden rounded-xl border-2 transition-colors duration-200"
            :class="[
                isDragging
                    ? 'border-dashed border-primary bg-primary/5'
                    : visualUrl
                      ? 'border-border/60 bg-muted/10'
                      : !readonly
                        ? 'cursor-pointer border-dashed border-border bg-muted/10 hover:border-primary/50 hover:bg-muted/30'
                        : 'border-border/40 bg-muted/10',
            ]"
            @dragover="handleDragOver"
            @dragleave="handleDragLeave"
            @drop="handleDrop"
            @click="!visualUrl && !readonly ? triggerFilePicker() : undefined"
        >
            <div class="relative h-[clamp(260px,38vh,480px)] w-full select-none">

                <!-- ── Imagen cargada ── -->
                <template v-if="visualUrl">
                    <!-- Imagen base: clic abre el visor completo, hover activa la lupa -->
                    <img
                        :src="visualUrl"
                        alt="Previsualización dibujo plano"
                        class="h-full w-full object-contain"
                        :style="{ cursor: isMagnifying ? 'none' : 'zoom-in' }"
                        draggable="false"
                        @error="handleImageError"
                        @click.stop="openZoom"
                        @mousemove="handleMagMove"
                        @mouseleave="handleMagLeave"
                    />

                    <!-- Lupa flotante: sigue al cursor con imagen amplificada -->
                    <div
                        v-if="isMagnifying"
                        class="pointer-events-none absolute z-30 overflow-hidden rounded-full border-2 border-white/80 shadow-2xl ring-2 ring-black/20"
                        :style="{
                            width: `${MAGNIFIER_SIZE}px`,
                            height: `${MAGNIFIER_SIZE}px`,
                            left: `${magPos.x - MAGNIFIER_SIZE / 2}px`,
                            top: `${magPos.y - MAGNIFIER_SIZE / 2}px`,
                        }"
                    >
                        <!--
                            Técnica: misma imagen a escala MAGNIFIER_ZOOM con transformOrigin
                            en las coordenadas del cursor dentro del contenedor.
                            El wrapper se desplaza -offset para que el cursor quede centrado en la lupa.
                        -->
                        <div
                            class="absolute"
                            :style="{
                                width: `${magPos.containerW}px`,
                                height: `${magPos.containerH}px`,
                                left: `${MAGNIFIER_SIZE / 2 - magPos.x}px`,
                                top: `${MAGNIFIER_SIZE / 2 - magPos.y}px`,
                                transform: `scale(${MAGNIFIER_ZOOM})`,
                                transformOrigin: `${magPos.x}px ${magPos.y}px`,
                            }"
                        >
                            <img :src="visualUrl" class="h-full w-full object-contain" draggable="false" alt="" />
                        </div>
                        <!-- Retícula de referencia en la lupa -->
                        <div class="pointer-events-none absolute inset-0 opacity-25">
                            <div class="absolute left-1/2 h-full w-px -translate-x-1/2 bg-white" />
                            <div class="absolute top-1/2 h-px w-full -translate-y-1/2 bg-white" />
                        </div>
                    </div>

                    <!-- Pill con acción "Cambiar" (solo en hover, no interfiere con lupa) -->
                    <div
                        v-if="!readonly"
                        class="pointer-events-none absolute inset-x-0 bottom-3 z-20 flex justify-center opacity-0 transition-opacity duration-200 group-hover:opacity-100"
                    >
                        <div class="pointer-events-auto flex items-center rounded-full border border-border/40 bg-background/95 px-2 py-1 shadow-lg backdrop-blur-sm">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-medium text-foreground transition hover:bg-muted"
                                title="Cambiar imagen"
                                @click.stop="triggerFilePicker"
                            >
                                <Upload class="h-3 w-3" />
                                Cambiar
                            </button>
                        </div>
                    </div>

                    <!-- Botón quitar: esquina superior derecha -->
                    <button
                        v-if="!readonly && canClear"
                        type="button"
                        class="absolute right-2 top-2 z-20 inline-flex h-6 w-6 items-center justify-center rounded-full bg-background/80 text-muted-foreground/60 opacity-0 shadow backdrop-blur-sm transition-all duration-200 group-hover:opacity-100 hover:!bg-destructive/15 hover:!text-destructive"
                        title="Quitar imagen"
                        @click.stop="clearImage"
                    >
                        <Trash class="h-3 w-3" />
                    </button>
                </template>

                <!-- ── Estado vacío ── -->
                <template v-else>
                    <div
                        class="flex h-full w-full flex-col items-center justify-center gap-3 px-4 text-center"
                        :class="isDragging ? 'text-primary' : 'text-muted-foreground'"
                    >
                        <div
                            class="flex h-14 w-14 items-center justify-center rounded-2xl border-2 border-dashed transition-colors"
                            :class="isDragging ? 'border-primary bg-primary/10' : 'border-border/50 bg-muted/30'"
                        >
                            <Upload v-if="isDragging" class="h-6 w-6 text-primary" />
                            <ImageIcon v-else class="h-6 w-6 opacity-50" />
                        </div>
                        <div>
                            <p class="text-sm font-medium">
                                {{ isDragging ? 'Suelta para cargar' : readonly ? 'Sin dibujo plano' : 'Cargar dibujo plano' }}
                            </p>
                            <p v-if="!readonly && !isDragging" class="mt-0.5 text-xs opacity-60">
                                Haz clic o arrastra una imagen · JPG, PNG, WEBP
                            </p>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- ── Visor de zoom ── se renderiza en body, por encima de cualquier dialog ── -->
    <Teleport to="body">
        <Transition name="zoom-fade">
            <div
                v-if="isZoomOpen && visualUrl"
                class="fixed inset-0 z-[99999] flex items-center justify-center bg-black/85 p-4 backdrop-blur-sm"
                @click="closeZoom"
            >
                <div
                    class="relative flex w-full flex-col overflow-hidden bg-background shadow-2xl ring-1 ring-white/10 transition-all duration-300"
                    :class="isFullscreen
                        ? 'fixed inset-3 z-10 rounded-2xl'
                        : 'max-h-[92vh] max-w-4xl rounded-2xl'"
                    @click.stop
                >
                    <!-- Cabecera mínima: título + badge zoom + fullscreen + cerrar -->
                    <div class="flex shrink-0 items-center justify-between border-b bg-background/95 px-4 py-2">
                        <span class="text-xs font-semibold text-foreground/60">Dibujo plano</span>
                        <div class="flex items-center gap-1.5">
                            <span
                                v-if="isZoomed"
                                class="cursor-pointer rounded border border-border/50 px-2 py-0.5 font-mono text-[11px] text-foreground/70 transition hover:bg-muted"
                                title="Restablecer zoom (tecla 0)"
                                @click="zoomLevel = 1"
                            >
                                {{ zoomPercent }}
                            </span>
                            <!-- Toggle pantalla completa -->
                            <button
                                type="button"
                                class="inline-flex h-7 w-7 items-center justify-center rounded-full text-foreground/50 transition hover:bg-muted hover:text-foreground"
                                :title="isFullscreen ? 'Ventana normal (F / Esc)' : 'Pantalla completa (F)'"
                                @click="toggleFullscreen"
                            >
                                <Minimize2 v-if="isFullscreen" class="h-4 w-4" />
                                <Maximize2 v-else class="h-4 w-4" />
                            </button>
                            <button
                                type="button"
                                class="inline-flex h-7 w-7 items-center justify-center rounded-full text-foreground/50 transition hover:bg-muted hover:text-foreground"
                                title="Cerrar (Esc)"
                                @click="closeZoom"
                            >
                                <X class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <!--
                        Área de imagen:
                        · Mover el cursor cambia el transformOrigin → la zona bajo el cursor
                          permanece visible al ampliar (navegación sin scroll, sin botones).
                        · Rueda del mouse controla el nivel de zoom.
                        · Doble clic: alterna entre 1× y 2,5× anclado al cursor.
                    -->
                    <div
                        class="relative flex-1 overflow-hidden bg-[#111]"
                        :style="{ cursor: isZoomed ? 'crosshair' : 'zoom-in', minHeight: isFullscreen ? '0' : '400px' }"
                        @mousemove="handleModalMouseMove"
                        @wheel.prevent="handleZoomWheel"
                        @dblclick="handleModalDblClick"
                        @click.stop
                    >
                        <img
                            :src="visualUrl"
                            alt="Dibujo plano – visor zoom"
                            class="block h-full w-full select-none object-contain transition-transform duration-100 ease-out"
                            :style="{
                                transform: `scale(${zoomLevel})`,
                                transformOrigin: `${zoomOriginX}% ${zoomOriginY}%`,
                            }"
                            draggable="false"
                        />
                    </div>

                    <!-- Pie: instrucciones breves -->
                    <div class="flex shrink-0 items-center justify-center border-t bg-background/95 py-1.5">
                        <span class="text-[10px] text-muted-foreground/50">
                            Rueda para zoom · Mueve el cursor para navegar · Doble clic para alternar ·
                            <kbd class="rounded bg-muted px-1 font-mono">F</kbd>&nbsp;pantalla completa ·
                            <kbd class="rounded bg-muted px-1 font-mono">Esc</kbd>&nbsp;cerrar
                        </span>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.zoom-fade-enter-active,
.zoom-fade-leave-active {
    transition: opacity 0.18s ease, transform 0.18s ease;
}
.zoom-fade-enter-from,
.zoom-fade-leave-to {
    opacity: 0;
    transform: scale(0.97);
}
</style>
