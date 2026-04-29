<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import axios from 'axios';
import { ClipboardList, CloudUpload, LoaderCircle } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

type ImportErrorItem = {
    row: number | null;
    message: string;
};

type ImportResult = {
    inserted: number;
    skipped: number;
    errors: ImportErrorItem[];
};

const props = defineProps<{
    modelValue: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: boolean): void;
    (e: 'imported', payload: { inserted: number; skipped: number; errors: string[] }): void;
}>();

const expectedHeaders = [
    'N° ROLLO',
    'UBICACIÓN',
    'BASE TELA',
    'TELA',
    'COLOR',
    'CÓDIGO COLOR',
    'ANCHO (m)',
    'CANTIDAD (m)',
    'SALIDA PARCIAL',
    'DISPONIBILIDAD REAL',
    'PROVEEDOR',
    'N° PEDIDO',
    'FECHA INGRESO',
    'REFERENCIA ÍTEM',
];

const recommendations = [
    'Descarga la plantilla y conserva exactamente el orden y nombre de los headers.',
    'Usa valores numericos limpios para ancho, cantidad, salida parcial y salida actual.',
    'Registra la fecha en entry_date con un formato consistente, idealmente YYYY-MM-DD.',
];

const isOpen = ref(props.modelValue);
const file = ref<File | null>(null);
const isImporting = ref(false);
const errorMessage = ref('');
const successMessage = ref('');
const result = ref<ImportResult | null>(null);
const fileInputRef = ref<HTMLInputElement | null>(null);

watch(
    () => props.modelValue,
    (value) => {
        isOpen.value = value;
        if (!value) {
            resetState();
        }
    },
);

watch(isOpen, (value) => {
    emit('update:modelValue', value);
    if (!value) {
        resetState();
    }
});

const selectedFileLabel = computed(() => file.value?.name ?? 'Ningun archivo seleccionado');
const hasFile = computed(() => Boolean(file.value));

function resetState() {
    file.value = null;
    isImporting.value = false;
    errorMessage.value = '';
    successMessage.value = '';
    result.value = null;

    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }
}

function close() {
    isOpen.value = false;
}

function openFileDialog() {
    fileInputRef.value?.click();
}

function downloadTemplate() {
    // Generar CSV como alternativa sin xlsx
    const csv = expectedHeaders.join(',') + '\n';
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'plantilla_importacion_rollos.csv';
    link.click();
    window.URL.revokeObjectURL(url);
}

function preventDrag(event: DragEvent) {
    event.preventDefault();
}

async function handleDrop(event: DragEvent) {
    event.preventDefault();

    const droppedFile = event.dataTransfer?.files?.[0] ?? null;
    if (!droppedFile) return;

    await setSelectedFile(droppedFile);
}

async function handleFileSelection(event: Event) {
    const target = event.target as HTMLInputElement;
    const selectedFile = target.files?.[0] ?? null;

    if (!selectedFile) {
        file.value = null;
        return;
    }

    await setSelectedFile(selectedFile);
}

async function setSelectedFile(selectedFile: File) {
    errorMessage.value = '';
    successMessage.value = '';
    result.value = null;

    const validationError = await validateFile(selectedFile);
    if (validationError) {
        file.value = null;
        errorMessage.value = validationError;

        if (fileInputRef.value) {
            fileInputRef.value.value = '';
        }

        return;
    }

    file.value = selectedFile;
}

async function validateFile(selectedFile: File): Promise<string | null> {
    const lowerName = selectedFile.name.toLowerCase();
    if (!lowerName.endsWith('.xlsx') && !lowerName.endsWith('.xls')) {
        return 'El archivo debe tener extension .xlsx o .xls.';
    }

    // La validación completa de estructura y headers se realiza en el backend
    // Aquí solo validamos el tamaño máximo (10MB)
    const maxSize = 10 * 1024 * 1024; // 10MB
    if (selectedFile.size > maxSize) {
        return 'El archivo no debe exceder 10MB.';
    }

    return null;
}



function toRowNumber(value: unknown): number | null {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
}

function parseStringError(value: string): ImportErrorItem {
    const trimmed = value.trim();
    const match = trimmed.match(/(?:fila|row)\s*(\d+)/i);

    if (!match) {
        return { row: null, message: trimmed };
    }

    const row = toRowNumber(match[1]);
    const message = trimmed.replace(/^(?:fila|row)\s*\d+\s*[:\-]?\s*/i, '').trim();

    return {
        row,
        message: message || trimmed,
    };
}

function normalizeImportErrors(rawErrors: unknown): ImportErrorItem[] {
    if (!Array.isArray(rawErrors)) return [];

    return rawErrors
        .map((entry) => {
            if (typeof entry === 'string') {
                return parseStringError(entry);
            }

            if (entry && typeof entry === 'object') {
                const record = entry as Record<string, unknown>;
                const row = 'row' in record ? toRowNumber(record.row) : 'fila' in record ? toRowNumber(record.fila) : null;
                const message = 'message' in record ? String(record.message ?? '').trim() : 'mensaje' in record ? String(record.mensaje ?? '').trim() : '';

                if (message) {
                    return { row, message };
                }
            }

            if (entry == null) return null;

            return {
                row: null,
                message: String(entry),
            };
        })
        .filter((entry): entry is ImportErrorItem => Boolean(entry?.message));
}

function formatErrorForEmit(item: ImportErrorItem) {
    return item.row ? `Fila ${item.row}: ${item.message}` : item.message;
}

