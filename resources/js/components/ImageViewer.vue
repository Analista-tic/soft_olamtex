<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { X, Edit3, ZoomIn } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Props {
    src?: string | null;
    alt?: string;
    maxZoom?: number;
    minZoom?: number;
    readonly?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    alt: 'Imagen',
    maxZoom: 5,
    minZoom: 1,
    readonly: false,
});

const emit = defineEmits<{
    (e: 'delete'): void;
    (e: 'update'): void;
    (e: 'open'): void;
    (e: 'close'): void;
}>();

// Modal & Display
const isModalOpen = ref(false);
const imageContainerRef = ref<HTMLElement | null>(null);
const isDragging = ref(false);

// Zoom & Position
const zoom = ref(1);
const offsetX = ref(0);
const offsetY = ref(0);
const startX = ref(0);
const startY = ref(0);
const lastX = ref(0);
const lastY = ref(0);

// Computed
const hasImage = computed(() => Boolean(props.src));

// Transform style
const imageTransformStyle = computed(() => ({
    transform: `translate(${offsetX.value}px, ${offsetY.value}px) scale(${zoom.value})`,
    transition: isDragging.value ? 'none' : 'transform 0.15s ease-out',
    cursor: zoom.value > 1 ? 'grab' : 'pointer',
}));

// Reset zoom & position
function resetZoom() {
    zoom.value = 1;
    offsetX.value = 0;
    offsetY.value = 0;
}

function handleScrollZoom(event: WheelEvent) {
    if (!imageContainerRef.value) return;
    event.preventDefault();

    const isZoomingIn = event.deltaY < 0;
    const newZoom = isZoomingIn ? zoom.value + 0.2 : zoom.value - 0.2;
    const clampedZoom = Math.max(props.minZoom, Math.min(newZoom, props.maxZoom));

    if (clampedZoom === zoom.value) return;

    const rect = imageContainerRef.value.getBoundingClientRect();
    const currentCenterX = (rect.width / 2 - offsetX.value) / zoom.value;
    const currentCenterY = (rect.height / 2 - offsetY.value) / zoom.value;

    zoom.value = clampedZoom;

    offsetX.value = rect.width / 2 - currentCenterX * zoom.value;
    offsetY.value = rect.height / 2 - currentCenterY * zoom.value;

    constrainOffset();
}

function handleDoubleClick() {
    if (zoom.value > props.minZoom) {
        resetZoom();
    } else {
        zoom.value = Math.min(3, props.maxZoom);
    }
}

// Drag handlers
function handleMouseDown(event: MouseEvent) {
    if (zoom.value <= props.minZoom) {
        openModal();
        return;
    }

    isDragging.value = true;
    startX.value = event.clientX;
    startY.value = event.clientY;
    lastX.value = offsetX.value;
    lastY.value = offsetY.value;
}

function handleMouseMove(event: MouseEvent) {
    if (!isDragging.value) return;

    const deltaX = event.clientX - startX.value;
    const deltaY = event.clientY - startY.value;

    offsetX.value = lastX.value + deltaX;
    offsetY.value = lastY.value + deltaY;

    constrainOffset();
}

function handleMouseUp() {
    isDragging.value = false;
}

function constrainOffset() {
    if (!imageContainerRef.value) return;

    const container = imageContainerRef.value;
    const rect = container.getBoundingClientRect();

    const imgWidth = rect.width / zoom.value;
    const imgHeight = rect.height / zoom.value;

    const maxX = (imgWidth * zoom.value - rect.width) / 2;
    const maxY = (imgHeight * zoom.value - rect.height) / 2;

    offsetX.value = Math.max(-maxX, Math.min(offsetX.value, maxX));
    offsetY.value = Math.max(-maxY, Math.min(offsetY.value, maxY));
}

// Modal handlers
function openModal() {
    isModalOpen.value = true;
    emit('open');
}

function closeModal() {
    isModalOpen.value = false;
    resetZoom();
    emit('close');
}

function handleDelete() {
    if (!props.readonly) {
        emit('delete');
        closeModal();
    }
}

function handleUpdate() {
    if (!props.readonly) {
        emit('update');
    }
}

// Clean up listeners
watch(
    () => isModalOpen.value,
    (isOpen) => {
        if (isOpen) {
            window.addEventListener('mousemove', handleMouseMove);
            window.addEventListener('mouseup', handleMouseUp);
        } else {
            window.removeEventListener('mousemove', handleMouseMove);
            window.removeEventListener('mouseup', handleMouseUp);
        }
    },
);
</script>

