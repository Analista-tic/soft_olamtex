<script setup lang="ts">
import SelectField from '@/components/SelectField.vue';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { RollLine, ThreadLine, ThreadState } from '@/domain/Production';
import { fetchOpRawMaterials, fetchOpRolls, fetchOpThreads, fetchSettingsParameters, saveOpThreads } from '@/domain/Production';
import type { Option } from '@/domain/shared';
import { Save } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    opId: string | number;
    units: number | null;
    producedColors?: Array<{
        name: string;
        code: string | null;
    }>;
}>();

type LocalThreadRow = {
    uid: string;
    id: string | null;
    color: string;
    colorCode: string;
    threadName: string | number | null;
    gauge: string | number | null;
    brand: string | number | null;
    stitchesPerInch: string | number | null;
    defaultThreadCost: string | number | null;
};

type PrincipalColor = {
    color: string;
    colorCode: string;
};

const isLoading = ref(false);
const isSaving = ref(false);
const isLoadingParameterOptions = ref(false);
const error = ref<string | null>(null);
const saveMessage = ref<string | null>(null);
const rows = ref<LocalThreadRow[]>([]);
const hiddenLines = ref<ThreadLine[]>([]);
const snapshot = ref('');
const threadOptions = ref<Option[]>([]);
const gaugeOptions = ref<Option[]>([]);
const brandOptions = ref<Option[]>([]);
const stitchesPerInchOptions = ref<Option[]>([]);
const fabricTypeOptions = ref<Option[]>([]);
const principalColors = ref<PrincipalColor[]>([]);

function generateUid(): string {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    return `tmp_${Date.now()}_${Math.random().toString(36).slice(2, 10)}`;
}

function normalizeText(value: string | null | undefined): string {
    return String(value ?? '')
        .trim()
        .toLocaleLowerCase('es-CO');
}

function buildColorKey(color: string | null | undefined, colorCode: string | null | undefined): string {
    const normalizedColor = normalizeText(color);
    if (normalizedColor !== '') return normalizedColor;
    return `code:${normalizeText(colorCode)}`;
}

function toNullableText(value: unknown): string | null {
    const clean = String(value ?? '').trim();
    return clean === '' ? null : clean;
}

function toNullableNumber(value: unknown): number | null {
    const clean = String(value ?? '').trim();
    if (clean === '') return null;

    const parsed = Number(clean);
    return Number.isFinite(parsed) ? parsed : null;
}

function mapOption(option: Option): Option {
    const label = String(option.name ?? '').trim();
    return {
        id: option.id,
        name: label !== '' ? label : String(option.id ?? ''),
    };
}

function resolveMainFabricTypeId(): string | number | boolean | null {
    const exact = fabricTypeOptions.value.find((option) => {
        const code = normalizeText((option as Option & { code?: string }).code);
        const name = normalizeText(option.name);
        return code === 'tela principal' || code === 'tela_principal' || name === 'tela principal';
    });

    return exact?.id ?? null;
}

function isMainFabricRow(row: { fabricTypeParameterId?: string | number | boolean | null; fabricTypeParameterName?: string | null }): boolean {
    const expectedId = resolveMainFabricTypeId();
    if (expectedId !== null && row.fabricTypeParameterId !== null && row.fabricTypeParameterId !== undefined) {
        return String(row.fabricTypeParameterId) === String(expectedId);
    }

    return normalizeText(row.fabricTypeParameterName) === 'tela principal';
}

