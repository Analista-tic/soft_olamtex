<script setup lang="ts">
import { ref, watch } from 'vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import Button from '@/components/ui/button/Button.vue';
import { Upload, Download } from 'lucide-vue-next';
import axios from 'axios';

const props = defineProps<{
    open: boolean
}>();
const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'uploaded'): void;
}>();

const isOpen = ref(props.open);
watch(() => props.open, (val) => isOpen.value = val);

const bulkUploadFile = ref<File | null>(null);
const isUploading = ref(false);
const isDownloading = ref(false);
const uploadError = ref('');
const uploadSuccess = ref('');

function close() {
    isOpen.value = false;
    emit('close');
    uploadError.value = '';
    uploadSuccess.value = '';
    bulkUploadFile.value = null;
}

function downloadTemplate() {
    isDownloading.value = true;
    uploadError.value = '';
    // Usa ruta absoluta sin BASE_URL para evitar doble slash o rutas incorrectas
    const url = '/item/descargar-plantilla';
    fetch(url, {
        method: 'GET',
        headers: {
            'Accept': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        }
    })
        .then(async response => {
            if (!response.ok) {
                const errorText = await response.text();
                uploadError.value = 'No se pudo descargar la plantilla. ' + errorText;
                isDownloading.value = false;
                return;
            }
            return response.blob();
        })
        .then(blob => {
            if (!blob) return;
            const link = document.createElement('a');
            link.href = window.URL.createObjectURL(blob);
            link.download = 'plantilla_items.xlsx';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            isDownloading.value = false;
        })
        .catch(() => {
            uploadError.value = 'No se pudo descargar la plantilla.';
            isDownloading.value = false;
        });
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
            headers: { 'Content-Type': 'multipart/form-data' }
        });
        uploadSuccess.value = 'Artículos cargados correctamente.';
        emit('uploaded');
    } catch (e: any) {
        uploadError.value = e?.response?.data?.message || 'Error al cargar los artículos.';
    } finally {
        isUploading.value = false;
    }
}
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent class="w-full max-w-2xl">
            <DialogHeader>
                <DialogTitle>Carga Masiva de Artículos</DialogTitle>
                <DialogDescription>
                    Descarga la plantilla o sube el archivo Excel con los artículos para registrarlos en lote.
                </DialogDescription>
            </DialogHeader>
            <Tabs default-value="descargar" class="w-full">
                <TabsList>
                    <TabsTrigger value="descargar">
                        Descargar Plantilla
                    </TabsTrigger>
                    <TabsTrigger value="subir">
                        Subir Plantilla
                    </TabsTrigger>
                </TabsList>
                <TabsContent value="descargar">
                    <div class="flex flex-col items-center justify-center gap-4 py-6">
                        <p>Descarga la plantilla de Excel para registrar artículos de forma masiva.</p>
                        <Button @click="downloadTemplate" variant="outline" :disabled="isDownloading">
                            <template v-if="isDownloading">
                                <svg class="animate-spin h-4 w-4 mr-2 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                </svg>
                                Generando...
                            </template>
                            <template v-else>
                                <Download class="h-4 w-4 mr-2" /> Descargar Plantilla
                            </template>
                        </Button>
                        <span v-if="uploadError" class="text-red-500">{{ uploadError }}</span>
                    </div>
                </TabsContent>
                <TabsContent value="subir">
                    <div class="flex flex-col gap-4 py-6">
                        <p>Selecciona el archivo Excel con los artículos a registrar.</p>
                        <input
                            type="file"
                            accept=".xlsx,.xls"
                            @change="(e: Event) => {
                                const target = e.target as HTMLInputElement;
                                // No accedas a ninguna propiedad 'value' de File, solo asigna el objeto File directamente al ref de Vue
                                bulkUploadFile = target.files && target.files.length > 0 ? target.files[0] : null;
                            }"
                        />
                        <Button :disabled="isUploading" @click="handleBulkUpload" variant="default">
                            <Upload class="h-4 w-4 mr-2" /> Subir Archivo
                        </Button>
                        <span v-if="uploadError" class="text-red-500">{{ uploadError }}</span>
                        <span v-if="uploadSuccess" class="text-green-600">{{ uploadSuccess }}</span>
                    </div>
                </TabsContent>
            </Tabs>
            <DialogFooter>
                <Button variant="outline" @click="close">Cerrar</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>






