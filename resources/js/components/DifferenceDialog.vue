<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import TextareaField from '@/components/TextareaField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useAlertStack } from '@/composables/useAlert';
import { AlertTriangle } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        open: boolean;
        total?: number | null;
        orderTotal?: number | null;
        description?: string;
        requireEvidence?: boolean;
        allowEvidenceUrl?: boolean;
        enableCamera?: boolean;
    }>(),
    {
        total: null,
        orderTotal: null,
        description: '',
        requireEvidence: true,
        allowEvidenceUrl: false,
        enableCamera: true,
    },
);

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'save', payload: { reason: string; evidenceData: string | null; evidenceName: string; evidenceUrl: string | null }): void;
    (e: 'cancel'): void;
}>();

const dialogOpen = computed({
    get: () => props.open,
    set: (value: boolean) => emit('update:open', value),
});

const { errorMessage, errorType, showError, showWarning, clearError } = useAlertStack();
const reason = ref('');
const evidenceData = ref<string | null>(null);
const evidenceName = ref('');
const evidenceUrl = ref('');
const cameraOpen = ref(false);
const cameraStream = ref<MediaStream | null>(null);
const videoRef = ref<HTMLVideoElement | null>(null);
const canvasRef = ref<HTMLCanvasElement | null>(null);
const evidenceImage = ref<string | null>(null);

const hasCapturedImage = computed(() => Boolean(evidenceImage.value));
const hasEvidence = computed(() => Boolean(evidenceData.value || evidenceImage.value));
const isCameraActive = computed(() => cameraOpen.value && Boolean(cameraStream.value));
const hasTotals = computed(() => props.total != null && props.orderTotal != null);
const formattedTotal = computed(() => formatCurrency(Number(props.total ?? 0)));
const formattedOrderTotal = computed(() => formatCurrency(Number(props.orderTotal ?? 0)));

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) {
            stopCamera();
            resetFields();
        }
    },
);

function resetFields() {
    reason.value = '';
    evidenceData.value = null;
    evidenceName.value = '';
    evidenceUrl.value = '';
    evidenceImage.value = null;
    cameraOpen.value = false;
    clearError();
}

