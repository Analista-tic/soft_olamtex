<script setup lang="ts">
import SelectField from '@/components/SelectField.vue';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { ThreadLine, ThreadState } from '@/domain/Production';
import { fetchOpThreads, fetchSettingsParameters, saveOpThreads } from '@/domain/Production';
import type { Option } from '@/domain/shared';
import { Plus, Save, Trash } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    opId: string | number;
    units: number | null;
    producedColors?: Array<{
        name: string;
        code: string | null;
    }>;
}>();

type ColorOption = Option & { code?: string };

type RowColor = {
    id: string | null;
    color: string;
    colorCode: string;
};

const FIXED_COLOR_SLOTS = 6;

type LocalThreadRow = {
    uid: string;
    id: string | null;
    threadName: string;
    gauge: string | number | null;
    brand: string;
    colors: RowColor[];
    stitchesPerInch: string | number | null;
    defaultThreadCost: string | number | null;
};

const isLoading = ref(false);
const isSaving = ref(false);
const error = ref<string | null>(null);
const saveMessage = ref<string | null>(null);
const rows = ref<LocalThreadRow[]>([]);
const deletedIds = ref<string[]>([]);
const snapshot = ref('');
const isLoadingParameterOptions = ref(false);
const gaugeOptions = ref<Option[]>([]);
const stitchesPerInchOptions = ref<Option[]>([]);
const brandOptions = ref<Option[]>([]);
const settingsColorOptions = ref<ColorOption[]>([]);

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
    })
        .format(Number(value || 0))
        .replace(/\u00A0/g, ' ');
}

const totalHilos = computed(() => rows.value.reduce((sum, row) => sum + (Number(row.defaultThreadCost) || 0), 0));
const costoHilos = computed(() => formatCOP(totalHilos.value));

function normalizeText(value: string | null | undefined): string {
    return String(value ?? '')
        .trim()
        .toLocaleLowerCase('es-CO');
}

function buildColorKey(name: string | null | undefined, code: string | null | undefined): string {
    return `${normalizeText(name)}|${normalizeText(code)}`;
}

function mapColorEntryToOption(color: { name: string; code?: string | null; id?: string | number | null }): ColorOption | null {
    const name = String(color.name ?? '').trim();
    const code = String(color.code ?? '').trim();
    const id = color.id != null && String(color.id).trim() !== '' ? String(color.id) : code || name;

    if (name === '' && code === '') return null;

    return {
        id,
        name: name || code,
        ...(code !== '' && { code }),
    };
}

function findColorInSettings(name: string | null | undefined, code: string | null | undefined): ColorOption | null {
    const normalizedName = normalizeText(name);
    const normalizedCode = normalizeText(code);

    return (
        settingsColorOptions.value.find((option) => {
            const optionName = normalizeText(option.name);
            const optionCode = normalizeText(option.code);

            if (normalizedCode && optionCode === normalizedCode) return true;
            if (normalizedName && optionName === normalizedName) return true;
            return false;
        }) ?? null
    );
}

function getColorOptionLabel(option: Option): string {
    const colorOption = option as ColorOption;
    const name = String(colorOption.name ?? '').trim();
    const code = String(colorOption.code ?? '').trim();
    return code ? `${name} (${code})` : name;
}

const producedColorOptions = computed<ColorOption[]>(() => {
    const map = new Map<string, ColorOption>();

    for (const color of props.producedColors ?? []) {
        const matched = findColorInSettings(color.name, color.code);
        const option = matched ?? mapColorEntryToOption({ name: color.name, code: color.code });
        if (!option) continue;

        const key = buildColorKey(option.name, option.code);
        if (!map.has(key)) {
            map.set(key, option);
        }
    }

    return [...map.values()].sort((a, b) => getColorOptionLabel(a).localeCompare(getColorOptionLabel(b), 'es-CO', { sensitivity: 'base' }));
});

