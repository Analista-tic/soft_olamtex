<script setup lang="ts">
import AlertBox from '@/components/AlertBox.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import axios from 'axios';
import { Upload } from 'lucide-vue-next';
import { ref } from 'vue';

const emit = defineEmits<{
    (e: 'imported', payload: { inserted: number; skipped: number; errors: { row: number | null; message: string }[] }): void;
    (e: 'update:open', value: boolean): void;
}>();

defineProps<{ open: boolean }>();

const fileInput = ref<HTMLInputElement | null>(null);
const selectedFile = ref<File | null>(null);
const isLoading = ref(false);
const result = ref<{ inserted: number; skipped: number; errors: { row: number | null; message: string }[]; message: string } | null>(null);
const errorMessage = ref('');

function onFileChange(e: Event) {
    const input = e.target as HTMLInputElement;
    selectedFile.value = input.files?.[0] ?? null;
    result.value = null;
    errorMessage.value = '';
}

async function submit() {
    if (!selectedFile.value) return;
    isLoading.value = true;
    errorMessage.value = '';
    result.value = null;

    const form = new FormData();
    form.append('file', selectedFile.value);

    try {
        const response = await axios.post('/kardex-telas/inventario-rollos/importar-csv', form, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        result.value = response.data;
        if (response.data.inserted > 0) {
            emit('imported', {
                inserted: response.data.inserted,
                skipped: response.data.skipped,
                errors: response.data.errors ?? [],
            });
        }
    } catch (err: unknown) {
        const axiosErr = err as { response?: { data?: { message?: string } } };
        errorMessage.value = axiosErr.response?.data?.message ?? 'Error al importar el archivo.';
    } finally {
        isLoading.value = false;
    }
}

function close() {
    selectedFile.value = null;
    result.value = null;
    errorMessage.value = '';
    if (fileInput.value) fileInput.value.value = '';
    emit('update:open', false);
}
</script>

<template>
    <Dialog :open="open" @update:open="close">
        <DialogContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle>Importar CSV histórico de rollos</DialogTitle>
            </DialogHeader>

            <div class="flex flex-col gap-4 py-2">
                <p class="text-sm text-muted-foreground">
                    Selecciona el archivo CSV con los rollos a importar (delimitador <code>;</code>). Los rollos con número de rollo
                    ya existente serán omitidos.
                </p>

                <input ref="fileInput" type="file" accept=".csv,.txt" class="text-sm" @change="onFileChange" />

                <AlertBox
                    v-if="errorMessage"
                    type="error"
                    title="Error"
                    :message="errorMessage"
                    :auto-close="false"
                    @close="errorMessage = ''"
                />

                <div v-if="result" class="rounded-md border border-border bg-muted/30 p-3 text-sm">
                    <p class="font-medium">{{ result.message }}</p>
                    <p class="mt-1 text-muted-foreground">Insertados: {{ result.inserted }} &nbsp;|&nbsp; Omitidos: {{ result.skipped }}</p>
                    <ul v-if="result.errors.length" class="mt-2 max-h-40 overflow-y-auto space-y-1">
                        <li
                            v-for="(err, idx) in result.errors"
                            :key="idx"
                            class="text-destructive text-xs"
                        >
                            <span v-if="err.row">Fila {{ err.row }}: </span>{{ err.message }}
                        </li>
                    </ul>
                </div>
            </div>

            <DialogFooter class="gap-2">
                <Button variant="outline" type="button" @click="close">Cerrar</Button>
                <Button
                    type="button"
                    :disabled="!selectedFile || isLoading"
                    @click="submit"
                >
                    <Upload class="mr-2 h-4 w-4" />
                    {{ isLoading ? 'Importando...' : 'Importar' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
