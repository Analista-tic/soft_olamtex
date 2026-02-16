<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Plus, Save, Trash } from 'lucide-vue-next';
import type { ThreadLine, ThreadState } from '@/domain/Production/TechnicalDataSheet';
import { fetchOpThreads, saveOpThreads } from '@/domain/Production/TechnicalDataSheet';

const props = defineProps<{
    opId: string | number;
    units: number | null;
}>();

type LocalThreadRow = {
    uid: string;
    id: string | null;
    threadName: string;
    gauge: string;
    brand: string;
    color: string;
    colorCode: string;
    stitchesPerInch: string;
};

const isLoading = ref(false);
const isSaving = ref(false);
const error = ref<string | null>(null);
const saveMessage = ref<string | null>(null);
const rows = ref<LocalThreadRow[]>([]);
const deletedIds = ref<string[]>([]);
const snapshot = ref('');

function generateUid(): string {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    return `tmp_${Date.now()}_${Math.random().toString(36).slice(2, 10)}`;
}

function formatCOP(value: number): string {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        maximumFractionDigits: 0,
    }).format(Number(value || 0)).replace(/\u00A0/g, ' ');
}

const totalHilos = computed(() => 0);
const costoHilos = computed(() => formatCOP(totalHilos.value));

function toNullableText(value: string): string | null {
    const clean = value.trim();
    return clean === '' ? null : clean;
}

function toNullableNumber(value: string): number | null {
    const clean = String(value ?? '').trim();
    if (clean === '') return null;

    const parsed = Number(clean);
    return Number.isFinite(parsed) ? parsed : null;
}

function mapApiLineToLocal(line: ThreadLine): LocalThreadRow {
    return {
        uid: generateUid(),
        id: line.id ? String(line.id) : null,
        threadName: String(line.threadName ?? ''),
        gauge: line.gauge !== null && line.gauge !== undefined ? String(line.gauge) : '',
        brand: String(line.brand ?? ''),
        color: String(line.color ?? ''),
        colorCode: String(line.colorCode ?? ''),
        stitchesPerInch: line.stitchesPerInch !== null && line.stitchesPerInch !== undefined ? String(line.stitchesPerInch) : '',
    };
}

function createEmptyRow(): LocalThreadRow {
    return {
        uid: generateUid(),
        id: null,
        threadName: '',
        gauge: '',
        brand: '',
        color: '',
        colorCode: '',
        stitchesPerInch: '',
    };
}

function normalizeRowsForSave(): ThreadLine[] {
    return rows.value.map((row) => ({
        id: row.id ?? '',
        threadName: row.threadName.trim(),
        gauge: toNullableNumber(row.gauge),
        brand: toNullableText(row.brand),
        color: toNullableText(row.color),
        colorCode: toNullableText(row.colorCode),
        stitchesPerInch: toNullableNumber(row.stitchesPerInch),
    }));
}

function serializeState(): string {
    return JSON.stringify({
        threads: normalizeRowsForSave(),
        deletedIds: deletedIds.value.slice().sort(),
    });
}

const dirty = computed(() => serializeState() !== snapshot.value);
const hasInvalidRows = computed(() => rows.value.some((row) => row.threadName.trim() === ''));
const canSave = computed(() => !isLoading.value && !isSaving.value && dirty.value && !hasInvalidRows.value);

function applyState(state: ThreadState): void {
    const loadedRows = Array.isArray(state.lines) ? state.lines.map(mapApiLineToLocal) : [];
    rows.value = loadedRows.length > 0 ? loadedRows : [createEmptyRow()];
    deletedIds.value = [];
    snapshot.value = serializeState();
}

function addThread(): void {
    rows.value.push(createEmptyRow());
}

function removeThread(uid: string): void {
    const target = rows.value.find((row) => row.uid === uid);
    if (!target) return;

    if (target.id && !deletedIds.value.includes(target.id)) {
        deletedIds.value.push(target.id);
    }

    rows.value = rows.value.filter((row) => row.uid !== uid);
}

async function loadThreads(): Promise<void> {
    isLoading.value = true;
    error.value = null;
    saveMessage.value = null;

    try {
        const state = await fetchOpThreads(props.opId);
        applyState(state);
    } catch (err) {
        console.error('Error al cargar hilos:', err);
        error.value = 'No se pudo cargar la informacion de hilos.';
        rows.value = [createEmptyRow()];
        deletedIds.value = [];
        snapshot.value = serializeState();
    } finally {
        isLoading.value = false;
    }
}

