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
const ZOOM_STEP = 0.05;
const OUTPUT_SIZE = 512;

let dragStartX = 0;
let dragStartY = 0;
let dragOffsetX = 0;
let dragOffsetY = 0;

const zoomPercent = computed(() => Math.round(zoom.value * 100));

const clamp = (value: number, min: number, max: number) => Math.min(Math.max(value, min), max);

const getBaseScale = () => {
    if (!imageElement.value) {
        return 1;
    }

    return Math.max(viewportSize.value / imageElement.value.width, viewportSize.value / imageElement.value.height);
};

const getRenderDimensions = () => {
    if (!imageElement.value) {
        return { width: viewportSize.value, height: viewportSize.value };
    }

    const scale = getBaseScale() * zoom.value;

    return {
        width: imageElement.value.width * scale,
        height: imageElement.value.height * scale,
    };
};

const clampOffsets = () => {
    const dimensions = getRenderDimensions();

    const maxX = Math.max(0, (dimensions.width - viewportSize.value) / 2);
    const maxY = Math.max(0, (dimensions.height - viewportSize.value) / 2);

    offsetX.value = clamp(offsetX.value, -maxX, maxX);
    offsetY.value = clamp(offsetY.value, -maxY, maxY);
};

const updateViewportSize = () => {
    viewportSize.value = cropContainerRef.value?.clientWidth ?? 320;
    clampOffsets();
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

    const delta = event.deltaY > 0 ? -ZOOM_STEP : ZOOM_STEP;
    zoom.value = clamp(zoom.value + delta, MIN_ZOOM, MAX_ZOOM);
};

const handleSliderChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    zoom.value = clamp(Number.parseFloat(target.value), MIN_ZOOM, MAX_ZOOM);
};

const zoomIn = () => {
    zoom.value = clamp(zoom.value + ZOOM_STEP, MIN_ZOOM, MAX_ZOOM);
};

const zoomOut = () => {
    zoom.value = clamp(zoom.value - ZOOM_STEP, MIN_ZOOM, MAX_ZOOM);
};

const imageStyle = computed(() => {
    const dimensions = getRenderDimensions();
    const left = (viewportSize.value - dimensions.width) / 2 + offsetX.value;
    const top = (viewportSize.value - dimensions.height) / 2 + offsetY.value;

    return {
        width: `${dimensions.width}px`,
        height: `${dimensions.height}px`,
        left: `${left}px`,
        top: `${top}px`,
        cursor: isDragging.value ? 'grabbing' : 'grab',
    };
});

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
        <FormDialogContent class="max-w-[560px]">
            <div class="space-y-5">
                <DialogHeader>
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
                                <img
                                    :src="localImageUrl"
                                    alt="Vista previa de avatar"
                                    class="absolute select-none"
                                    :style="imageStyle"
                                    draggable="false"
                                />
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
                                variant="outline"
                                size="icon"
                                class="h-8 w-8 rounded-full"
                                :disabled="zoom <= MIN_ZOOM"
                                @click="zoomOut"
                            >
                                <Minus class="h-4 w-4" />
                            </Button>

                            <input
                                type="range"
                                :min="MIN_ZOOM"
                                :max="MAX_ZOOM"
                                :step="0.01"
                                :value="zoom"
                                class="h-2 w-full cursor-pointer appearance-none rounded-full bg-muted accent-primary"
                                @input="handleSliderChange"
                            />

                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                class="h-8 w-8 rounded-full"
                                :disabled="zoom >= MAX_ZOOM"
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
                        <Button type="button" variant="outline" @click="closeDialog" :disabled="isSaving">
                            Cancelar
                        </Button>
                        <Button type="button" class="gap-2" @click="saveAvatar" :disabled="!isReady || isSaving">
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
