<script setup lang="ts">
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import TableStatusBadge from '@/components/common/TableStatusBadge.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

export type SectionOption = { id: number | string; name: string; type?: string | null };
export type ProductSheetOption = {
    id: number | string;
    name: string;
    reference?: string;
    type?: string;
    sample_reference?: string | null;
    product_reference?: string | null;
    product_reference_2?: string | null;
    version?: number | null;
};
export type MachineOption = {
    id: number | string;
    name: string;
    code?: string | null;
    supplement_factor?: number | null;
    factor_suplemento?: number | null;
};

export interface OperationFormData {
    action: string;
    garment_part: string;
    anexo?: string | null;
    section_id: number | null;
    measure: number | null;
    quantity: number;
    sam_base: number | null;
    factor_operario: number;
    product_sheet_id_reference: number | null;
    machine_id_reference: number | null;
    codigo?: string | null;
    is_active?: boolean;
}

const props = withDefaults(
    defineProps<{
        sections: SectionOption[];
        actions: string[];
        garmentParts: string[];
        productSheets?: ProductSheetOption[];
        machines?: MachineOption[];
        operation?: OperationFormData | null;
        defaultSectionId?: number | null;
        defaultProductSheetId?: number | string | null;
        lockProductSheet?: boolean;
    }>(),
    {
        productSheets: () => [],
        machines: () => [],
        operation: null,
        defaultSectionId: null,
        defaultProductSheetId: null,
        lockProductSheet: false,
    },
);

const emit = defineEmits<{
    (e: 'submit', payload: Record<string, unknown>): void;
    /** Emitido cuando se persiste una nueva opción en un catálogo */
    (e: 'catalog-updated', catalog: string, options: string[]): void;
}>();

const isEditing = computed(() => !!props.operation);

const form = ref<OperationFormData>({
    action: '',
    garment_part: '',
    anexo: null,
    section_id: props.defaultSectionId ?? (props.sections[0]?.id !== undefined ? Number(props.sections[0].id) : null),
    measure: null,
    quantity: 1,
    sam_base: null,
    factor_operario: 100,
    product_sheet_id_reference: (props.defaultProductSheetId ?? null) as number | null,
    machine_id_reference: null,
    codigo: '',
    is_active: true,
});

watch(
    () => [props.operation, props.defaultSectionId, props.defaultProductSheetId] as const,
    ([operation, defaultSectionId, defaultProductSheetId]) => {
        if (operation) {
            form.value = {
                action: operation.action ?? '',
                garment_part: operation.garment_part ?? '',
                anexo: operation.anexo ?? null,
                section_id: operation.section_id ?? null,
                measure: operation.measure ?? null,
                quantity: operation.quantity ?? 1,
                sam_base: operation.sam_base ?? null,
                factor_operario: operation.factor_operario ?? 100,
                product_sheet_id_reference: operation.product_sheet_id_reference ?? null,
                machine_id_reference: operation.machine_id_reference ?? null,
                codigo: operation.codigo ?? '',
                is_active: operation.is_active ?? true,
            };
        } else {
            form.value = {
                action: '',
                garment_part: '',
                anexo: null,
                section_id: defaultSectionId ?? (props.sections[0]?.id !== undefined ? Number(props.sections[0].id) : null),
                measure: null,
                quantity: 1,
                sam_base: null,
                factor_operario: 100,
                product_sheet_id_reference: (defaultProductSheetId ?? null) as number | null,
                machine_id_reference: null,
                codigo: '',
                is_active: true,
            };
        }
    },
    { immediate: true },
);

watch(() => form.value.anexo, (v) => {
    if (v) {
        const n = v.toUpperCase();
        if (v !== n) form.value.anexo = n;
    }
});

// ── Catálogos reactivos (sincronizados con backend al agregar nuevas opciones) ──

const localActions = ref<string[]>([...props.actions]);
const localGarmentParts = ref<string[]>([...props.garmentParts]);

watch(() => props.actions, (v) => { localActions.value = [...v]; });
watch(() => props.garmentParts, (v) => { localGarmentParts.value = [...v]; });

async function persistCatalogOption(catalog: string, name: string): Promise<void> {
    try {
        const { data } = await axios.post('/produccion/operaciones/catalog-option', { catalog, name });
        const updated: string[] = data.options ?? [];
        if (catalog === 'operation_actions') localActions.value = updated;
        else if (catalog === 'operation_garment_parts') localGarmentParts.value = updated;
        emit('catalog-updated', catalog, updated);
    } catch {
        // La opción ya quedó seleccionada en el form; no bloquear el flujo
    }
}