<template>
    <div v-if="hasImage" class="space-y-2">
        <!-- Thumbnail: Click para abrir, Hover para acciones -->
        <div class="group relative overflow-hidden rounded-lg border border-gray-200 bg-gray-50">
            <div
                ref="imageContainerRef"
                class="relative flex h-48 w-full cursor-pointer items-center justify-center bg-gray-100 transition-opacity hover:bg-gray-200"
                @wheel="handleScrollZoom"
                @mousedown="handleMouseDown"
                @dblclick="handleDoubleClick"
            >
                <img
                    v-if="src"
                    :src="src"
                    :alt="alt"
                    class="max-h-full max-w-full select-none object-contain"
                    :style="imageTransformStyle"
                />

                <!-- Hover overlay: solo Edit + Delete en modo no-readonly -->
                <div
                    v-if="!readonly"
                    class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center bg-black/0 opacity-0 transition-all group-hover:bg-black/30 group-hover:opacity-100"
                >
                    <div class="pointer-events-auto flex flex-col gap-2 text-white">
                        <p class="text-sm font-medium">Opciones</p>
                        <div class="flex gap-2">
                            <Button
                                size="sm"
                                variant="ghost"
                                class="text-white hover:bg-blue-600"
                                @click.stop="handleUpdate"
                                title="Cambiar imagen"
                            >
                                <Edit3 class="h-4 w-4" />
                            </Button>
                            <Button
                                size="sm"
                                variant="ghost"
                                class="text-white hover:bg-red-600"
                                @click.stop="handleDelete"
                                title="Eliminar imagen"
                            >
                                <X class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Zoom info (bottom corner, subtle) -->
                <div v-if="zoom > 1" class="absolute bottom-1 right-1 text-xs text-gray-400">
                    {{ Math.round(zoom * 100) }}%
                </div>
            </div>
        </div>

        <!-- Click para abrir hint -->
        <p class="text-xs text-gray-400">Haz click para ampliar | Scroll para zoom | Doble clic para restablecer</p>
    </div>

    <!-- Modal: Gris claro, solo zoom con scroll/dobleclick, botones simples abajo -->
    <Dialog :open="isModalOpen" @update:open="closeModal">
        <DialogContent class="max-w-5xl p-0">
            <div class="relative flex h-[85vh] w-full flex-col items-center justify-center bg-gray-100">
                <!-- Close button (top right) -->
                <Button
                    size="icon"
                    variant="ghost"
                    class="absolute right-3 top-3 z-50 rounded-lg bg-white shadow-sm hover:bg-gray-100"
                    @click="closeModal"
                    title="Cerrar (ESC)"
                >
                    <X class="h-5 w-5 text-gray-700" />
                </Button>

                <!-- Imagen: Scroll para zoom, dobleclick para reset -->
                <div
                    ref="imageContainerRef"
                    class="relative flex h-full w-full items-center justify-center overflow-hidden"
                    @wheel="handleScrollZoom"
                    @mousedown="handleMouseDown"
                    @dblclick="handleDoubleClick"
                >
                    <img
                        v-if="src"
                        :src="src"
                        :alt="alt"
                        class="select-none"
                        :style="imageTransformStyle"
                    />
                </div>

                <!-- Info: Zoom % + Instrucciones (subtle) -->
                <div class="pointer-events-none absolute bottom-3 left-3 text-xs text-gray-500 space-y-1">
                    <div>{{ Math.round(zoom * 100) }}%</div>
                    <div class="opacity-60">Scroll para zoom | Doble clic para restablecer</div>
                </div>

                <!-- Botones de acción (bottom right) -->
                <div v-if="!readonly" class="absolute bottom-3 right-3 flex gap-2">
                    <Button
                        size="sm"
                        variant="outline"
                        class="bg-white"
                        @click="handleUpdate"
                    >
                        <Edit3 class="h-4 w-4 mr-1.5" />
                        Cambiar
                    </Button>
                    <Button
                        size="sm"
                        variant="destructive"
                        @click="handleDelete"
                    >
                        <X class="h-4 w-4 mr-1.5" />
                        Eliminar
                    </Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
::-webkit-scrollbar {
    display: none;
}

* {
    -ms-overflow-style: none;
    scrollbar-width: none;
}

img {
    user-select: none;
    -webkit-user-drag: none;
}
</style>