const selectableColorOptions = computed<ColorOption[]>(() => {
    const map = new Map<string, ColorOption>();

    for (const option of producedColorOptions.value) {
        map.set(buildColorKey(option.name, option.code), option);
    }

    for (const row of rows.value) {
        for (const colorEntry of row.colors) {
            const matched = findColorInSettings(colorEntry.color, colorEntry.colorCode);
            const option =
                matched ??
                mapColorEntryToOption({
                    name: colorEntry.color,
                    code: colorEntry.colorCode,
                    id: colorEntry.colorCode || colorEntry.color,
                });
            if (!option) continue;

            const key = buildColorKey(option.name, option.code);
            if (!map.has(key)) {
                map.set(key, option);
            }
        }
    }

    return [...map.values()].sort((a, b) => getColorOptionLabel(a).localeCompare(getColorOptionLabel(b), 'es-CO', { sensitivity: 'base' }));
});

function toNullableText(value: string): string | null {
    const clean = value.trim();
    return clean === '' ? null : clean;
}

function toNullableNumber(value: unknown): number | null {
    const clean = String(value ?? '').trim();
    if (clean === '') return null;

    const parsed = Number(clean);
    return Number.isFinite(parsed) ? parsed : null;
}

function mapApiLineToLocal(line: ThreadLine): LocalThreadRow {
    const colors: RowColor[] = Array.from({ length: FIXED_COLOR_SLOTS }, () => ({ id: null, color: '', colorCode: '' }));
    if (String(line.color ?? '').trim() !== '' || String(line.colorCode ?? '').trim() !== '') {
        colors[0] = {
            id: line.id ? String(line.id) : null,
            color: String(line.color ?? ''),
            colorCode: String(line.colorCode ?? ''),
        };
    }

    return {
        uid: generateUid(),
        id: line.id ? String(line.id) : null,
        threadName: String(line.threadName ?? ''),
        gauge: line.gauge !== null && line.gauge !== undefined ? Number(line.gauge) : null,
        brand: String(line.brand ?? ''),
        colors,
        stitchesPerInch: line.stitchesPerInch !== null && line.stitchesPerInch !== undefined ? Number(line.stitchesPerInch) : null,
        defaultThreadCost: line.defaultThreadCost != null ? Number(line.defaultThreadCost) : null,
    };
}

function createEmptyRow(): LocalThreadRow {
    return {
        uid: generateUid(),
        id: null,
        threadName: '',
        gauge: null,
        brand: '',
        colors: Array.from({ length: FIXED_COLOR_SLOTS }, () => ({ id: null, color: '', colorCode: '' })),
        stitchesPerInch: null,
        defaultThreadCost: null,
    };
}

function buildThreadGroupKey(line: ThreadLine): string {
    const threadName = String(line.threadName ?? '').trim().toLocaleLowerCase('es-CO');
    const gauge = line.gauge != null ? String(Number(line.gauge)) : '';
    const brand = String(line.brand ?? '').trim().toLocaleLowerCase('es-CO');
    const stitches = line.stitchesPerInch != null ? String(Number(line.stitchesPerInch)) : '';
    const cost = line.defaultThreadCost != null ? String(Number(line.defaultThreadCost)) : '';

    return [threadName, gauge, brand, stitches, cost].join('|');
}

function groupApiLines(lines: ThreadLine[]): LocalThreadRow[] {
    const grouped = new Map<string, LocalThreadRow>();

    for (const line of lines) {
        const key = buildThreadGroupKey(line);
        const current = grouped.get(key);

        if (!current) {
            grouped.set(key, mapApiLineToLocal(line));
            continue;
        }

        const color = String(line.color ?? '').trim();
        const colorCode = String(line.colorCode ?? '').trim();
        const colorKey = buildColorKey(color, colorCode);
        const exists = current.colors.some((entry) => buildColorKey(entry.color, entry.colorCode) === colorKey);
        if (!exists && (color !== '' || colorCode !== '')) {
            const emptyIndex = current.colors.findIndex((entry) => String(entry.color).trim() === '' && String(entry.colorCode).trim() === '');
            if (emptyIndex >= 0 && emptyIndex < FIXED_COLOR_SLOTS) {
                current.colors[emptyIndex] = {
                    id: line.id ? String(line.id) : null,
                    color,
                    colorCode,
                };
            }
        }
    }

    return [...grouped.values()];
}

