<script setup lang="ts">
import { ref, watch } from 'vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import Button from '@/components/ui/button/Button.vue';
import { Upload } from 'lucide-vue-next';
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
const uploadError = ref('');
const uploadSuccess = ref('');

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
            headers: { 'Content-Type': 'multipart/form-data' }
        });
        uploadSuccess.value = 'Artículos cargados correctamente.';
        emit('uploaded');
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
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent class="w-full max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Carga Masiva de Artículos</DialogTitle>
                    <DialogDescription>
                        Sube el archivo Excel con los ítems para registrarlos en lote.
                    </DialogDescription>
                </DialogHeader>
            <div class="flex flex-col gap-4 py-6">
                <p>Selecciona el archivo Excel con los ítems a registrar en lote.</p>
                <input
                    type="file"
                    accept=".xlsx,.xls"
                    @change="handleFileSelection"
                />
                <Button :disabled="isUploading" @click="handleBulkUpload" variant="default">
                    <Upload class="h-4 w-4 mr-2" /> Subir Archivo
                </Button>
                <span v-if="uploadError" class="text-red-500">{{ uploadError }}</span>
                <span v-if="uploadSuccess" class="text-green-600">{{ uploadSuccess }}</span>
            </div>
            <DialogFooter>
                <Button variant="outline" @click="close">Cerrar</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