function handleActionChange(val: string | number | boolean | (string | number | boolean)[] | null | undefined) {
    const upper = String(val ?? '').trim().toUpperCase();
    form.value.action = upper;
    if (upper && !localActions.value.some((a) => a.toUpperCase() === upper)) {
        void persistCatalogOption('operation_actions', upper);
    }
}

function handleGarmentPartChange(val: string | number | boolean | (string | number | boolean)[] | null | undefined) {
    const upper = String(val ?? '').trim().toUpperCase();
    form.value.garment_part = upper;
    if (upper && !localGarmentParts.value.some((g) => g.toUpperCase() === upper)) {
        void persistCatalogOption('operation_garment_parts', upper);
    }
}

// ── Options computadas ───────────────────────────────────────────────────────

const sectionOptions = computed(() =>
    props.sections.map((s) => ({ id: s.id, name: s.name })).sort((a, b) => a.name.localeCompare(b.name, 'es', { sensitivity: 'base' })),
);

const actionOptions = computed(() => {
    const all = [...new Set(localActions.value.map((v) => v.trim().toUpperCase()).filter(Boolean))]
        .sort((a, b) => a.localeCompare(b, 'es-CO'));
    return all.map((a) => ({ id: a, name: a }));
});

const garmentPartOptions = computed(() => {
    const all = [...new Set(localGarmentParts.value.map((v) => v.trim().toUpperCase()))]
        .sort((a, b) => a.localeCompare(b, 'es-CO'));
    return all.map((g) => ({ id: g, name: g || '— (genérica)' }));
});

const productSheetOptions = computed(() =>
    (props.productSheets || []).map((ps) => {
        const explicitName = String(ps.name ?? '').trim();
        if (explicitName) return { id: ps.id, name: explicitName };

        const parts = [
            String(ps.sample_reference ?? '').trim().toUpperCase(),
            String(ps.product_reference ?? '').trim().toUpperCase(),
            String(ps.product_reference_2 ?? '').trim().toUpperCase(),
        ].filter(Boolean);
        const version = Number(ps.version ?? 1);
        const base = parts.length ? parts.join(' / ') : `#${ps.id}`;
        return { id: ps.id, name: `${base} / v${version}` };
    }),
);

function buildMachineOptionLabel(machine: MachineOption): string {
    const code = String(machine.code ?? '').trim();
    const name = String(machine.name ?? '').trim();
    const supplement = Number(machine.supplement_factor ?? machine.factor_suplemento ?? null);
    const baseLabel = code && name ? `${code} · ${name}` : code || name || `#${machine.id}`;

    if (Number.isFinite(supplement) && supplement > 0) {
        return `${baseLabel} (Supl. ×${supplement.toFixed(2)})`;
    }

    return baseLabel;
}

const machineOptions = computed(() =>
    (props.machines || [])
        .map((m) => ({
            id: m.id,
            name: buildMachineOptionLabel(m),
        }))
        .sort((a, b) => a.name.localeCompare(b.name, 'es', { sensitivity: 'base' })),
);

const generatedName = computed(() => {
    const a = form.value.action?.trim().toUpperCase() ?? '';
    const p = form.value.garment_part?.trim().toUpperCase() ?? '';
    const anx = form.value.anexo?.trim().toUpperCase() ?? '';
    const med = form.value.measure != null && Number(form.value.measure) > 0
        ? `${Number(form.value.measure)}cm`
        : '';
    const qty = form.value.quantity != null && Number(form.value.quantity) > 1
        ? `X${Number(form.value.quantity)}`
        : '';

    const parts = [
        a && p ? `${a} ${p}` : a || p || '',
        anx,
        med,
        qty,
    ].filter(Boolean);
    return parts.join(' · ');
});

const selectedProductSheetName = computed(() => {
    const id = props.defaultProductSheetId ?? form.value.product_sheet_id_reference;
    const ps = props.productSheets?.find((s) => s.id === id);
    return ps?.name ?? '-';
});

/** Vacío ≠ 0: la medida es obligatoria y se escribe 0 cuando no aplica. */
const isMeasureMissing = () =>
    form.value.measure === null || form.value.measure === undefined || (form.value.measure as unknown) === '' || Number.isNaN(Number(form.value.measure));
const showRequiredErrors = ref(false);

