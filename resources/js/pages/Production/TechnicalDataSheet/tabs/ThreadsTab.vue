<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import SelectField from '@/components/SelectField.vue';
import { useGlobalAlert } from '@/composables/useGlobalAlert';
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
    readonly?: boolean;
}>();

type LocalThreadRow = {
    uid: string;
    id: string | null;
    color: string;
    colorCode: string;
    threadCode: string | null;
    processType: string;
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

const PROCESS_TYPES = ['Pespunte/Amarre', 'Filetes', 'Ojales', 'Presilla', 'Puntada Seguridad'] as const;

const isLoading = ref(false);
const isSaving = ref(false);
const isLoadingParameterOptions = ref(false);
const error = ref<string | null>(null);
const rows = ref<LocalThreadRow[]>([]);
const hiddenLines = ref<ThreadLine[]>([]);
const snapshot = ref('');
const threadOptions = ref<Option[]>([]);
const gaugeOptions = ref<Option[]>([]);
const brandOptions = ref<Option[]>([]);
const stitchesPerInchOptions = ref<Option[]>([]);
const fabricTypeOptions = ref<Option[]>([]);
const principalColors = ref<PrincipalColor[]>([]);
const { successMessage, errorMessage, errorType, success, error: showError, clear } = useGlobalAlert();

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

// Dado un valor (id o nombre), devuelve el nombre legible de la opción para guardar en backend
function resolveOptionName(options: Option[], value: unknown): string | null {
    if (value === null || value === undefined || String(value).trim() === '') return null;
    const str = String(value).trim();
    const option = options.find((o) => String(o.id) === str);
    return option ? option.name : str;
}

// Dado un valor guardado (id o nombre), devuelve el id numérico/texto de la opción para el SelectField
function resolveOptionId(options: Option[], value: unknown): string | number | null {
    if (value === null || value === undefined || String(value).trim() === '') return null;
    const str = String(value).trim();
    const byId = options.find((o) => String(o.id) === str);
    if (byId) return typeof byId.id === 'boolean' ? str : byId.id;
    const byName = options.find((o) => normalizeText(o.name) === normalizeText(str));
    if (byName) return typeof byName.id === 'boolean' ? str : byName.id;
    return value as string | number;
}

function resolveMainFabricTypeId(): string | number | boolean | null {
    const exact = fabricTypeOptions.value.find((option) => {
        const code = normalizeText((option as Option & { code?: string }).code);
        const name = normalizeText(option.name);
        return code === 'tela principal' || code === 'tela_principal' || name === 'tela principal';
    });

    return exact?.id ?? 'PRINCIPAL';
}

function isMainFabricRow(row: { fabricTypeParameterId?: string | number | boolean | null; fabricTypeParameterName?: string | null }): boolean {
    const normalizedId = normalizeText(
        row.fabricTypeParameterId === null || row.fabricTypeParameterId === undefined
            ? null
            : String(row.fabricTypeParameterId),
    );
    if (normalizedId === 'principal' || normalizedId === 'tela principal') {
        return true;
    }

    const expectedId = resolveMainFabricTypeId();
    if (expectedId !== null && row.fabricTypeParameterId !== null && row.fabricTypeParameterId !== undefined) {
        return String(row.fabricTypeParameterId) === String(expectedId);
    }

    const normalizedName = normalizeText(row.fabricTypeParameterName);
    return normalizedName === 'tela principal' || normalizedName === 'principal';
}

function collectPrincipalColors(
    rawMaterials: Array<{
        uid?: string;
        telaItemId?: string | number | null;
        tela?: string | null;
        fabricTypeParameterId?: string | number | boolean | null;
        fabricTypeParameterName?: string | null;
    }>,
    rollLines: RollLine[],
): PrincipalColor[] {
    const principalRows = rawMaterials.filter((row) => isMainFabricRow(row));
    const principalRawMaterialUids = new Set(
        principalRows
            .map((row) => String(row.uid ?? '').trim())
            .filter((uid) => uid !== ''),
    );
    const principalItemIds = new Set(
        principalRows
            .map((row) => Number(row.telaItemId ?? 0))
            .filter((id) => Number.isFinite(id) && id > 0),
    );
    const principalFabricNames = new Set(
        principalRows
            .map((row) => normalizeText(row.tela))
            .filter((name) => name !== ''),
    );

    // Si no hay principal, continuaremos con fallback más abajo

    const deduped = new Map<string, PrincipalColor>();
    const matchedLines: RollLine[] = [];

    for (const line of rollLines) {
        const rawMaterialUid = String(line.rawMaterialUid ?? '').trim();
        const fabricItemId = Number(line.fabricItemId ?? 0);
        const lineType = normalizeText((line as unknown as { type?: string | null }).type ?? null);
        const lineFabricName = normalizeText(line.fabricName ?? null);
        const byUid = rawMaterialUid !== '' && principalRawMaterialUids.has(rawMaterialUid);
        const byItem = Number.isFinite(fabricItemId) && fabricItemId > 0 && principalItemIds.has(fabricItemId);
        const byType = lineType === 'principal';
        const byFabricName = lineFabricName !== '' && principalFabricNames.has(lineFabricName);

        if (!byUid && !byItem && !byType && !byFabricName) {
            continue;
        }

        matchedLines.push(line);

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

    if (deduped.size === 0) {
        const principalRollLines = rollLines.filter((line) => normalizeText((line as unknown as { type?: string | null }).type ?? null) === 'principal');
        const fallbackLines = principalRollLines.length > 0
            ? principalRollLines
            : matchedLines.length > 0
                ? matchedLines
                : rollLines;

        for (const line of fallbackLines) {
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
    }

    // Siempre agregar colores de todos los rollos con metros programados (incluye trazos secundarios)
    const meteredLines = rollLines.filter((line) => {
        const meters = Number(line.programmedMeters ?? 0);
        return Number.isFinite(meters) && meters > 0;
    });

    for (const line of meteredLines) {
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

function mapLineToRow(line: ThreadLine, principalColor: PrincipalColor, processType: string): LocalThreadRow {
    return {
        uid: generateUid(),
        id: String(line.id ?? '').trim() || null,
        color: String(line.color ?? principalColor.color ?? '').trim(),
        colorCode: String(principalColor.colorCode ?? '').trim(),
        threadCode: String(line.colorCode ?? '').trim() || null,
        processType,
        threadName: resolveOptionId(threadOptions.value, line.threadName),
        gauge: resolveOptionId(gaugeOptions.value, line.gauge),
        brand: resolveOptionId(brandOptions.value, line.brand),
        stitchesPerInch: resolveOptionId(stitchesPerInchOptions.value, line.stitchesPerInch),
        defaultThreadCost: line.defaultThreadCost != null ? Number(line.defaultThreadCost) : null,
    };
}

function createEmptyRow(principalColor: PrincipalColor, processType: string): LocalThreadRow {
    return {
        uid: generateUid(),
        id: null,
        color: principalColor.color,
        colorCode: principalColor.colorCode,
        threadCode: null,
        processType,
        threadName: resolveOptionId(threadOptions.value, '180'),
        gauge: resolveOptionId(gaugeOptions.value, '120'),
        brand: resolveOptionId(brandOptions.value, 'Venus'),
        stitchesPerInch: resolveOptionId(stitchesPerInchOptions.value, '12'),
        defaultThreadCost: null,
    };
}

function normalizeRowsForSave(): ThreadLine[] {
    return rows.value.map((row) => ({
        id: row.id ?? '',
        processType: row.processType || null,
        threadName: resolveOptionName(threadOptions.value, row.threadName) ?? '',
        gauge: toNullableNumber(resolveOptionName(gaugeOptions.value, row.gauge) ?? row.gauge),
        brand: resolveOptionName(brandOptions.value, row.brand) ?? toNullableText(row.brand),
        color: toNullableText(row.color),
        colorCode: toNullableText(row.threadCode),
        stitchesPerInch: toNullableNumber(resolveOptionName(stitchesPerInchOptions.value, row.stitchesPerInch) ?? row.stitchesPerInch),
        defaultThreadCost: toNullableNumber(row.defaultThreadCost),
    }));
}

function serializeState(): string {
    return JSON.stringify({
        threads: [...normalizeRowsForSave(), ...hiddenLines.value],
    });
}

function applyRows(sourceLines: ThreadLine[], nextPrincipalColors: PrincipalColor[], resetSnapshotState: boolean): void {
    // Build map by colorKey::processType for O(1) lookup
    const grouped = new Map<string, ThreadLine>();
    const parkedLines: ThreadLine[] = [];

    for (const line of sourceLines) {
        const color = String(line.color ?? '').trim();
        const colorCode = String(line.colorCode ?? '').trim();
        const processType = String(line.processType ?? '').trim();

        if (processType === '' || (color === '' && colorCode === '')) {
            // Líneas legacy sin processType: se aparcan para no perder datos
            parkedLines.push(line);
            continue;
        }

        const key = buildColorKey(color, colorCode) + '::' + processType.toLowerCase();
        grouped.set(key, line);
    }

    const nextRows: LocalThreadRow[] = [];

    for (const principalColor of nextPrincipalColors) {
        for (const processType of PROCESS_TYPES) {
            const key = buildColorKey(principalColor.color, principalColor.colorCode) + '::' + processType.toLowerCase();
            const matched = grouped.get(key) ?? null;
            nextRows.push(
                matched ? mapLineToRow(matched, principalColor, processType) : createEmptyRow(principalColor, processType),
            );
        }
    }

    rows.value = nextRows;
    hiddenLines.value = parkedLines;
    principalColors.value = nextPrincipalColors;

    if (resetSnapshotState) {
        snapshot.value = serializeState();
    }
}

const dirty = computed(() => serializeState() !== snapshot.value);
const canSave = computed(() => !isLoading.value && !isSaving.value && dirty.value && !props.readonly);

// Agrupa las filas por color para mostrar encabezados de color en la tabla
const colorGroups = computed(() => {
    return principalColors.value.map((pc, i) => ({
        color: pc.color,
        colorCode: pc.colorCode,
        startIndex: i * PROCESS_TYPES.length,
    }));
});

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

    try {
        const state: ThreadState = await saveOpThreads(props.opId, {
            threads: [...normalizeRowsForSave(), ...hiddenLines.value],
            deletedIds: [],
        });
        applyRows(Array.isArray(state.lines) ? state.lines : [], principalColors.value, true);
        success('Hilos guardados correctamente.');
    } catch (err) {
        console.error('Error al guardar hilos:', err);
        showError('No se pudieron guardar los hilos.');
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
    <div class="space-y-2">
        <AlertStack
            :success-message="successMessage"
            :error-message="errorMessage"
            :error-type="errorType"
            @close-success="clear"
            @close-error="clear"
        />

        <div class="flex flex-col gap-1 p-1">
            <h2 class="text-base font-semibold text-foreground">Hilos</h2>
            <p class="text-xs text-muted-foreground">
                Los hilos se crean automáticamente desde los colores de rollos con metros programados. Completa código de hilo, calibre, marca y puntada.
            </p>
        </div>

        <div v-if="isLoading" class="rounded-lg border-0 px-4 py-6 text-sm text-muted-foreground shadow-none">Cargando hilos...</div>

        <template v-else>
            <div
                v-if="rows.length === 0"
                class="rounded-lg border border-dashed border-border bg-background px-4 py-6 text-center text-sm text-muted-foreground"
            >
                Selecciona rollos con metros programados en Materia prima para crear registros de hilos.
            </div>

            <div v-else class="space-y-2">

                <div class="overflow-x-auto rounded-md border border-border">
                    <Table>
                        <TableHeader class="bg-muted/30">
                            <TableRow>
                                <TableHead class="w-[170px] text-left">Proceso</TableHead>
                                <TableHead class="min-w-[160px] text-left">Hilo</TableHead>
                                <TableHead class="min-w-[120px] text-left">Calibre</TableHead>
                                <TableHead class="min-w-[150px] text-left">Marca</TableHead>
                                <TableHead class="min-w-[160px] text-left">Punt. x Pulgada</TableHead>
                                <TableHead class="min-w-[150px] text-left">Color</TableHead>
                                <TableHead class="min-w-[130px] text-left">Código de hilo</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <template v-for="group in colorGroups" :key="group.color + ':' + group.colorCode">
                                <!-- Encabezado de color -->
                                <TableRow class="bg-muted/50 hover:bg-muted/50">
                                    <TableCell colspan="7" class="py-1.5 pl-3">
                                        <span class="text-sm font-semibold text-foreground">{{ group.color }}</span>
                                        <span v-if="group.colorCode" class="ml-2 text-xs text-muted-foreground">Cód. {{ group.colorCode }}</span>
                                    </TableCell>
                                </TableRow>
                                <!-- 5 sub-filas de proceso por color -->
                                <TableRow
                                    v-for="(processType, procIdx) in PROCESS_TYPES"
                                    :key="processType"
                                    class="transition-colors hover:bg-muted/20"
                                >
                                    <TableCell class="py-1.5 pl-6 text-sm text-muted-foreground">{{ processType }}</TableCell>
                                    <TableCell class="py-1">
                                        <SelectField
                                            v-model="rows[group.startIndex + procIdx].threadName"
                                            label=""
                                            placeholder="Selecciona hilo"
                                            :options="threadOptions"
                                            :disabled="isSaving || isLoadingParameterOptions"
                                            :searchable="true"
                                            :clearable="true"
                                        />
                                    </TableCell>
                                    <TableCell class="py-1">
                                        <SelectField
                                            v-model="rows[group.startIndex + procIdx].gauge"
                                            label=""
                                            placeholder="Calibre"
                                            :options="gaugeOptions"
                                            :disabled="isSaving || isLoadingParameterOptions"
                                            :searchable="true"
                                            :clearable="true"
                                        />
                                    </TableCell>
                                    <TableCell class="py-1">
                                        <SelectField
                                            v-model="rows[group.startIndex + procIdx].brand"
                                            label=""
                                            placeholder="Marca"
                                            :options="brandOptions"
                                            :disabled="isSaving || isLoadingParameterOptions"
                                            :searchable="true"
                                            :allow-custom="true"
                                            :clearable="true"
                                            custom-option-label='Crear "{value}"'
                                        />
                                    </TableCell>
                                    <TableCell class="py-1">
                                        <SelectField
                                            v-model="rows[group.startIndex + procIdx].stitchesPerInch"
                                            label=""
                                            placeholder="Puntada"
                                            :options="stitchesPerInchOptions"
                                            :disabled="isSaving || isLoadingParameterOptions"
                                            :searchable="true"
                                            :clearable="true"
                                        />
                                    </TableCell>
                                    <TableCell class="py-1">
                                        <Input
                                            v-model="rows[group.startIndex + procIdx].color"
                                            placeholder="Color"
                                            :disabled="isSaving"
                                        />
                                    </TableCell>
                                    <TableCell class="py-1">
                                        <Input
                                            v-model="rows[group.startIndex + procIdx].threadCode"
                                            placeholder="Código hilo"
                                            :disabled="isSaving"
                                        />
                                    </TableCell>
                                </TableRow>
                            </template>
                        </TableBody>
                    </Table>
                </div>

                <div class="flex items-center justify-end">
                    <Button type="button" class="h-9 rounded-full px-4" :disabled="!canSave" @click="handleSave">
                        <Save class="mr-1 h-4 w-4" />
                        {{ isSaving ? 'Guardando...' : 'Guardar cambios' }}
                    </Button>
                </div>
            </div>
        </template>

        <div v-if="error" class="rounded-lg border-0 bg-destructive/10 px-4 py-3 text-sm text-destructive shadow-none">
            {{ error }}
        </div>
    </div>
</template>
