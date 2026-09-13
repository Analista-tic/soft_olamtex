<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import axios from 'axios';
import { ClipboardList, CloudUpload, LoaderCircle } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

type ImportErrorItem = { row: number | null; message: string };
type ImportResult = { inserted: number; skipped: number; errors: ImportErrorItem[] };

const props = defineProps<{ open: boolean }>();

const emit = defineEmits<{
    (e: 'imported', payload: { inserted: number; skipped: number; errors: ImportErrorItem[] }): void;
    (e: 'update:open', value: boolean): void;
}>();

const recommendations = [
    'Acepta .csv, .txt (delimitador "," o ";", detectado automáticamente) o Excel .xlsx/.xls.',
    'Conserva el orden de las 15 columnas históricas: ROLLO, UBICACION, N PEDIDO, PROVEEDOR, BASE TELA, TELA, COLOR, CODIGO COLOR, ANCHO, CANTIDAD INGRESADA, SALIDA PARCIAL, DISPONIBILIDAD PARCIAL, CANTIDAD SALIDA, DISPONIBILIDAD REAL, FECHA DE INGRESO.',
    'La primera fila debe ser el encabezado; se omite al importar.',
    'Los N° ROLLO ya existentes (en el archivo o en el sistema) se omiten y se reportan en el detalle de errores.',
];

const isOpen = ref(props.open);
const file = ref<File | null>(null);
const isImporting = ref(false);
const result = ref<ImportResult | null>(null);
const errorMessage = ref('');
const fileInputRef = ref<HTMLInputElement | null>(null);

watch(
    () => props.open,
    (value) => {
        isOpen.value = value;
        if (!value) resetState();
    },
);

watch(isOpen, (value) => {
    emit('update:open', value);
    if (!value) resetState();
});

const selectedFileLabel = computed(() => file.value?.name ?? 'Ningún archivo seleccionado');
const hasFile = computed(() => Boolean(file.value));

function resetState() {
    file.value = null;
    isImporting.value = false;
    result.value = null;
    errorMessage.value = '';
    if (fileInputRef.value) fileInputRef.value.value = '';
}

function close() {
    isOpen.value = false;
}

function openFileDialog() {
    fileInputRef.value?.click();
}

function handleFileSelection(event: Event) {
    const target = event.target as HTMLInputElement;
    file.value = target.files?.length ? target.files[0] : null;
    result.value = null;
    errorMessage.value = '';
}

function handleDrop(event: DragEvent) {
    event.preventDefault();
    const files = event.dataTransfer?.files ?? null;
    if (files?.length) {
        file.value = files[0];
        result.value = null;
        errorMessage.value = '';
    }
}

function preventDrag(event: DragEvent) {
    event.preventDefault();
}

function normalizeErrors(raw: unknown): ImportErrorItem[] {
    if (!Array.isArray(raw)) return [];
    return raw
        .map((entry) => {
            if (typeof entry === 'string') return { row: null, message: entry };
            if (entry && typeof entry === 'object') {
                const record = entry as Record<string, unknown>;
                return {
                    row: record.row != null ? Number(record.row) : null,
                    message: String(record.message ?? ''),
                };
            }
            return null;
        })
        .filter((entry): entry is ImportErrorItem => Boolean(entry?.message));
}

