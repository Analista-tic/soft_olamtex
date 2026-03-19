<script setup lang="ts">
import FormDialogContent from '@/components/FormDialogContent.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import axios from 'axios';
import { Loader2, Minus, Plus, RotateCcw } from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps<{
    modelValue: boolean;
    imageFile: File | null;
    hasCurrentAvatar?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: boolean): void;
    (e: 'success', message: string, avatarUrl: string, avatarPath?: string | null): void;
    (e: 'error', message: string): void;
    (e: 'delete'): void;
}>();

const isOpen = computed({
    get: () => props.modelValue,
    set: (value: boolean) => emit('update:modelValue', value),
});

const cropContainerRef = ref<HTMLDivElement | null>(null);
const canvasRef = ref<HTMLCanvasElement | null>(null);
const imageElement = ref<HTMLImageElement | null>(null);
const localImageUrl = ref<string | null>(null);

const isReady = ref(false);
const isSaving = ref(false);
const isDragging = ref(false);

const viewportSize = ref(320);
const zoom = ref(1);
const offsetX = ref(0);
const offsetY = ref(0);

const MIN_ZOOM = 1;
const MAX_ZOOM = 3;
const SLIDER_MIN = 0;
const SLIDER_MAX = 100;
const SLIDER_STEP = 0.1;
const ZOOM_STEP_PERCENT = 4;
const ZOOM_EPSILON = 0.001;
const OUTPUT_SIZE = 512;

let dragStartX = 0;
let dragStartY = 0;
let dragOffsetX = 0;
let dragOffsetY = 0;

const zoomPercent = computed(() => Math.round(zoom.value * 100));
const zoomRatio = MAX_ZOOM / MIN_ZOOM;

const clamp = (value: number, min: number, max: number) => Math.min(Math.max(value, min), max);

const zoomFromSlider = (sliderValue: number) => {
    const normalized = clamp(sliderValue, SLIDER_MIN, SLIDER_MAX) / SLIDER_MAX;
    return MIN_ZOOM * zoomRatio ** normalized;
};

const sliderFromZoom = (zoomValue: number) => {
    if (zoomRatio <= 1) {
        return SLIDER_MIN;
    }

    const safeZoom = clamp(zoomValue, MIN_ZOOM, MAX_ZOOM);
    return (Math.log(safeZoom / MIN_ZOOM) / Math.log(zoomRatio)) * SLIDER_MAX;
};

const sliderValue = computed(() => sliderFromZoom(zoom.value));
const canZoomIn = computed(() => zoom.value < MAX_ZOOM - ZOOM_EPSILON);
const canZoomOut = computed(() => zoom.value > MIN_ZOOM + ZOOM_EPSILON);

const getBaseScale = () => {
    if (!imageElement.value) {
        return 1;
    }

    return Math.max(viewportSize.value / imageElement.value.width, viewportSize.value / imageElement.value.height);
};

const getRenderDimensions = (zoomValue = zoom.value) => {
    if (!imageElement.value) {
        return { width: viewportSize.value, height: viewportSize.value };
    }

    const scale = getBaseScale() * zoomValue;

    return {
        width: imageElement.value.width * scale,
        height: imageElement.value.height * scale,
    };
};

const clampOffsets = (zoomValue = zoom.value) => {
    const dimensions = getRenderDimensions(zoomValue);

    const maxX = Math.max(0, (dimensions.width - viewportSize.value) / 2);
    const maxY = Math.max(0, (dimensions.height - viewportSize.value) / 2);

    offsetX.value = clamp(offsetX.value, -maxX, maxX);
    offsetY.value = clamp(offsetY.value, -maxY, maxY);
};

const updateViewportSize = () => {
    viewportSize.value = cropContainerRef.value?.clientWidth ?? 320;
    clampOffsets();
};

const setZoom = (nextZoom: number) => {
    zoom.value = clamp(nextZoom, MIN_ZOOM, MAX_ZOOM);
    clampOffsets(zoom.value);
};

const resetTransform = () => {
    zoom.value = MIN_ZOOM;
    offsetX.value = 0;
    offsetY.value = 0;
    clampOffsets();
};

const cleanupImage = () => {
    if (localImageUrl.value) {
        URL.revokeObjectURL(localImageUrl.value);
        localImageUrl.value = null;
    }

    imageElement.value = null;
    isReady.value = false;
    isDragging.value = false;
};

const closeDialog = () => {
    isOpen.value = false;
};

