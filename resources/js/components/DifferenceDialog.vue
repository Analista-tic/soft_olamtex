<script setup lang="ts">
import TextareaField from '@/components/TextareaField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { computed, nextTick, ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    total: number;
    orderTotal: number;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'save', payload: { reason: string; evidenceData: string; evidenceName: string }): void;
    (e: 'cancel'): void;
}>();

const dialogOpen = computed({
    get: () => props.open,
    set: (value: boolean) => emit('update:open', value),
});

const reason = ref('');
const evidenceData = ref<string | null>(null);
const evidenceName = ref('');
const validationMessage = ref('');
const videoElement = ref<HTMLVideoElement | null>(null);
const videoStream = ref<MediaStream | null>(null);
const cameraError = ref('');
const capturedImagePreview = ref<string | null>(null);
const cameraCaptured = ref(false);
const isCameraActive = computed(() => Boolean(videoStream.value));

const formattedTotal = computed(() => formatCurrency(props.total));
const formattedOrderTotal = computed(() => formatCurrency(props.orderTotal));

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) {
            stopCamera();
            resetFields();
        }
    },
);

watch(videoStream, () => {
    if (videoElement.value) {
        videoElement.value.srcObject = videoStream.value;
    }
});

function resetFields() {
    reason.value = '';
    evidenceData.value = null;
    evidenceName.value = '';
    validationMessage.value = '';
    capturedImagePreview.value = null;
    cameraCaptured.value = false;
    cameraError.value = '';
}

function formatCurrency(value: number) {
    return Number(value || 0).toLocaleString('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    });
}

function handleFileChange(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) {
        evidenceData.value = null;
        evidenceName.value = '';
        return;
    }
    stopCamera();
    capturedImagePreview.value = null;
    cameraCaptured.value = false;
    evidenceName.value = file.name;
    const reader = new FileReader();
    reader.onload = () => {
        const data = reader.result as string;
        evidenceData.value = data;
        capturedImagePreview.value = data;
    };
    reader.readAsDataURL(file);
}

async function startCamera() {
    if (typeof navigator === 'undefined' || !navigator.mediaDevices?.getUserMedia) {
        cameraError.value = 'Tu navegador no permite acceder a la cámara.';
        return;
    }
    stopCamera();
    cameraCaptured.value = false;
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
        videoStream.value = stream;
        cameraError.value = '';
        await nextTick();
        if (videoElement.value) {
            videoElement.value.srcObject = stream;
            videoElement.value.play().catch(() => {});
        }
    } catch {
        cameraError.value = 'No se pudo iniciar la cámara.';
    }
}

function stopCamera() {
    if (videoStream.value) {
        videoStream.value.getTracks().forEach((track) => track.stop());
    }
    videoStream.value = null;
    if (videoElement.value) {
        videoElement.value.srcObject = null;
    }
}

function toggleCamera() {
    if (isCameraActive.value) {
        stopCamera();
    } else {
        void startCamera();
    }
}

function takePhoto() {
    if (!videoElement.value) {
        cameraError.value = 'No hay cámara disponible.';
        return;
    }
    const video = videoElement.value;
    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth || 1280;
    canvas.height = video.videoHeight || 720;
    const ctx = canvas.getContext('2d');
    if (!ctx) {
        cameraError.value = 'No se pudo capturar la imagen.';
        return;
    }
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    const dataUrl = canvas.toDataURL('image/png');
    evidenceData.value = dataUrl;
    capturedImagePreview.value = dataUrl;
    cameraCaptured.value = true;
    evidenceName.value = `captura-${Date.now()}.png`;
    stopCamera();
}

function retakePhoto() {
    capturedImagePreview.value = null;
    evidenceData.value = null;
    evidenceName.value = '';
    cameraError.value = '';
    cameraCaptured.value = false;
    void startCamera();
}