function collectPrincipalColors(
    rawMaterials: Array<{ uid?: string; fabricTypeParameterId?: string | number | boolean | null; fabricTypeParameterName?: string | null }>,
    rollLines: RollLine[],
): PrincipalColor[] {
    const principalRawMaterialUids = new Set(
        rawMaterials
            .filter((row) => isMainFabricRow(row))
            .map((row) => String(row.uid ?? '').trim())
            .filter((uid) => uid !== ''),
    );

    if (principalRawMaterialUids.size === 0) {
        return [];
    }

    const deduped = new Map<string, PrincipalColor>();

    for (const line of rollLines) {
        const rawMaterialUid = String(line.rawMaterialUid ?? '').trim();
        if (rawMaterialUid === '' || !principalRawMaterialUids.has(rawMaterialUid)) {
            continue;
        }

        const color = String(line.color ?? line.colors ?? '').trim();
        const colorCode = String(line.colorCode ?? '').trim();
        if (color === '' && colorCode === '') {
            continue;
        }

        const key = buildColorKey(color, colorCode);
        if (!deduped.has(key)) {
            deduped.set(key, {
                color: color || colorCode,
                colorCode,
            });
        }
    }

    return [...deduped.values()].sort((a, b) => {
        const byColor = a.color.localeCompare(b.color, 'es-CO', { sensitivity: 'base' });
        if (byColor !== 0) return byColor;
        return a.colorCode.localeCompare(b.colorCode, 'es-CO', { sensitivity: 'base' });
    });
}

function mapLineToRow(line: ThreadLine, principalColor: PrincipalColor): LocalThreadRow {
    return {
        uid: generateUid(),
        id: String(line.id ?? '').trim() || null,
        color: principalColor.color,
        colorCode: String(line.colorCode ?? principalColor.colorCode ?? '').trim(),
        threadName: toNullableText(line.threadName),
        gauge: line.gauge !== null && line.gauge !== undefined ? Number(line.gauge) : null,
        brand: toNullableText(line.brand),
        stitchesPerInch: line.stitchesPerInch !== null && line.stitchesPerInch !== undefined ? Number(line.stitchesPerInch) : null,
        defaultThreadCost: line.defaultThreadCost != null ? Number(line.defaultThreadCost) : null,
    };
}

function createEmptyRow(principalColor: PrincipalColor): LocalThreadRow {
    return {
        uid: generateUid(),
        id: null,
        color: principalColor.color,
        colorCode: principalColor.colorCode,
        threadName: null,
        gauge: null,
        brand: null,
        stitchesPerInch: null,
        defaultThreadCost: null,
    };
}

function normalizeRowsForSave(): ThreadLine[] {
    return rows.value.map((row) => ({
        id: row.id ?? '',
        threadName: toNullableText(row.threadName) ?? '',
        gauge: toNullableNumber(row.gauge),
        brand: toNullableText(row.brand),
        color: toNullableText(row.color),
        colorCode: toNullableText(row.colorCode),
        stitchesPerInch: toNullableNumber(row.stitchesPerInch),
        defaultThreadCost: toNullableNumber(row.defaultThreadCost),
    }));
}

function serializeState(): string {
    return JSON.stringify({
        threads: [...normalizeRowsForSave(), ...hiddenLines.value],
    });
}

function applyRows(sourceLines: ThreadLine[], nextPrincipalColors: PrincipalColor[], resetSnapshotState: boolean): void {
    const groupedByColor = new Map<string, ThreadLine[]>();
    const parkedLines: ThreadLine[] = [];

    for (const line of sourceLines) {
        const color = String(line.color ?? '').trim();
        const colorCode = String(line.colorCode ?? '').trim();
        if (color === '' && colorCode === '') {
            parkedLines.push(line);
            continue;
        }

        const key = buildColorKey(color, colorCode);
        const bucket = groupedByColor.get(key) ?? [];
        bucket.push(line);
        groupedByColor.set(key, bucket);
    }

    const nextRows = nextPrincipalColors.map((principalColor) => {
        const key = buildColorKey(principalColor.color, principalColor.colorCode);
        const bucket = groupedByColor.get(key) ?? [];
        const matched = bucket.shift() ?? null;
        if (matched) {
            if (bucket.length === 0) {
                groupedByColor.delete(key);
            } else {
                groupedByColor.set(key, bucket);
            }
            return mapLineToRow(matched, principalColor);
        }

        return createEmptyRow(principalColor);
    });

    for (const leftover of groupedByColor.values()) {
        parkedLines.push(...leftover);
    }

    rows.value = nextRows;
    hiddenLines.value = parkedLines;
    principalColors.value = nextPrincipalColors;

    if (resetSnapshotState) {
        snapshot.value = serializeState();
    }
}