const loadImage = async (file: File) => {
    cleanupImage();

    localImageUrl.value = URL.createObjectURL(file);

    const image = new Image();
    const loadedImage = await new Promise<HTMLImageElement>((resolve, reject) => {
        image.onload = () => resolve(image);
        image.onerror = () => reject(new Error('No se pudo cargar la imagen.'));
        image.src = localImageUrl.value as string;
    });

    imageElement.value = loadedImage;
    isReady.value = true;

    await nextTick();
    requestAnimationFrame(() => {
        updateViewportSize();
        resetTransform();
    });
};

watch(
    () => props.modelValue,
    async (open) => {
        if (open && props.imageFile) {
            try {
                await loadImage(props.imageFile);
            } catch {
                emit('error', 'No se pudo cargar la imagen seleccionada.');
                closeDialog();
            }
            return;
        }

        if (!open) {
            cleanupImage();
        }
    },
);

watch(zoom, () => {
    clampOffsets();
});

const handlePointerDown = (event: PointerEvent) => {
    if (!isReady.value) {
        return;
    }

    event.preventDefault();

    isDragging.value = true;
    dragStartX = event.clientX;
    dragStartY = event.clientY;
    dragOffsetX = offsetX.value;
    dragOffsetY = offsetY.value;
};

const handlePointerMove = (event: PointerEvent) => {
    if (!isDragging.value) {
        return;
    }

    offsetX.value = dragOffsetX + (event.clientX - dragStartX);
    offsetY.value = dragOffsetY + (event.clientY - dragStartY);
    clampOffsets();
};

const handlePointerUp = () => {
    isDragging.value = false;
};

const handleWheel = (event: WheelEvent) => {
    event.preventDefault();

    const delta = event.deltaY < 0 ? ZOOM_STEP_PERCENT : -ZOOM_STEP_PERCENT;
    const nextSlider = sliderFromZoom(zoom.value) + delta;

    setZoom(zoomFromSlider(nextSlider));
};

const handleSliderChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const nextSlider = Number.parseFloat(target.value);

    if (Number.isNaN(nextSlider)) {
        return;
    }

    setZoom(zoomFromSlider(nextSlider));
};

const zoomIn = () => {
    setZoom(zoomFromSlider(sliderFromZoom(zoom.value) + ZOOM_STEP_PERCENT));
};

const zoomOut = () => {
    setZoom(zoomFromSlider(sliderFromZoom(zoom.value) - ZOOM_STEP_PERCENT));
};

const baseDimensions = computed(() => getRenderDimensions(1));

const panLayerStyle = computed(() => ({
    width: `${baseDimensions.value.width}px`,
    height: `${baseDimensions.value.height}px`,
    transform: `translate(-50%, -50%) translate(${offsetX.value}px, ${offsetY.value}px)`,
    willChange: 'transform',
    cursor: isDragging.value ? 'grabbing' : 'grab',
}));

const zoomLayerStyle = computed(() => ({
    transform: `scale(${zoom.value})`,
    transformOrigin: 'center center',
    willChange: 'transform',
}));

const buildCroppedBlob = async (): Promise<Blob | null> => {
    if (!canvasRef.value || !imageElement.value) {
        return null;
    }

    const canvas = canvasRef.value;
    const context = canvas.getContext('2d');

    if (!context) {
        return null;
    }

    const dimensions = getRenderDimensions();
    const left = (viewportSize.value - dimensions.width) / 2 + offsetX.value;
    const top = (viewportSize.value - dimensions.height) / 2 + offsetY.value;
    const ratio = OUTPUT_SIZE / viewportSize.value;

    canvas.width = OUTPUT_SIZE;
    canvas.height = OUTPUT_SIZE;

    context.clearRect(0, 0, OUTPUT_SIZE, OUTPUT_SIZE);
    context.save();
    context.beginPath();
    context.arc(OUTPUT_SIZE / 2, OUTPUT_SIZE / 2, OUTPUT_SIZE / 2, 0, Math.PI * 2);
    context.closePath();
    context.clip();
    context.drawImage(imageElement.value, left * ratio, top * ratio, dimensions.width * ratio, dimensions.height * ratio);
    context.restore();

    return await new Promise<Blob | null>((resolve) => {
        canvas.toBlob((blob) => resolve(blob), 'image/png', 0.95);
    });
};