function handleCancel() {
    validationMessage.value = '';
    stopCamera();
    emit('cancel');
    dialogOpen.value = false;
}

function handleSave() {
    const trimmedReason = reason.value.trim();
    validationMessage.value = '';
    if (!trimmedReason) {
        validationMessage.value = 'Por favor describe el motivo de la diferencia.';
        return;
    }
    if (!evidenceData.value) {
        validationMessage.value = 'Por favor adjunta una imagen de evidencia.';
        return;
    }
    emit('save', {
        reason: trimmedReason,
        evidenceData: evidenceData.value,
        evidenceName: evidenceName.value,
    });
    dialogOpen.value = false;
    resetFields();
}
</script>

<template>
    <Dialog v-model:open="dialogOpen">
        <DialogContent class="flex flex-col max-h-[95vh] max-w-2xl px-2 py-2">
            <DialogHeader class="space-y-1 border-b border-sidebar-border/70 bg-muted/20 px-5 py-4 dark:border-sidebar-border">
                <DialogTitle>Valor diferente a la orden</DialogTitle>
                <DialogDescription>
                    El total de la factura {{ formattedTotal }} no coincide con el valor de la orden {{ formattedOrderTotal }}.
                </DialogDescription>
            </DialogHeader>
            <div class="flex-1 overflow-y-auto scroll-smooth pr-2" style="scrollbar-width: thin">
                <div class="space-y-4">
                    <TextareaField v-model="reason" label="Motivo" rows="3" required />
                    <div class="space-y-2 text-sm">
                        <label class="block font-semibold">
                            Evidencia (imagen)
                            <span class="text-red-500">*</span>
                        </label>
                        <div class="space-y-2">
                            <label class="block">
                                <Button as="label" variant="outline" class="w-full justify-center px-4">
                                    Seleccionar archivo
                                    <input type="file" accept="image/*" class="sr-only" @change="handleFileChange" required />
                                </Button>
                            </label>
                            <div class="space-y-2">
                                <Button
                                    variant="outline"
                                    type="button"
                                    class="w-full justify-center px-4"
                                    @click="toggleCamera"
                                    v-if="!cameraCaptured"
                                >
                                    {{ isCameraActive ? 'Cerrar cámara' : 'Abrir cámara' }}
                                </Button>
                                <div class="flex flex-wrap gap-2">
                                    <Button
                                        variant="outline"
                                        type="button"
                                        class="w-full justify-center px-4"
                                        @click="retakePhoto"
                                        v-if="cameraCaptured"
                                    >
                                        Reanudar foto
                                    </Button>
                                </div>
                            </div>
                        </div>
                        <p v-if="evidenceName" class="text-xs text-muted-foreground">{{ evidenceName }}</p>
                        <p v-else-if="capturedImagePreview" class="text-xs text-muted-foreground">Imagen capturada</p>
                        <p v-if="cameraError" class="text-xs text-red-500">{{ cameraError }}</p>
                        <div v-if="videoStream" class="overflow-hidden rounded border border-border/60">
                            <video ref="videoElement" autoplay playsinline class="h-64 w-full bg-black object-cover"></video>
                            <div class="flex justify-end p-2">
                                <Button variant="default" type="button" @click="takePhoto">Tomar foto</Button>
                            </div>
                        </div>
                        <div v-else-if="capturedImagePreview" class="overflow-hidden rounded border border-border/60">
                            <img :src="capturedImagePreview" alt="Foto capturada" class="h-64 w-full object-cover" />
                        </div>
                    </div>
                    <p v-if="validationMessage" class="text-xs text-red-500">{{ validationMessage }}</p>
                </div>
            </div>
            <DialogFooter class="mt-4 flex-shrink-0 gap-2 border-t pt-4">
                <Button variant="outline" size="pill" type="button" @click="handleCancel">Cancelar</Button>
                <Button variant="default" size="pill" class="shadow-sm" type="button" @click="handleSave">Guardar y continuar</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