function formatCurrency(value: number) {
    return Number(value || 0).toLocaleString('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    });
}

function handleFileSelect(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    if (!file) {
        evidenceData.value = null;
        evidenceName.value = '';
        evidenceImage.value = null;
        return;
    }

    closeCamera();
    evidenceName.value = file.name;
    clearError();

    const reader = new FileReader();
    reader.onload = () => {
        const data = reader.result as string;
        evidenceData.value = data;
        evidenceImage.value = data;
        clearError();
    };
    reader.readAsDataURL(file);
}

async function openCamera() {
    if (typeof navigator === 'undefined' || !navigator.mediaDevices?.getUserMedia) {
        showError('Tu navegador no permite acceder a la cámara.', 'error');
        return;
    }

    closeCamera();
    evidenceImage.value = null;

    try {
        const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
        cameraStream.value = stream;
        cameraOpen.value = true;
        clearError();
        await nextTick();

        if (videoRef.value) {
            videoRef.value.srcObject = stream;
            videoRef.value.play().catch(() => {});
        }
    } catch {
        cameraOpen.value = false;
        showError('No se pudo iniciar la cámara.', 'error');
    }
}

function closeCamera() {
    if (cameraStream.value) {
        cameraStream.value.getTracks().forEach((track) => track.stop());
        cameraStream.value = null;
    }

    if (videoRef.value) {
        videoRef.value.srcObject = null;
    }

    cameraOpen.value = false;
}

function stopCamera() {
    closeCamera();
}

function takePhoto() {
    if (!videoRef.value || !canvasRef.value) {
        showError('No hay cámara disponible.', 'error');
        return;
    }

    const video = videoRef.value;
    const canvas = canvasRef.value;
    canvas.width = video.videoWidth || 1280;
    canvas.height = video.videoHeight || 720;

    const ctx = canvas.getContext('2d');
    if (!ctx) {
        showError('No se pudo capturar la imagen.', 'error');
        return;
    }

    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    const dataUrl = canvas.toDataURL('image/png');
    evidenceData.value = dataUrl;
    evidenceImage.value = dataUrl;
    evidenceName.value = `captura-${Date.now()}.png`;
    clearError();
    stopCamera();
}

function retakePhoto() {
    evidenceImage.value = null;
    evidenceData.value = null;
    evidenceName.value = '';
    clearError();
    void openCamera();
}

function handleCancel() {
    clearError();
    stopCamera();
    emit('cancel');
    dialogOpen.value = false;
}

function handleSave() {
    const trimmedReason = reason.value.trim();
    clearError();

    if (!trimmedReason) {
        showWarning('El motivo de la diferencia es obligatorio.');
        return;
    }

    if (!hasEvidence.value) {
        showWarning('Debes adjuntar una imagen o tomar una foto como evidencia.');
        return;
    }

    emit('save', {
        reason: trimmedReason,
        evidenceData: evidenceData.value,
        evidenceName: evidenceName.value,
        evidenceUrl: null,
    });
    dialogOpen.value = false;
    resetFields();
}
</script>

<template>
    <Dialog v-model:open="dialogOpen">
        <DialogContent class="flex max-h-[95vh] max-w-2xl flex-col gap-0 overflow-hidden p-0">
            <AlertStack :error-message="errorMessage" :error-type="errorType" @close-error="clearError" />

            <DialogHeader class="border-b border-border/50 bg-amber-500/5 px-6 py-5">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-amber-500/10">
                        <AlertTriangle class="h-4 w-4 text-amber-600" />
                    </div>
                    <div class="min-w-0">
                        <DialogTitle class="text-base font-semibold leading-tight">Valor diferente a la orden</DialogTitle>
                        <p v-if="hasTotals" class="mt-0.5 text-xs text-muted-foreground">
                            Total factura: <span class="font-semibold text-foreground">{{ formattedTotal }}</span>
                            <span class="mx-1">·</span>
                            Total orden: <span class="font-semibold text-foreground">{{ formattedOrderTotal }}</span>
                        </p>
                        <p v-else class="mt-0.5 text-xs text-muted-foreground">Se requiere justificación para continuar</p>
                    </div>
                </div>
            </DialogHeader>

            <div class="flex-1 overflow-y-auto scroll-smooth px-5 py-4" style="scrollbar-width: thin">
                <div class="space-y-4">
                    <TextareaField data-no-uppercase v-model="reason" label="Motivo de la diferencia" :rows="4" class="min-h-[100px] resize-y" required />

                    <div class="space-y-2 text-sm">
                        <label class="block font-semibold">
                            Evidencia
                            <span class="text-red-500">*</span>
                        </label>
                        <p class="mb-2 text-xs text-muted-foreground">
                            Adjunta una imagen que respalde la diferencia: cotización del proveedor, correo de autorización, nota de ajuste u otro
                            documento relevante.
                        </p>

                        <div class="space-y-2">
                            <div class="flex gap-2">
                                <label class="flex-1">
                                    <Button as="label" variant="outline" class="w-full justify-center px-4">
                                        Seleccionar archivo
                                        <input type="file" accept="image/*" class="sr-only" @change="handleFileSelect" />
                                    </Button>
                                </label>
                                <Button
                                    v-if="!isCameraActive && !hasCapturedImage"
                                    variant="outline"
                                    type="button"
                                    class="flex-1 justify-center px-4"
                                    @click="openCamera"
                                >
                                    Abrir cámara
                                </Button>
                            </div>

                            <div v-if="isCameraActive" class="overflow-hidden rounded border border-border/60">
                                <video ref="videoRef" autoplay playsinline muted class="h-64 w-full bg-black object-cover"></video>
                                <div class="flex justify-end gap-2 p-2">
                                    <Button variant="outline" type="button" @click="closeCamera">Cerrar cámara</Button>
                                    <Button variant="default" type="button" @click="takePhoto">Tomar foto</Button>
                                </div>
                            </div>

                            <div v-if="hasCapturedImage" class="space-y-2">
                                <div class="overflow-hidden rounded border border-border/60">
                                    <img :src="evidenceImage || ''" alt="Foto capturada" class="h-64 w-full object-cover" />
                                </div>
                                <Button variant="outline" type="button" class="w-full justify-center px-4 py-2" @click="retakePhoto">
                                    Reanudar foto
                                </Button>
                            </div>

                            <canvas ref="canvasRef" class="hidden"></canvas>
                        </div>

                        <p v-if="evidenceName" class="text-xs text-muted-foreground">{{ evidenceName }}</p>
                    </div>
                </div>
            </div>

            <DialogFooter class="flex-shrink-0 gap-2 border-t border-border/50 px-6 py-4">
                <Button variant="outline" size="pill" type="button" @click="handleCancel">Cancelar</Button>
                <Button variant="default" size="pill" class="shadow-sm" type="button" @click="handleSave">Enviar para aprobación</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
