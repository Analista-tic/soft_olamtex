<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import axios from 'axios';
import { ClipboardList, CloudUpload, Download } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
}>();
const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'uploaded', payload: { message: string }): void;
    (e: 'error', payload: { message: string }): void;
}>();

const isOpen = ref(props.open);
watch(
    () => props.open,
    (val) => (isOpen.value = val),
);

const bulkUploadFile = ref<File | null>(null);
const isUploading = ref(false);
const fileInputRef = ref<HTMLInputElement | null>(null);

const recommendations = [
    'La plantilla ya trae las sábanas existentes con su ID lleno: no borres esa columna si quieres actualizarlas.',
    'Deja el ID vacío para crear una sábana nueva.',
    'Si ES TEJIDO = NO, indica BASE TEJIDO. Si ES TEJIDO = SI, indica BASE HILAZA.',
    'Si activas el proceso "Muestras", LISTA OPERACIONAL pasa a ser obligatoria.',
    'En las columnas de tallas y procesos escribe SI para activarlas, o déjalas vacías.',
];

const selectedFileLabel = computed(() => bulkUploadFile.value?.name ?? 'Ningún archivo seleccionado');
const hasFile = computed(() => Boolean(bulkUploadFile.value));

function close() {
    isOpen.value = false;
    emit('close');
    bulkUploadFile.value = null;
    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }
}

async function handleBulkUpload() {
    if (!bulkUploadFile.value) {
        emit('error', { message: 'Selecciona un archivo para subir.' });
        return;
    }
    isUploading.value = true;
    const formData = new FormData();
    formData.append('file', bulkUploadFile.value);
    try {
        const response = await axios.post('/produccion/sabana-productos/carga-masiva', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        emit('uploaded', {
            message: response?.data?.message ?? 'Sábanas cargadas correctamente.',
        });
        close();
    } catch (e: unknown) {
        const message = axios.isAxiosError(e) ? (e.response?.data?.message ?? 'Error al importar el archivo.') : 'Error al importar el archivo.';
        emit('error', { message });
    } finally {
        isUploading.value = false;
    }
}

function handleFileSelection(event: Event) {
    const target = event.target as HTMLInputElement;
    bulkUploadFile.value = target.files && target.files.length > 0 ? target.files[0] : null;
}

function openFileDialog() {
    fileInputRef.value?.click();
}

function downloadTemplate() {
    window.open('/produccion/sabana-productos/descargar-plantilla', '_blank');
}

function handleDrop(event: DragEvent) {
    event.preventDefault();
    const files = event.dataTransfer?.files ?? null;
    if (files && files.length > 0) {
        bulkUploadFile.value = files[0];
    }
}

function preventDrag(event: DragEvent) {
    event.preventDefault();
}
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent class="flex max-h-[90vh] w-full max-w-2xl flex-col">
            <DialogHeader class="space-y-1 border-b border-sidebar-border/70 bg-muted/20 px-5 py-4 dark:border-sidebar-border">
                <div class="flex items-center gap-3">
                    <ClipboardList class="h-6 w-6 text-primary" />
                    <DialogTitle class="text-lg font-semibold">Importación / exportación masiva de sábanas</DialogTitle>
                </div>
                <p class="pb-5 text-sm text-muted-foreground">
                    Descarga la plantilla (ya trae las sábanas existentes) para editarlas o agregar nuevas, y luego súbela para aplicar los cambios.
                </p>
            </DialogHeader>

            <div class="no-scrollbar -mx-4 max-h-[65vh] min-h-0 flex-1 overflow-y-auto px-4">
                <div class="grid gap-6 lg:grid-cols-[1.3fr,1.7fr]">
                    <section class="rounded-2xl border border-muted bg-muted/40 p-4 text-sm text-muted-foreground">
                        <header class="flex items-center gap-2 text-base font-semibold text-foreground">
                            <ClipboardList class="h-5 w-5" />
                            Recomendaciones
                        </header>
                        <ul class="mt-4 list-disc space-y-2 pl-5 text-sm text-foreground/80">
                            <li v-for="(item, index) in recommendations" :key="index">
                                {{ item }}
                            </li>
                        </ul>
                    </section>

                    <section class="rounded-2xl border border-muted bg-card p-6 shadow-sm">
                        <div class="space-y-4">
                            <div>
                                <p class="text-sm font-semibold text-foreground">1. Descarga la plantilla con los datos actuales</p>
                                <p class="text-sm text-muted-foreground">
                                    Incluye todas las sábanas existentes (para editarlas) y filas en blanco para crear nuevas.
                                </p>
                                <div class="mt-3">
                                    <Button variant="outline" class="w-full justify-center gap-2" @click="downloadTemplate">
                                        <Download class="h-4 w-4" /> Descargar plantilla
                                    </Button>
                                </div>
                            </div>

                            <div>
                                <p class="text-sm font-semibold text-foreground">2. Sube tu archivo</p>
                                <p class="text-sm text-muted-foreground">Arrastra el archivo .xlsx o selecciónalo manualmente.</p>
                                <div
                                    class="mt-3 rounded-2xl border border-dashed border-muted p-6 text-center transition hover:border-primary hover:bg-muted/40"
                                    @drop="handleDrop"
                                    @dragover="preventDrag"
                                    @dragenter="preventDrag"
                                >
                                    <div class="flex flex-col items-center gap-3">
                                        <CloudUpload class="h-9 w-9 text-primary" />
                                        <p class="text-base font-semibold text-foreground">Selecciona o arrastra el archivo</p>
                                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Formato .xlsx</p>
                                        <input ref="fileInputRef" type="file" accept=".xlsx,.xls" class="sr-only" @change="handleFileSelection" />
                                        <Button variant="outline" size="sm" class="mt-3" @click="openFileDialog"> Seleccionar archivo </Button>
                                        <p class="text-sm text-muted-foreground">{{ selectedFileLabel }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>

            <DialogFooter class="flex flex-shrink-0 flex-col-reverse gap-2 border-t pt-4 md:flex-row md:justify-end">
                <div class="w-full md:w-auto">
                    <Button variant="outline" size="pill" class="mx-auto flex w-full max-w-[140px] justify-center" @click="close"> Cancelar </Button>
                </div>
                <div class="w-full md:w-auto">
                    <Button
                        variant="default"
                        type="button"
                        class="mx-auto flex w-full max-w-[140px] items-center justify-center rounded-full px-5 py-2 text-sm font-semibold shadow-sm"
                        :disabled="isUploading || !hasFile"
                        @click="handleBulkUpload"
                    >
                        {{ isUploading ? 'Subiendo...' : 'Subir archivo' }}
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