async function submitImport() {
    if (!file.value) return;

    isImporting.value = true;
    errorMessage.value = '';
    result.value = null;

    const formData = new FormData();
    formData.append('file', file.value);

    try {
        const { data } = await axios.post('/kardex-telas/inventario-rollos/importar-csv', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        const errors = normalizeErrors(data?.errors);
        result.value = { inserted: Number(data?.inserted ?? 0), skipped: Number(data?.skipped ?? 0), errors };

        if (result.value.inserted > 0) {
            emit('imported', { inserted: result.value.inserted, skipped: result.value.skipped, errors });
        }
    } catch (err: unknown) {
        const axiosErr = err as { response?: { data?: { message?: string; errors?: unknown; inserted?: number; skipped?: number } } };
        const data = axiosErr.response?.data;
        const errors = normalizeErrors(data?.errors);

        if (errors.length > 0 || (data?.inserted ?? 0) > 0 || (data?.skipped ?? 0) > 0) {
            result.value = { inserted: Number(data?.inserted ?? 0), skipped: Number(data?.skipped ?? 0), errors };
        }

        errorMessage.value = data?.message ?? 'Error al importar el archivo.';
    } finally {
        isImporting.value = false;
    }
}
</script>

<template>
    <Dialog :open="isOpen" @update:open="(v) => (isOpen = v)">
        <DialogContent class="flex w-full max-w-2xl flex-col">
            <DialogHeader class="space-y-1 border-b border-sidebar-border/70 bg-muted/20 px-5 py-4 dark:border-sidebar-border">
                <div class="flex items-center gap-3">
                    <ClipboardList class="h-6 w-6 text-primary" />
                    <DialogTitle class="text-lg font-semibold">Importar histórico de rollos</DialogTitle>
                </div>
                <p class="pb-5 text-sm text-muted-foreground">
                    Sube el archivo histórico de rollos de tela (CSV o Excel) para registrarlos masivamente.
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
                            <li v-for="(item, index) in recommendations" :key="index">{{ item }}</li>
                        </ul>
                    </section>

                    <section class="rounded-2xl border border-muted bg-card p-6 shadow-sm">
                        <div class="space-y-4">
                            <div>
                                <p class="text-sm font-semibold text-foreground">Sube tu archivo</p>
                                <p class="text-sm text-muted-foreground">Arrastra el archivo o selecciónalo manualmente.</p>
                                <div
                                    class="mt-3 rounded-2xl border border-dashed border-muted p-6 text-center transition hover:border-primary hover:bg-muted/40"
                                    @drop="handleDrop"
                                    @dragover="preventDrag"
                                    @dragenter="preventDrag"
                                >
                                    <div class="flex flex-col items-center gap-3">
                                        <CloudUpload class="h-9 w-9 text-primary" />
                                        <p class="text-base font-semibold text-foreground">Selecciona o arrastra el archivo</p>
                                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Formatos .csv, .txt, .xlsx, .xls</p>
                                        <input
                                            ref="fileInputRef"
                                            type="file"
                                            accept=".csv,.txt,.xlsx,.xls"
                                            class="sr-only"
                                            @change="handleFileSelection"
                                        />
                                        <Button variant="outline" size="sm" class="mt-3" @click="openFileDialog">Seleccionar archivo</Button>
                                        <p class="text-sm text-muted-foreground">{{ selectedFileLabel }}</p>
                                    </div>
                                </div>
                            </div>

                            <div v-if="isImporting" class="rounded-2xl border border-primary/20 bg-primary/5 p-4">
                                <div class="flex items-start gap-3">
                                    <LoaderCircle class="mt-0.5 h-5 w-5 animate-spin text-primary" />
                                    <div>
                                        <p class="text-sm font-semibold text-foreground">Procesando archivo</p>
                                        <p class="text-sm text-muted-foreground">Validando contenido e importando rollos. Espera a que termine.</p>
                                    </div>
                                </div>
                            </div>

                            <div v-if="errorMessage" class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                                {{ errorMessage }}
                            </div>

                            <div v-if="result" class="rounded-2xl border border-muted bg-muted/30 p-4">
                                <p class="text-sm font-semibold text-foreground">Resultado de la importación</p>

                                <div class="mt-3 grid gap-3 sm:grid-cols-3">
                                    <div class="rounded-xl border border-muted bg-background p-3">
                                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Insertados</p>
                                        <p class="mt-1 text-2xl font-semibold text-foreground">{{ result.inserted }}</p>
                                    </div>
                                    <div class="rounded-xl border border-muted bg-background p-3">
                                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Omitidos</p>
                                        <p class="mt-1 text-2xl font-semibold text-foreground">{{ result.skipped }}</p>
                                    </div>
                                    <div class="rounded-xl border border-muted bg-background p-3">
                                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Errores</p>
                                        <p class="mt-1 text-2xl font-semibold text-foreground">{{ result.errors.length }}</p>
                                    </div>
                                </div>

                                <div v-if="result.errors.length > 0" class="mt-4">
                                    <p class="text-sm font-semibold text-foreground">Detalle de errores</p>
                                    <ul class="mt-2 max-h-56 space-y-2 overflow-y-auto pr-2">
                                        <li
                                            v-for="(item, index) in result.errors"
                                            :key="`${item.row ?? 'na'}-${index}`"
                                            class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700"
                                        >
                                            <span class="font-semibold">{{ item.row ? `Fila ${item.row}` : 'Aviso' }}:</span>
                                            {{ item.message }}
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>

            <DialogFooter class="flex flex-col-reverse gap-2 border-t pt-4 md:flex-row md:justify-end">
                <Button variant="outline" size="pill" class="mx-auto flex w-full max-w-[140px] justify-center" @click="close">Cerrar</Button>
                <Button
                    variant="default"
                    type="button"
                    class="mx-auto flex w-full max-w-[160px] items-center justify-center rounded-full px-5 py-2 text-sm font-semibold shadow-sm"
                    :disabled="isImporting || !hasFile"
                    @click="submitImport"
                >
                    {{ isImporting ? 'Importando...' : 'Importar' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