function normalizeRowsForSave(): ThreadLine[] {
    const normalized: ThreadLine[] = [];

    for (const row of rows.value) {
        const threadName = row.threadName.trim();
        const gauge = toNullableNumber(row.gauge);
        const brand = toNullableText(row.brand);
        const stitchesPerInch = toNullableNumber(row.stitchesPerInch);
        const defaultThreadCost = toNullableNumber(row.defaultThreadCost);

        const rowColors = row.colors.filter((entry) => String(entry.color).trim() !== '' || String(entry.colorCode).trim() !== '');

        if (rowColors.length === 0) {
            normalized.push({
                id: row.id ?? '',
                threadName,
                gauge,
                brand,
                color: null,
                colorCode: null,
                stitchesPerInch,
                defaultThreadCost,
            });
            continue;
        }

        for (const colorEntry of rowColors) {
            normalized.push({
                id: colorEntry.id ?? '',
                threadName,
                gauge,
                brand,
                color: toNullableText(colorEntry.color),
                colorCode: toNullableText(colorEntry.colorCode),
                stitchesPerInch,
                defaultThreadCost,
            });
        }
    }

    return normalized;
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
    const loadedRows = Array.isArray(state.lines) ? groupApiLines(state.lines) : [];
    rows.value = loadedRows.length > 0 ? loadedRows : [createEmptyRow()];
    deletedIds.value = [];
    snapshot.value = serializeState();
}

function addThread(): void {
    rows.value.push(createEmptyRow());
}

function pushDeletedIdIfNeeded(id: string | null | undefined): void {
    const normalized = String(id ?? '').trim();
    if (normalized === '') return;
    if (!deletedIds.value.includes(normalized)) {
        deletedIds.value.push(normalized);
    }
}

function onColorSelected(
    rowIndex: number,
    slotIndex: number,
    value: string | number | boolean | null | undefined,
): void {
    const row = rows.value[rowIndex];
    if (!row || !row.colors[slotIndex]) return;

    const slot = row.colors[slotIndex];
    const selectedValue = Array.isArray(value) ? value[0] : value;

    if (selectedValue === null || selectedValue === undefined || selectedValue === '') {
        pushDeletedIdIfNeeded(slot.id);
        row.colors[slotIndex] = { id: null, color: '', colorCode: '' };
        return;
    }

    const opt = selectableColorOptions.value.find((o) => String(o.id) === String(selectedValue) || String(o.name) === String(selectedValue));
    if (!opt) return;

    const nextColor = String(opt.name ?? '').trim();
    const nextColorCode = String(opt.code ?? opt.name ?? '').trim();
    const prevKey = buildColorKey(slot.color, slot.colorCode);
    const nextKey = buildColorKey(nextColor, nextColorCode);

    if (prevKey !== '' && prevKey !== nextKey) {
        pushDeletedIdIfNeeded(slot.id);
    }

    row.colors[slotIndex] = {
        id: prevKey === nextKey ? slot.id : null,
        color: nextColor,
        colorCode: nextColorCode,
    };
}

function getColorSelectionValue(row: LocalThreadRow, slotIndex: number): string | number | boolean | null {
    const entry = row.colors[slotIndex];
    if (!entry) return null;
    if (String(entry.color).trim() === '' && String(entry.colorCode).trim() === '') return null;

    const opt = selectableColorOptions.value.find((o) => buildColorKey(o.name, o.code) === buildColorKey(entry.color, entry.colorCode));
    return opt?.id ?? null;
}

function removeThread(uid: string): void {
    const target = rows.value.find((row) => row.uid === uid);
    if (!target) return;

    const idsToDelete = new Set<string>();
    if (target.id) {
        idsToDelete.add(target.id);
    }
    for (const colorEntry of target.colors) {
        if (colorEntry.id) {
            idsToDelete.add(colorEntry.id);
        }
    }

    for (const id of idsToDelete) {
        if (!deletedIds.value.includes(id)) {
            deletedIds.value.push(id);
        }
    }

    rows.value = rows.value.filter((row) => row.uid !== uid);
}

function mapNumericOption(option: Option): Option {
    const label = String(option.name ?? '').trim();
    const parsed = Number(label);

    return {
        id: Number.isFinite(parsed) ? parsed : option.id,
        name: label !== '' ? label : String(option.id ?? ''),
    };
}