const dirty = computed(() => serializeState() !== snapshot.value);
const canSave = computed(() => !isLoading.value && !isSaving.value && dirty.value);
const hasPrincipalColors = computed(() => rows.value.length > 0);

async function loadThreadParameterOptions(): Promise<void> {
    if (isLoadingParameterOptions.value) return;

    isLoadingParameterOptions.value = true;
    try {
        const data = await fetchSettingsParameters(['type_of_fabric', 'hilo', 'calibre', 'puntada_por_pulgada', 'thread_brands']);
        fabricTypeOptions.value = (data.type_of_fabric ?? []).map(mapOption);
        threadOptions.value = (data.hilo ?? []).map(mapOption);
        gaugeOptions.value = (data.calibre ?? []).map(mapOption);
        stitchesPerInchOptions.value = (data.puntada_por_pulgada ?? []).map(mapOption);
        brandOptions.value = (data.thread_brands ?? []).map(mapOption);
    } catch (err) {
        console.error('Error al cargar parametros de hilos:', err);
        fabricTypeOptions.value = [];
        threadOptions.value = [];
        gaugeOptions.value = [];
        stitchesPerInchOptions.value = [];
        brandOptions.value = [];
    } finally {
        isLoadingParameterOptions.value = false;
    }
}

async function loadThreads(): Promise<void> {
    isLoading.value = true;
    error.value = null;
    saveMessage.value = null;

    try {
        await loadThreadParameterOptions();
        const [threadState, rawMaterials, rollState] = await Promise.all([fetchOpThreads(props.opId), fetchOpRawMaterials(props.opId), fetchOpRolls(props.opId)]);
        const nextPrincipalColors = collectPrincipalColors(rawMaterials, rollState.lines ?? []);
        applyRows(Array.isArray(threadState.lines) ? threadState.lines : [], nextPrincipalColors, true);
    } catch (err) {
        console.error('Error al cargar hilos:', err);
        error.value = 'No se pudo cargar la informacion de hilos.';
        rows.value = [];
        hiddenLines.value = [];
        principalColors.value = [];
        snapshot.value = serializeState();
    } finally {
        isLoading.value = false;
    }
}

async function refreshPrincipalColorsPreservingLocalState(): Promise<void> {
    try {
        await loadThreadParameterOptions();
        const [rawMaterials, rollState] = await Promise.all([fetchOpRawMaterials(props.opId), fetchOpRolls(props.opId)]);
        const nextPrincipalColors = collectPrincipalColors(rawMaterials, rollState.lines ?? []);
        applyRows([...normalizeRowsForSave(), ...hiddenLines.value], nextPrincipalColors, false);
    } catch (err) {
        console.error('Error al sincronizar colores principales de hilos:', err);
    }
}

async function handleSave(): Promise<void> {
    if (!canSave.value) return;

    isSaving.value = true;
    error.value = null;
    saveMessage.value = null;

    try {
        const state: ThreadState = await saveOpThreads(props.opId, {
            threads: [...normalizeRowsForSave(), ...hiddenLines.value],
            deletedIds: [],
        });
        applyRows(Array.isArray(state.lines) ? state.lines : [], principalColors.value, true);
        saveMessage.value = 'Hilos guardados.';
    } catch (err) {
        console.error('Error al guardar hilos:', err);
        error.value = 'No se pudieron guardar los hilos.';
    } finally {
        isSaving.value = false;
    }
}

async function handleRawMaterialSaved(event: Event): Promise<void> {
    const customEvent = event as CustomEvent<{ opId?: string | number }>;
    const opId = customEvent.detail?.opId;
    if (opId === undefined || opId === null || String(opId) !== String(props.opId)) return;

    await refreshPrincipalColorsPreservingLocalState();
}

watch(
    () => props.opId,
    () => {
        void loadThreads();
    },
    { immediate: true },
);

onMounted(() => {
    if (typeof window !== 'undefined') {
        window.addEventListener('tds-raw-material-saved', handleRawMaterialSaved as EventListener);
    }
});

