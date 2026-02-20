<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import Button from '@/components/ui/button/Button.vue';
import { ClipboardList, CloudUpload, Download } from 'lucide-vue-next';
import axios from 'axios';

const props = defineProps<{
    open: boolean;
}>();
const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'uploaded'): void;
}>();

const isOpen = ref(props.open);
watch(
    () => props.open,
    (val) => (isOpen.value = val),
);

const bulkUploadFile = ref<File | null>(null);
const isUploading = ref(false);
const uploadError = ref('');
const uploadSuccess = ref('');
const fileInputRef = ref<HTMLInputElement | null>(null);

const recommendations = [
    'Puedes importar hasta 3.000 artículos por archivo y repetir el proceso cuando lo necesites.',
    'Incluye los campos "Grupo" y "Subgrupo" para mantener el registro completo.',
    'Define stock en Excel agregando las columnas "Cantidad", "Cantidad Min" y "Cantidad Max".',
];

const selectedFileLabel = computed(() => bulkUploadFile.value?.name ?? 'Ningún archivo seleccionado');
const hasFile = computed(() => Boolean(bulkUploadFile.value));

function close() {
    isOpen.value = false;
    emit('close');
    uploadError.value = '';
    uploadSuccess.value = '';
    bulkUploadFile.value = null;
}

async function handleBulkUpload() {
    if (!bulkUploadFile.value) {
        uploadError.value = 'Selecciona un archivo para subir.';
        return;
    }
    isUploading.value = true;
    uploadError.value = '';
    uploadSuccess.value = '';
    const formData = new FormData();
    formData.append('file', bulkUploadFile.value);
    try {
        await axios.post('/item/carga-masiva', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        uploadSuccess.value = 'Artículos cargados correctamente.';
        emit('uploaded');
        
        // Cerrar el diálogo después de 1.5 segundos para que el usuario vea el mensaje
        setTimeout(() => {
            close();
        }, 1500);
    } catch (e: any) {
        uploadError.value = e?.response?.data?.message || 'Error al cargar los ítems.';
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
    window.open('/item/descargar-plantilla', '_blank');
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
        <DialogContent class="w-full max-w-4xl max-h-[90vh] flex flex-col">
            <DialogHeader class="space-y-1 flex-shrink-0">
                <div class="flex items-center gap-3">
                    <ClipboardList class="h-6 w-6 text-primary" />
                    <DialogTitle class="text-lg font-semibold">Importación de ítems</DialogTitle>
                </div>
                <p class="text-sm text-muted-foreground pb-5">
                    Sube un archivo Excel para registrar productos y servicios de forma masiva.
                </p>
            </DialogHeader>

            <div class="overflow-y-auto flex-1 pr-2 scroll-smooth" style="scrollbar-width: thin;">
            <div class="grid gap-6 lg:grid-cols-[1.3fr,1.7fr]">
                <section class="rounded-2xl border border-muted bg-muted/40 p-4 text-sm text-muted-foreground">
                    <header class="flex items-center gap-2 text-base font-semibold text-foreground">
                        <ClipboardList class="h-5 w-5" />
                        Recomendaciones
                    </header>
                    <ul class="mt-4 space-y-2 list-disc pl-5 text-sm text-foreground/80">
                        <li v-for="(item, index) in recommendations" :key="index">
                            {{ item }}
                        </li>
                    </ul>
                </section>

                <section class="rounded-2xl border border-muted bg-white p-6 shadow-sm">
                    <div class="space-y-4">
                        <div>
                            <p class="text-sm font-semibold text-foreground">1. Descarga la plantilla oficial</p>
                            <p class="text-sm text-muted-foreground">
                                Utiliza la plantilla oficial para garantizar que todos los campos estén alineados.
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
                                    <input
                                        ref="fileInputRef"
                                        type="file"
                                        accept=".xlsx,.xls"
                                        class="sr-only"
                                        @change="handleFileSelection"
                                    />
                                    <Button variant="outline" size="sm" class="mt-3" @click="openFileDialog">
                                        Seleccionar archivo
                                    </Button>
                                    <p class="text-sm text-muted-foreground">{{ selectedFileLabel }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <p v-if="uploadError" class="text-sm text-red-600">{{ uploadError }}</p>
                            <p v-if="uploadSuccess" class="text-sm text-emerald-600">{{ uploadSuccess }}</p>
                        </div>
                    </div>
                </section>
            </div>
            </div>

            <DialogFooter class="flex-shrink-0 border-t pt-4 mt-4 flex flex-col-reverse gap-2 md:flex-row md:justify-end">
                <div class="w-full md:w-auto">
                    <button
                        type="button"
                        class="mx-auto flex w-full max-w-[140px] items-center justify-center gap-2 rounded-full border border-primary/40 bg-white px-5 py-2 text-sm font-semibold text-primary transition hover:bg-primary/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary"
                        @click="close"
                    >
                        Cancelar
                    </button>
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

