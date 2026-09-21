<script setup lang="ts">
import HelpTooltip from '@/components/HelpTooltip.vue';
import ImageViewer from '@/components/ImageViewer.vue';
import Label from '@/components/ui/label/Label.vue';
import { Upload } from 'lucide-vue-next';
import { computed, onUnmounted, ref } from 'vue';

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

// Estado de archivo
const file = ref<File | null>(null);
const previewUrl = ref<string | null>(null);
const loadFailed = ref(false);
const fileInputRef = ref<HTMLInputElement | null>(null);
const isDragging = ref(false);

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

// Funciones
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
    isDragging.value = false;
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

function handleImageDelete(): void {
    clearImage();
}

function handleImageUpdate(): void {
    triggerFilePicker();
}

// Cleanup
onUnmounted(() => {
    revokePreview();
});

// Expose methods
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
            v-if="!visualUrl"
            class="group relative overflow-hidden rounded-xl transition-colors duration-200"
            :class="[
                isDragging
                    ? 'border-2 border-dashed border-primary bg-primary/5'
                    : !readonly
                      ? 'cursor-pointer border-2 border-dashed border-border bg-muted/10 hover:border-primary/50 hover:bg-muted/30'
                      : 'border-2 border-border/40 bg-muted/10',
            ]"
            @dragover="handleDragOver"
            @dragleave="handleDragLeave"
            @drop="handleDrop"
            @click="!readonly ? triggerFilePicker() : undefined"
        >
            <div class="flex h-48 flex-col items-center justify-center gap-3 p-4 text-center">
                <Upload class="h-8 w-8 text-muted-foreground transition-colors group-hover:text-primary" />
                <div>
                    <p class="text-sm font-medium text-foreground">Sube tu dibujo plano</p>
                    <p class="mt-1 text-xs text-muted-foreground">JPG, PNG o WebP</p>
                </div>
            </div>
        </div>

        <!-- Image Viewer (cuando hay imagen) -->
        <div v-else>
            <ImageViewer
                :src="visualUrl"
                alt="Dibujo plano"
                :readonly="readonly"
                @delete="handleImageDelete"
                @update="handleImageUpdate"
            />
        </div>
    </div>
</template>

<style scoped>
/* Prevenir selección de imagen durante drag */
img {
    user-select: none;
    -webkit-user-drag: none;
}
</style>