function mapTextOption(option: Option): Option {
    const label = String(option.name ?? '').trim();
    if (label === '') {
        return {
            id: String(option.id ?? ''),
            name: String(option.id ?? ''),
        };
    }

    return {
        id: label,
        name: label,
    };
}

async function loadThreadParameterOptions(): Promise<void> {
    if (isLoadingParameterOptions.value) return;

    isLoadingParameterOptions.value = true;
    try {
        const data = await fetchSettingsParameters(['calibre', 'puntada_por_pulgada', 'thread_brands', 'fabric_colors']);
        gaugeOptions.value = (data.calibre ?? []).map(mapNumericOption);
        stitchesPerInchOptions.value = (data.puntada_por_pulgada ?? []).map(mapNumericOption);
        brandOptions.value = (data.thread_brands ?? []).map(mapTextOption);
        settingsColorOptions.value = (data.fabric_colors ?? []).map((opt: Option & { code?: string }) => ({
            id: String(opt.id ?? opt.name ?? ''),
            name: String(opt.name ?? opt.id ?? ''),
            ...(opt.code != null && { code: String(opt.code) }),
        }));
    } catch (err) {
        console.error('Error al cargar parametros de hilos:', err);
        gaugeOptions.value = [];
        stitchesPerInchOptions.value = [];
        brandOptions.value = [];
        settingsColorOptions.value = [];
    } finally {
        isLoadingParameterOptions.value = false;
    }
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

onMounted(() => {
    void loadThreadParameterOptions();
});
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-col gap-3 p-2 md:flex-row md:items-start md:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-foreground">Hilos</h2>
                <p class="text-sm text-muted-foreground">
                    Los colores disponibles se toman de las telas programadas en la ficha y el nombre/código se resuelven desde settings.
                </p>
            </div>
            <div class="w-fit rounded-full border border-border bg-muted/30 px-3 py-1.5 text-sm font-medium text-foreground">
                Costo Hilos: {{ costoHilos }}
            </div>
        </div>

        <div v-if="isLoading" class="rounded-lg border-0 px-4 py-6 text-sm text-muted-foreground shadow-none">Cargando hilos...</div>

        <template v-else>
            <div v-if="rows.length === 0" class="rounded-lg border border-dashed border-border bg-background px-4 py-8 text-center">
                <p class="text-sm font-medium text-foreground">No hay hilos registrados. Agrega una linea para iniciar.</p>
            </div>

            <div v-else class="min-w-0 overflow-hidden">
                <div class="max-h-[460px] w-full overflow-auto rounded-md border border-border">
                    <Table>
                    <TableHeader class="sticky top-0 z-10 bg-background">
                        <TableRow>
                            <TableHead class="min-w-[170px] text-left">Hilo</TableHead>
                            <TableHead class="min-w-[120px] text-left">Calibre</TableHead>
                            <TableHead class="min-w-[160px] text-left">Marca</TableHead>
                            <TableHead class="min-w-[180px] text-left">Puntada por pulgada</TableHead>
                            <TableHead class="min-w-[170px] text-left">Color 1</TableHead>
                            <TableHead class="min-w-[120px] text-left">Código</TableHead>
                            <TableHead class="min-w-[170px] text-left">Color 2</TableHead>
                            <TableHead class="min-w-[120px] text-left">Código</TableHead>
                            <TableHead class="min-w-[170px] text-left">Color 3</TableHead>
                            <TableHead class="min-w-[120px] text-left">Código</TableHead>
                            <TableHead class="min-w-[170px] text-left">Color 4</TableHead>
                            <TableHead class="min-w-[120px] text-left">Código</TableHead>
                            <TableHead class="min-w-[170px] text-left">Color 5</TableHead>
                            <TableHead class="min-w-[120px] text-left">Código</TableHead>
                            <TableHead class="min-w-[170px] text-left">Color 6</TableHead>
                            <TableHead class="min-w-[120px] text-left">Código</TableHead>
                            <TableHead class="min-w-[120px] text-right">Costo hilo por defecto</TableHead>
                            <TableHead class="w-[80px] text-center">Accion</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="(row, index) in rows" :key="row.uid" class="group transition-colors hover:bg-muted/30">
                            <TableCell>
                                <Input v-model="rows[index].threadName" placeholder="Nombre del hilo" :disabled="isSaving" />
                            </TableCell>
                            <TableCell>
                                <SelectField
                                    v-model="rows[index].gauge"
                                    label=""
                                    placeholder="Selecciona"
                                    :options="gaugeOptions"
                                    :disabled="isSaving || isLoadingParameterOptions"
                                />
                            </TableCell>
                            <TableCell>
                                <SelectField
                                    v-model="rows[index].brand"
                                    label=""
                                    placeholder="Selecciona o escribe"
                                    :options="brandOptions"
                                    :disabled="isSaving || isLoadingParameterOptions"
                                    :searchable="true"
                                    :allow-custom="true"
                                    custom-option-label='Crear "{value}"'
                                />
                            </TableCell>
                            <TableCell>
                                <SelectField
                                    v-model="rows[index].stitchesPerInch"
                                    label=""
                                    placeholder="Selecciona"
                                    :options="stitchesPerInchOptions"
                                    :disabled="isSaving || isLoadingParameterOptions"
                                />
                            </TableCell>
                            <TableCell>
                                <Input
                                    v-model="rows[index].colors[0].color"
                                    placeholder="Color 1"
                                    :disabled="isSaving"
                                />
                            </TableCell>
                            <TableCell>
                                <Input
                                    v-model="rows[index].colors[0].colorCode"
                                    placeholder="Código"
                                    :disabled="isSaving"
                                />
                            </TableCell>
                            <TableCell>
                                <Input
                                    v-model="rows[index].colors[1].color"
                                    placeholder="Color 2"
                                    :disabled="isSaving"
                                />
                            </TableCell>
                            <TableCell>
                                <Input
                                    v-model="rows[index].colors[1].colorCode"
                                    placeholder="Código"
                                    :disabled="isSaving"
                                />
                            </TableCell>
                            <TableCell>
                                <Input
                                    v-model="rows[index].colors[2].color"
                                    placeholder="Color 3"
                                    :disabled="isSaving"
                                />
                            </TableCell>
                            <TableCell>
                                <Input
                                    v-model="rows[index].colors[2].colorCode"
                                    placeholder="Código"
                                    :disabled="isSaving"
                                />
                            </TableCell>
                            <TableCell>
                                <Input
                                    v-model="rows[index].colors[3].color"
                                    placeholder="Color 4"
                                    :disabled="isSaving"
                                />
                            </TableCell>
                            <TableCell>
                                <Input
                                    v-model="rows[index].colors[3].colorCode"
                                    placeholder="Código"
                                    :disabled="isSaving"
                                />
                            </TableCell>
                            <TableCell>
                                <Input
                                    v-model="rows[index].colors[4].color"
                                    placeholder="Color 5"
                                    :disabled="isSaving"
                                />
                            </TableCell>
                            <TableCell>
                                <Input
                                    v-model="rows[index].colors[4].colorCode"
                                    placeholder="Código"
                                    :disabled="isSaving"
                                />
                            </TableCell>
                            <TableCell>
                                <Input
                                    v-model="rows[index].colors[5].color"
                                    placeholder="Color 6"
                                    :disabled="isSaving"
                                />
                            </TableCell>
                            <TableCell>
                                <Input
                                    v-model="rows[index].colors[5].colorCode"
                                    placeholder="Código"
                                    :disabled="isSaving"
                                />
                            </TableCell>
                            <TableCell class="text-right">
                                <Input
                                    :model-value="row.defaultThreadCost != null ? String(row.defaultThreadCost) : ''"
                                    type="number"
                                    min="0"
                                    step="1"
                                    class="text-right tabular-nums"
                                    placeholder="0"
                                    :disabled="isSaving"
                                    @update:model-value="(v) => (rows[index].defaultThreadCost = v && Number(v) >= 0 ? Number(v) : null)"
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
                    <Button type="button" class="h-9 rounded-full px-4" :disabled="!canSave" @click="handleSave">
                        <Save class="mr-1 h-4 w-4" />
                        {{ isSaving ? 'Guardando...' : 'Guardar cambios' }}
                    </Button>
                </div>

                <div class="rounded-lg border border-primary/30 bg-primary/5 px-3 py-2">
                    <div class="flex items-center gap-2">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Total Hilos Asignados</p>
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