async function submitImport() {
    if (!file.value) {
        errorMessage.value = 'Selecciona un archivo Excel antes de importar.';
        return;
    }

    const validationError = await validateFile(file.value);
    if (validationError) {
        errorMessage.value = validationError;
        return;
    }

    isImporting.value = true;
    errorMessage.value = '';
    successMessage.value = '';
    result.value = null;

    const formData = new FormData();
    formData.append('file', file.value);

    try {
        const { data } = await axios.post('/api/fabric-rolls/import', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        const normalizedErrors = normalizeImportErrors(data?.errors);

        result.value = {
            inserted: Number(data?.inserted ?? 0),
            skipped: Number(data?.skipped ?? 0),
            errors: normalizedErrors,
        };

        successMessage.value = 'Importacion procesada correctamente.';

        emit('imported', {
            inserted: result.value.inserted,
            skipped: result.value.skipped,
            errors: normalizedErrors.map(formatErrorForEmit),
        });
    } catch (error: any) {
        const responseData = error?.response?.data;
        const normalizedErrors = normalizeImportErrors(responseData?.errors);
        const inserted = Number(responseData?.inserted ?? 0);
        const skipped = Number(responseData?.skipped ?? 0);

        if (normalizedErrors.length > 0 || inserted > 0 || skipped > 0) {
            result.value = {
                inserted,
                skipped,
                errors: normalizedErrors,
            };
        }

        if (inserted > 0 || skipped > 0) {
            emit('imported', {
                inserted,
                skipped,
                errors: normalizedErrors.map(formatErrorForEmit),
            });
        }

        if (Array.isArray(responseData?.errors) && responseData.errors.length > 0) {
            errorMessage.value = responseData?.message || 'La importacion termino con errores.';
        } else if (responseData?.errors?.file?.[0]) {
            errorMessage.value = responseData.errors.file[0];
        } else {
            errorMessage.value = responseData?.message || 'No se pudo importar el archivo.';
        }
    } finally {
        isImporting.value = false;
    }
}
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent class="flex flex-col w-full max-w-2xl max-h-[90vh]">
            <DialogHeader class="space-y-1 border-b border-sidebar-border/70 bg-muted/20 px-5 py-4 dark:border-sidebar-border">
                <div class="flex items-center gap-3">
                    <ClipboardList class="h-6 w-6 text-primary" />
                    <DialogTitle class="text-lg font-semibold">Importacion de rollos</DialogTitle>
                </div>
                <p class="pb-5 text-sm text-muted-foreground">Sube un archivo Excel para registrar rollos de tela de forma masiva.</p>
            </DialogHeader>

            <div class="no-scrollbar -mx-6 overflow-y-auto px-6 flex-1 min-h-0 py-4">
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
                                <p class="text-sm font-semibold text-foreground">1. Descarga la plantilla oficial</p>
                                <p class="text-sm text-muted-foreground">
                                    Utiliza la plantilla para conservar exactamente los headers esperados.
                                </p>
                                <div class="mt-3">
                                    <Button variant="outline" class="w-full justify-center gap-2" @click="downloadTemplate">
                                        Descargar plantilla (CSV)
                                    </Button>
                                </div>
                            </div>

                            <div>
                                <p class="text-sm font-semibold text-foreground">2. Sube tu archivo</p>
                                <p class="text-sm text-muted-foreground">Arrastra el archivo .xlsx o seleccionalo manualmente.</p>
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
                                        <Button variant="outline" size="sm" class="mt-3" @click="openFileDialog">Seleccionar archivo</Button>
                                        <p class="text-sm text-muted-foreground">{{ selectedFileLabel }}</p>
                                    </div>
                                </div>
                            </div>

                            <div v-if="isImporting" class="rounded-2xl border border-primary/20 bg-primary/5 p-4">
                                <div class="flex items-start gap-3">
                                    <LoaderCircle class="mt-0.5 h-5 w-5 animate-spin text-primary" />
                                    <div>
                                        <p class="text-sm font-semibold text-foreground">Procesando archivo Excel</p>
                                        <p class="text-sm text-muted-foreground">Validando contenido e importando rollos. Espera a que termine.</p>
                                    </div>
                                </div>
                            </div>

                            <div v-if="result" class="rounded-2xl border border-muted bg-muted/30 p-4">
                                <p class="text-sm font-semibold text-foreground">Resultado de la importacion</p>

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
                                            <span class="font-semibold">
                                                {{ item.row ? `Fila ${item.row}` : 'Fila no identificada' }}:
                                            </span>
                                            {{ item.message }}
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <p v-if="errorMessage" class="text-sm text-red-600">{{ errorMessage }}</p>
                                <p v-if="successMessage" class="text-sm text-emerald-600">{{ successMessage }}</p>
                            </div>
                        </div>
                    </section>
                </div>
            </div>

            <DialogFooter class="flex-shrink-0 border-t pt-4 flex flex-col-reverse gap-2 md:flex-row md:justify-end">
                <div class="w-full md:w-auto">
                    <Button variant="outline" size="pill" class="mx-auto flex w-full max-w-[140px] justify-center" @click="close">
                        Cancelar
                    </Button>
                </div>
                <div class="w-full md:w-auto">
                    <Button
                        variant="default"
                        type="button"
                        class="mx-auto flex w-full max-w-[160px] items-center justify-center rounded-full px-5 py-2 text-sm font-semibold shadow-sm"
                        :disabled="isImporting || !hasFile"
                        @click="submitImport"
                    >
                        {{ isImporting ? 'Procesando...' : 'Importar archivo' }}
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
