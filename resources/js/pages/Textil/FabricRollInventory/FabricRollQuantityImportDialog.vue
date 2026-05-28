<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import axios from 'axios';
import { ClipboardList, CloudUpload, LoaderCircle } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

type ImportErrorItem = { row: number | null; message: string };
type ImportResult = { updated: number; skipped: number; errors: ImportErrorItem[] };

const props = defineProps<{ modelValue: boolean }>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: boolean): void;
    (e: 'imported', payload: { message: string; updated: number; skipped: number; errors: string[] }): void;
}>();

const recommendations = [
    'Descarga la plantilla y conserva el orden exacto de las columnas.',
    'Solo se actualizan rollos existentes. Los N° ROLLO no encontrados se omiten.',
    'REFERENCIA ÍTEM y LOTE son opcionales.',
    'DISPONIBILIDAD REAL = CANTIDAD - salida real acumulada.',
];

const isOpen = ref(props.modelValue);
const file = ref<File | null>(null);
const isImporting = ref(false);
const result = ref<ImportResult | null>(null);
const fileInputRef = ref<HTMLInputElement | null>(null);

watch(() => props.modelValue, (v) => { isOpen.value = v; if (!v) resetState(); });
watch(isOpen, (v) => { emit('update:modelValue', v); if (!v) resetState(); });

const selectedFileLabel = computed(() => file.value?.name ?? 'Ningún archivo seleccionado');
const hasFile = computed(() => Boolean(file.value));

function resetState() {
    file.value = null;
    isImporting.value = false;
    result.value = null;
    if (fileInputRef.value) fileInputRef.value.value = '';
}

function close() { isOpen.value = false; }
function openFileDialog() { fileInputRef.value?.click(); }
function downloadTemplate() { window.open('/kardex-telas/inventario-rollos/plantilla-cantidades', '_blank'); }

function handleFileSelection(event: Event) {
    const target = event.target as HTMLInputElement;
    file.value = target.files?.length ? target.files[0] : null;
    result.value = null;
}

function handleDrop(event: DragEvent) {
    event.preventDefault();
    const files = event.dataTransfer?.files ?? null;
    if (files?.length) { file.value = files[0]; result.value = null; }
}

function preventDrag(event: DragEvent) { event.preventDefault(); }

function normalizeErrors(raw: unknown): ImportErrorItem[] {
    if (!Array.isArray(raw)) return [];
    return raw
        .map((e) => {
            if (typeof e === 'string') return { row: null, message: e };
            if (e && typeof e === 'object') {
                const r = e as Record<string, unknown>;
                return { row: r.row != null ? Number(r.row) : null, message: String(r.message ?? '') };
            }
            return null;
        })
        .filter((e): e is ImportErrorItem => Boolean(e?.message));
}

async function submitImport() {
    if (!file.value) return;

    isImporting.value = true;
    result.value = null;

    const formData = new FormData();
    formData.append('file', file.value);

    try {
        const { data } = await axios.post('/kardex-telas/inventario-rollos/importar-cantidades', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        const errors = normalizeErrors(data?.errors);
        result.value = { updated: Number(data?.updated ?? 0), skipped: Number(data?.skipped ?? 0), errors };

        emit('imported', {
            message: data?.message ?? 'Importación completada.',
            updated: result.value.updated,
            skipped: result.value.skipped,
            errors: errors.map((e) => (e.row ? `Fila ${e.row}: ${e.message}` : e.message)),
        });

        close();
    } catch (error: any) {
        const d = error?.response?.data;
        const errors = normalizeErrors(d?.errors);
        result.value = { updated: Number(d?.updated ?? 0), skipped: Number(d?.skipped ?? 0), errors };
        emit('imported', {
            message: d?.message ?? 'Error al importar.',
            updated: result.value.updated,
            skipped: result.value.skipped,
            errors: errors.map((e) => (e.row ? `Fila ${e.row}: ${e.message}` : e.message)),
        });
    } finally {
        isImporting.value = false;
    }
}
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent class="flex w-full max-w-2xl flex-col">
            <DialogHeader class="space-y-1 border-b border-sidebar-border/70 bg-muted/20 px-5 py-4 dark:border-sidebar-border">
                <div class="flex items-center gap-3">
                    <ClipboardList class="h-6 w-6 text-primary" />
                    <DialogTitle class="text-lg font-semibold">Importar cantidades de rollos</DialogTitle>
                </div>
                <p class="pb-5 text-sm text-muted-foreground">Actualiza cantidades e inventario de rollos existentes desde Excel.</p>
            </DialogHeader>

            <div class="no-scrollbar -mx-4 max-h-[65vh] flex-1 min-h-0 overflow-y-auto px-4">
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
                                <p class="text-sm font-semibold text-foreground">1. Descarga la plantilla</p>
                                <div class="mt-3">
                                    <Button variant="outline" class="w-full justify-center gap-2" @click="downloadTemplate">
                                        Descargar plantilla
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
                                        <p class="text-sm text-muted-foreground">Actualizando cantidades. Espera a que termine.</p>
                                    </div>
                                </div>
                            </div>

                            <div v-if="result" class="rounded-2xl border border-muted bg-muted/30 p-4">
                                <p class="text-sm font-semibold text-foreground">Resultado</p>
                                <div class="mt-3 grid gap-3 sm:grid-cols-3">
                                    <div class="rounded-xl border border-muted bg-background p-3">
                                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Actualizados</p>
                                        <p class="mt-1 text-2xl font-semibold text-foreground">{{ result.updated }}</p>
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
                <Button variant="outline" size="pill" class="mx-auto flex w-full max-w-[140px] justify-center" @click="close">Cancelar</Button>
                <Button
                    variant="default"
                    type="button"
                    class="mx-auto flex w-full max-w-[180px] items-center justify-center rounded-full px-5 py-2 text-sm font-semibold shadow-sm"
                    :disabled="isImporting || !hasFile"
                    @click="submitImport"
                >
                    {{ isImporting ? 'Procesando...' : 'Actualizar cantidades' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