function validate(): string | null {
    showRequiredErrors.value = true;
    if (!form.value.action?.trim()) return 'La acción es obligatoria.';
    if (form.value.garment_part === null || form.value.garment_part === undefined) return 'La parte de prenda es obligatoria.';
    if (!form.value.section_id && !isEditing.value) return 'La sección es obligatoria.';
    if (!form.value.product_sheet_id_reference) return 'La sábana de producto es obligatoria.';
    if (isMeasureMissing()) return 'La medida es obligatoria (escribe 0 si no aplica).';
    if (Number(form.value.measure) < 0) return 'La medida debe ser mayor o igual a 0.';

    if (!isEditing.value) {
        const sam = form.value.sam_base ?? 0;
        if (sam <= 0 || !Number.isFinite(sam)) {
            return 'El SAM base es obligatorio y debe ser mayor a 0 (ej: 0.25 min).';
        }
    } else if (form.value.sam_base != null) {
        if (form.value.sam_base <= 0 || !Number.isFinite(form.value.sam_base)) {
            return 'El SAM base debe ser mayor a 0 si se especifica.';
        }
    }

    if (!form.value.machine_id_reference) return 'La máquina es obligatoria.';
    return null;
}

function submit() {
    const err = validate();
    if (err) {
        emit('submit', { __error: err });
        return;
    }
    const payload: Record<string, unknown> = {
        action: (form.value.action ?? '').trim().toUpperCase(),
        garment_part: (form.value.garment_part ?? '').trim().toUpperCase(),
        anexo: form.value.anexo?.trim().toUpperCase() || null,
        section_id: form.value.section_id,
        measure: Number(form.value.measure),
        quantity: form.value.quantity ?? 1,
        sam_base: form.value.sam_base ?? null,
        factor_operario: form.value.factor_operario ?? 100,
        product_sheet_id_reference: form.value.product_sheet_id_reference ?? null,
        machine_id_reference: form.value.machine_id_reference ?? null,
        is_active: form.value.is_active ?? true,
    };
    emit('submit', payload);
}

defineExpose({ submit, validate });
</script>