async function handleSave(): Promise<void> {
    if (!canSave.value) return;

    isSaving.value = true;
    error.value = null;
    saveMessage.value = null;

    try {
        const state = await saveOpThreads(props.opId, {
            threads: normalizeRowsForSave(),
            deletedIds: [...deletedIds.value],
        });
        applyState(state);
        saveMessage.value = 'Hilos guardados.';
    } catch (err) {
        console.error('Error al guardar hilos:', err);
        error.value = 'No se pudieron guardar los hilos.';
    } finally {
        isSaving.value = false;
    }
}

watch(
    () => props.opId,
    () => {
        void loadThreads();
    },
    { immediate: true },
);
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-col gap-3 p-2 md:flex-row md:items-start md:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-foreground">Hilos</h2>
                <p class="text-sm text-muted-foreground">Costo estimado informativo por prenda. No afecta inventario.</p>
            </div>
            <div class="w-fit rounded-full border border-border bg-muted/30 px-3 py-1.5 text-sm font-medium text-foreground">
                Costo Hilos: {{ costoHilos }}
            </div>
        </div>

        <div v-if="isLoading" class="rounded-lg border-0 px-4 py-6 text-sm text-muted-foreground shadow-none">
            Cargando hilos...
        </div>

        <template v-else>
            <div
                v-if="rows.length === 0"
                class="rounded-lg border border-dashed border-border bg-background px-4 py-8 text-center"
            >
                <p class="text-sm font-medium text-foreground">No hay hilos registrados. Agrega una linea para iniciar.</p>
            </div>

            <div v-else class="max-h-[460px] overflow-auto rounded-md border border-border">
                <Table>
                    <TableHeader class="sticky top-0 z-10 bg-background">
                        <TableRow>
                            <TableHead class="min-w-[170px] text-left">Hilo</TableHead>
                            <TableHead class="min-w-[120px] text-left">Calibre</TableHead>
                            <TableHead class="min-w-[160px] text-left">Marca</TableHead>
                            <TableHead class="min-w-[140px] text-left">Color</TableHead>
                            <TableHead class="min-w-[140px] text-left">Cod. color</TableHead>
                            <TableHead class="min-w-[180px] text-left">Puntada por pulgada</TableHead>
                            <TableHead class="w-[80px] text-center">Accion</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="(row, index) in rows" :key="row.uid" class="group transition-colors hover:bg-muted/30">
                            <TableCell>
                                <Input
                                    v-model="rows[index].threadName"
                                    placeholder="Nombre del hilo"
                                    :disabled="isSaving"
                                />
                            </TableCell>
                            <TableCell>
                                <Input
                                    v-model="rows[index].gauge"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    placeholder="0"
                                    :disabled="isSaving"
                                />
                            </TableCell>
                            <TableCell>
                                <Input
                                    v-model="rows[index].brand"
                                    placeholder="Marca"
                                    :disabled="isSaving"
                                />
                            </TableCell>
                            <TableCell>
                                <Input
                                    v-model="rows[index].color"
                                    placeholder="Color"
                                    :disabled="isSaving"
                                />
                            </TableCell>
                            <TableCell>
                                <Input
                                    v-model="rows[index].colorCode"
                                    placeholder="Codigo"
                                    :disabled="isSaving"
                                />
                            </TableCell>
                            <TableCell>
                                <Input
                                    v-model="rows[index].stitchesPerInch"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    placeholder="0"
                                    :disabled="isSaving"
                                />
                            </TableCell>
                            <TableCell class="text-center">
                                <button
                                    type="button"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-full text-muted-foreground transition hover:bg-muted hover:text-destructive disabled:pointer-events-none disabled:opacity-40"
                                    :disabled="isSaving"
                                    @click="removeThread(row.uid)"
                                >
                                    <Trash class="h-4 w-4" />
                                </button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <div class="mt-4 flex items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        class="h-9 rounded-full border border-input bg-background px-4 hover:bg-muted/40"
                        :disabled="isSaving"
                        @click="addThread"
                    >
                        <Plus class="mr-1 h-4 w-4" />
                        Agregar hilo
                    </Button>
                    <Button
                        type="button"
                        class="h-9 rounded-full px-4"
                        :disabled="!canSave"
                        @click="handleSave"
                    >
                        <Save class="mr-1 h-4 w-4" />
                        {{ isSaving ? 'Guardando...' : 'Guardar cambios' }}
                    </Button>
                </div>

                <div class="rounded-lg border border-primary/30 bg-primary/5 px-3 py-2">
                    <div class="flex items-center gap-2">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Total hilos</p>
                        <p class="text-sm font-semibold tabular-nums text-foreground">{{ costoHilos }}</p>
                    </div>
                </div>
            </div>

            <p v-if="saveMessage" class="text-sm text-emerald-700">{{ saveMessage }}</p>
        </template>

        <div v-if="error" class="rounded-lg border-0 bg-destructive/10 px-4 py-3 text-sm text-destructive shadow-none">
            {{ error }}
        </div>
    </div>
</template>