onBeforeUnmount(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('tds-raw-material-saved', handleRawMaterialSaved as EventListener);
    }
});
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-col gap-2 p-2">
            <h2 class="text-lg font-semibold text-foreground">Hilos</h2>
            <p class="text-sm text-muted-foreground">
                Los colores activos se calculan desde los rollos asociados a los trazos marcados como Tela principal en Materia prima.
            </p>
        </div>

        <div v-if="isLoading" class="rounded-lg border-0 px-4 py-6 text-sm text-muted-foreground shadow-none">Cargando hilos...</div>

        <template v-else>
            <div
                v-if="!hasPrincipalColors"
                class="rounded-lg border border-dashed border-border bg-background px-4 py-8 text-center text-sm text-muted-foreground"
            >
                Selecciona una tela principal en Materia prima para listar los colores disponibles.
            </div>

            <div v-else class="space-y-4">
                <div class="rounded-lg border border-border bg-background/70 p-3 text-sm text-muted-foreground">
                    Cada fila corresponde a un color unico detectado en la materia prima principal. Los valores guardados se conservan por color.
                </div>

                <div class="overflow-x-auto rounded-md border border-border">
                    <Table>
                        <TableHeader class="bg-background">
                            <TableRow>
                                <TableHead class="min-w-[180px] text-left">Color</TableHead>
                                <TableHead class="min-w-[140px] text-left">Codigo</TableHead>
                                <TableHead class="min-w-[180px] text-left">Hilo</TableHead>
                                <TableHead class="min-w-[140px] text-left">Calibre</TableHead>
                                <TableHead class="min-w-[180px] text-left">Marca</TableHead>
                                <TableHead class="min-w-[180px] text-left">Puntada por pulgada</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="(row, index) in rows" :key="row.uid" class="transition-colors hover:bg-muted/30">
                                <TableCell>
                                    <div class="space-y-1">
                                        <div class="font-medium text-foreground">{{ row.color }}</div>
                                        <div v-if="row.colorCode" class="text-xs text-muted-foreground">Referencia rollo: {{ row.colorCode }}</div>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <Input v-model="rows[index].colorCode" placeholder="Codigo" :disabled="isSaving" />
                                </TableCell>
                                <TableCell>
                                    <SelectField
                                        v-model="rows[index].threadName"
                                        label=""
                                        placeholder="Selecciona hilo"
                                        :options="threadOptions"
                                        :disabled="isSaving || isLoadingParameterOptions"
                                        :searchable="true"
                                        :clearable="true"
                                    />
                                </TableCell>
                                <TableCell>
                                    <SelectField
                                        v-model="rows[index].gauge"
                                        label=""
                                        placeholder="Selecciona calibre"
                                        :options="gaugeOptions"
                                        :disabled="isSaving || isLoadingParameterOptions"
                                        :searchable="true"
                                        :clearable="true"
                                    />
                                </TableCell>
                                <TableCell>
                                    <SelectField
                                        v-model="rows[index].brand"
                                        label=""
                                        placeholder="Selecciona marca"
                                        :options="brandOptions"
                                        :disabled="isSaving || isLoadingParameterOptions"
                                        :searchable="true"
                                        :allow-custom="true"
                                        :clearable="true"
                                        custom-option-label='Crear "{value}"'
                                    />
                                </TableCell>
                                <TableCell>
                                    <SelectField
                                        v-model="rows[index].stitchesPerInch"
                                        label=""
                                        placeholder="Selecciona puntada"
                                        :options="stitchesPerInchOptions"
                                        :disabled="isSaving || isLoadingParameterOptions"
                                        :searchable="true"
                                        :clearable="true"
                                    />
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <div class="flex items-center justify-end">
                    <Button type="button" class="h-9 rounded-full px-4" :disabled="!canSave" @click="handleSave">
                        <Save class="mr-1 h-4 w-4" />
                        {{ isSaving ? 'Guardando...' : 'Guardar cambios' }}
                    </Button>
                </div>

                <p v-if="saveMessage" class="text-sm text-emerald-700">{{ saveMessage }}</p>
            </div>
        </template>

        <div v-if="error" class="rounded-lg border-0 bg-destructive/10 px-4 py-3 text-sm text-destructive shadow-none">
            {{ error }}
        </div>
    </div>
</template>