<template>
    <div class="space-y-4">

        <!-- ── Bloque 1: Nombre vivo de la operación ──────────────────────── -->
        <div
            class="flex min-h-[52px] flex-col items-start gap-2 rounded-lg border border-primary/20 bg-gradient-to-r from-primary/5 to-transparent px-3 py-2 sm:flex-row sm:items-center"
            :class="generatedName ? '' : 'border-dashed opacity-60'"
        >
            <div class="flex-1 min-w-0">
                <p class="text-[10px] font-medium uppercase tracking-widest text-muted-foreground">Nombre generado</p>
                <p v-if="generatedName" class="truncate font-mono text-base font-bold leading-tight">{{ generatedName }}</p>
                <p v-else class="text-sm italic text-muted-foreground">Selecciona acción y parte de prenda…</p>
            </div>
            <div v-if="isEditing && form.codigo" class="flex-shrink-0 rounded-md border bg-muted px-2.5 py-1">
                <p class="text-[9px] uppercase tracking-widest text-muted-foreground">Código</p>
                <p class="font-mono text-xs font-semibold">{{ form.codigo }}</p>
            </div>
        </div>

        <!-- ── Bloque 2: Clasificación ─────────────────────────────────────── -->
        <div class="space-y-3 rounded-lg border bg-card p-3">
            <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Clasificación</p>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                <SelectField
                    v-model="form.section_id"
                    label="Sección"
                    placeholder="Selecciona sección"
                    :options="sectionOptions"
                    required
                />
                <SelectField
                    :model-value="form.action"
                    label="Acción"
                    placeholder="Selecciona o escribe"
                    :options="actionOptions"
                    :allow-custom="true"
                    :searchable="true"
                    custom-option-label="Agregar '{value}'"
                    required
                    @update:model-value="handleActionChange"
                />
                <SelectField
                    :model-value="form.garment_part"
                    label="Parte de prenda"
                    placeholder="Selecciona o escribe"
                    :options="garmentPartOptions"
                    :allow-custom="true"
                    :searchable="true"
                    custom-option-label="Agregar '{value}'"
                    required
                    @update:model-value="handleGarmentPartChange"
                />
            </div>

            <div class="space-y-1">
                <Label class="text-sm">Complemento <span class="text-[10px] font-normal text-muted-foreground">(opcional · añade detalle al nombre)</span></Label>
                <InputField
                    v-model="form.anexo"
                    label=""
                    placeholder="Ej: FRENTE DERECHO, INTERIOR, TIRO…"
                    class="uppercase"
                />
            </div>
        </div>

        <!-- ── Bloque 3: Tiempos base (obligatorios) ────────────────────── -->
        <div class="space-y-3 rounded-lg border border-primary/20 bg-card p-3">
            <p class="text-[10px] font-semibold uppercase tracking-widest text-primary/70">Tiempos base <span class="text-destructive">*</span></p>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                <div class="space-y-1">
                    <Label class="text-sm">SAM base (min) <span class="text-destructive">*</span></Label>
                    <InputField
                        v-model.number="form.sam_base"
                        label=""
                        type="number"
                        step="0.01"
                        min="0"
                        placeholder="0.25"
                        class="font-mono text-base font-semibold"
                    />
                    <p class="text-[10px] text-muted-foreground">Tiempo medido con cronómetro</p>
                </div>
                <div class="space-y-1">
                    <Label class="text-sm">Factor operario (%) <span class="text-destructive">*</span></Label>
                    <InputField v-model.number="form.factor_operario" label="" type="number" step="0.01" min="0.01" placeholder="100" />
                    <p class="text-[10px] text-muted-foreground">100 = eficiencia normal del operario</p>
                </div>
                <div class="space-y-1 md:col-span-2">
                    <Label class="text-sm">Máquina <span class="text-destructive">*</span></Label>
                    <SelectField
                        v-model="form.machine_id_reference"
                        placeholder="Selecciona máquina"
                        :options="machineOptions"
                        button-class="h-10"
                    />
                    <p class="text-[10px] text-muted-foreground">El suplemento de máquina se aplica en listados operacionales</p>
                </div>
            </div>
        </div>

        <!-- ── Bloque 4: Detalles de costura (opcionales) ─────────────────── -->
        <div class="space-y-3 rounded-lg border bg-card p-3">
            <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">
                Detalles de costura
            </p>
            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1">
                    <Label class="text-sm">Medida (cm) <span class="text-destructive">*</span></Label>
                    <InputField
                        v-model.number="form.measure"
                        label=""
                        type="number"
                        step="0.01"
                        min="0"
                        placeholder="0.00"
                        :error="showRequiredErrors && isMeasureMissing() ? 'Requerido' : false"
                    />
                    <p class="text-[10px] text-muted-foreground">Longitud de costura; 0 si no aplica</p>
                </div>
                <div class="space-y-1">
                    <Label class="text-sm">Cantidad</Label>
                    <Input v-model.number="form.quantity" type="number" step="1" min="1" placeholder="1" />
                    <p class="text-[10px] text-muted-foreground">Repeticiones por pieza</p>
                </div>
            </div>
        </div>

        <!-- ── Bloque 5: Sábana de producto (obligatoria) ─────────────────── -->
        <div class="space-y-3 rounded-lg border bg-card p-3">
            <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Sábana de producto <span class="text-destructive">*</span></p>
            <div v-if="lockProductSheet && defaultProductSheetId">
                <div class="rounded-md border bg-muted/50 px-2.5 py-2 text-sm text-muted-foreground">{{ selectedProductSheetName }}</div>
            </div>
            <div v-else-if="productSheets?.length">
                <SelectField v-model="form.product_sheet_id_reference" placeholder="Selecciona sábana" :options="productSheetOptions" required />
            </div>
            <p v-else class="text-xs text-destructive">No hay sábanas de producto disponibles. Crea una sábana primero para registrar operaciones.</p>
        </div>

        <!-- ── Bloque 5: Estado (solo edición) ────────────────────────────── -->
        <div v-if="isEditing" class="flex items-center gap-3 rounded-lg border px-3 py-2.5 transition-colors" :class="form.is_active ? 'border-emerald-200 bg-emerald-50/50' : 'border-red-200 bg-red-50/50'">
            <input type="checkbox" id="op-active" v-model="form.is_active" class="h-4 w-4 cursor-pointer rounded accent-emerald-600" />
            <label for="op-active" class="flex-1 cursor-pointer select-none text-sm font-medium">
                {{ form.is_active ? 'Operación activa' : 'Operación inactiva' }}
            </label>
            <TableStatusBadge :label="form.is_active ? 'Activa' : 'Inactiva'" :tone="form.is_active ? 'green' : 'red'" />
        </div>

    </div>
</template>