const saveAvatar = async () => {
    const croppedBlob = await buildCroppedBlob();

    if (!croppedBlob) {
        emit('error', 'No se pudo procesar la imagen.');
        return;
    }

    isSaving.value = true;

    try {
        const formData = new FormData();
        formData.append('avatar', new File([croppedBlob], `avatar_${Date.now()}.png`, { type: 'image/png' }));
        const response = await axios.post(route('profile.avatar.upload'), formData);

        emit(
            'success',
            response.data.message ?? 'Avatar actualizado correctamente.',
            response.data.avatar_url,
            response.data.avatar_path ?? null,
        );
        closeDialog();
    } catch (error) {
        if (axios.isAxiosError(error)) {
            const backendMessage = error.response?.data?.message as string | undefined;
            const avatarValidation = error.response?.data?.errors?.avatar?.[0] as string | undefined;
            if (backendMessage || avatarValidation) {
                emit('error', backendMessage ?? avatarValidation ?? 'No se pudo guardar el avatar.');
                return;
            }
            emit('error', 'No se pudo guardar el avatar.');
        } else {
            emit('error', 'No se pudo guardar el avatar.');
        }
    } finally {
        isSaving.value = false;
    }
};

onMounted(() => {
    window.addEventListener('pointermove', handlePointerMove);
    window.addEventListener('pointerup', handlePointerUp);
    window.addEventListener('resize', updateViewportSize);
});

onUnmounted(() => {
    cleanupImage();
    window.removeEventListener('pointermove', handlePointerMove);
    window.removeEventListener('pointerup', handlePointerUp);
    window.removeEventListener('resize', updateViewportSize);
});
</script>

<template>
    <Dialog v-model:open="isOpen">
        <FormDialogContent class="flex flex-col max-h-[95vh] max-w-2xl px-2 py-2">
            <div class="space-y-5">
                <DialogHeader class="space-y-1 border-b border-sidebar-border/70 bg-muted/20 px-5 py-4 dark:border-sidebar-border">
                    <DialogTitle>Ajustar foto de perfil</DialogTitle>
                    <p class="text-sm text-muted-foreground">
                        Arrastra la imagen para reposicionar y usa el zoom para encuadrar.
                    </p>
                </DialogHeader>

                <div class="space-y-4">
                    <div class="flex justify-center">
                        <div
                            ref="cropContainerRef"
                            class="relative h-80 w-80 max-w-full overflow-hidden rounded-full border border-border bg-muted"
                            @pointerdown="handlePointerDown"
                            @wheel.prevent="handleWheel"
                        >
                            <template v-if="isReady && localImageUrl">
                                <div class="absolute left-1/2 top-1/2" :style="panLayerStyle">
                                    <img
                                        :src="localImageUrl"
                                        alt="Vista previa de avatar"
                                        class="block h-full w-full select-none"
                                        :style="zoomLayerStyle"
                                        draggable="false"
                                    />
                                </div>
                            </template>

                            <div v-else class="flex h-full items-center justify-center">
                                <Loader2 class="h-7 w-7 animate-spin text-muted-foreground" />
                            </div>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <div class="flex items-center justify-between text-sm text-muted-foreground">
                            <span>Zoom</span>
                            <span>{{ zoomPercent }}%</span>
                        </div>

                        <div class="flex items-center gap-3">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="h-8 w-8 rounded-full"
                                :disabled="!canZoomOut"
                                @click="zoomOut"
                            >
                                <Minus class="h-4 w-4" />
                            </Button>

                            <input
                                type="range"
                                :min="SLIDER_MIN"
                                :max="SLIDER_MAX"
                                :step="SLIDER_STEP"
                                :value="sliderValue"
                                class="h-2 w-full cursor-pointer appearance-none rounded-full bg-muted accent-primary"
                                @input="handleSliderChange"
                            />

                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="h-8 w-8 rounded-full"
                                :disabled="!canZoomIn"
                                @click="zoomIn"
                            >
                                <Plus class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </div>

                <DialogFooter class="items-center justify-between sm:justify-between">
                    <div class="flex items-center gap-2">
                        <Button type="button" variant="ghost" class="gap-2" @click="resetTransform" :disabled="isSaving">
                            <RotateCcw class="h-4 w-4" />
                            Reset
                        </Button>
                        <Button
                            v-if="props.hasCurrentAvatar"
                            type="button"
                            variant="ghost"
                            class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                            :disabled="isSaving"
                            @click="
                                emit('delete');
                                closeDialog();
                            "
                        >
                            Eliminar foto
                        </Button>
                    </div>

                    <div class="flex items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="pill"
                            class="border-primary/40 bg-background text-primary hover:bg-primary/10 hover:text-primary"
                            @click="closeDialog"
                            :disabled="isSaving"
                        >
                            Cancelar
                        </Button>
                        <Button type="button" size="pill" class="gap-2" @click="saveAvatar" :disabled="!isReady || isSaving">
                            <Loader2 v-if="isSaving" class="h-4 w-4 animate-spin" />
                            {{ isSaving ? 'Guardando...' : 'Guardar' }}
                        </Button>
                    </div>
                </DialogFooter>
            </div>

            <canvas ref="canvasRef" class="hidden" />
        </FormDialogContent>
    </Dialog>
</template>
