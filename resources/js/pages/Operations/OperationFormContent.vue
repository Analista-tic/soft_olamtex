<script setup lang="ts">
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Plus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

export type SectionOption = { id: number; name: string; type?: string };
export type ProductSheetOption = { id: number | string; name: string; reference?: string; type?: string };
export type MachineOption = { id: number; name: string; code?: string };

export interface OperationFormData {
    action: string;
    garment_part: string;
    section_id: number | null;
    measure: number | null;
    quantity: number;
    sam_base: number | null;
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
}>();

const isEditing = computed(() => !!props.operation);

const actionsList = ref<string[]>(props.actions || []);
const garmentPartsList = ref<string[]>(props.garmentParts || []);
const showActionInput = ref(false);
const showGarmentPartInput = ref(false);
const newActionValue = ref('');
const newGarmentPartValue = ref('');

const form = ref<OperationFormData>({
    action: '',
    garment_part: '',
    section_id: props.defaultSectionId ?? props.sections[0]?.id ?? null,
    measure: null,
    quantity: 1,
    sam_base: null,
    product_sheet_id_reference: (props.defaultProductSheetId ?? null) as number | null,
    machine_id_reference: props.machines?.[0]?.id ?? null,
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
                section_id: operation.section_id ?? null,
                measure: operation.measure ?? null,
                quantity: operation.quantity ?? 1,
                sam_base: operation.sam_base ?? null,
                product_sheet_id_reference: operation.product_sheet_id_reference ?? null,
                machine_id_reference: operation.machine_id_reference ?? null,
                codigo: operation.codigo ?? '',
                is_active: operation.is_active ?? true,
            };
        } else {
            form.value = {
                action: '',
                garment_part: '',
                section_id: defaultSectionId ?? props.sections[0]?.id ?? null,
                measure: null,
                quantity: 1,
                sam_base: null,
                product_sheet_id_reference: (defaultProductSheetId ?? null) as number | null,
                machine_id_reference: props.machines?.[0]?.id ?? null,
                codigo: '',
                is_active: true,
            };
        }
    },
    { immediate: true },
);

watch(() => props.actions, (v) => { actionsList.value = v ? [...v] : []; });
watch(() => props.garmentParts, (v) => { garmentPartsList.value = v ? [...v] : []; });

watch(newActionValue, (v) => {
    const n = v.toUpperCase();
    if (v !== n) newActionValue.value = n;
});
watch(newGarmentPartValue, (v) => {
    const n = v.toUpperCase();
    if (v !== n) newGarmentPartValue.value = n;
});

const sectionOptions = computed(() =>
    props.sections.map((s) => ({ id: s.id, name: s.name })),
);
const actionOptions = computed(() => {
    const base = actionsList.value.map((a) => ({ id: a, name: a }));
    const current = form.value.action?.trim();
    if (current && !base.some((o) => String(o.id).toUpperCase() === current.toUpperCase())) {
        return [{ id: current, name: current.toUpperCase() }, ...base];
    }
    return base;
});
const garmentPartOptions = computed(() => {
    const base = garmentPartsList.value.map((g) => ({ id: g, name: g }));
    const current = form.value.garment_part?.trim();
    if (current && !base.some((o) => String(o.id).toUpperCase() === current.toUpperCase())) {
        return [{ id: current, name: current.toUpperCase() }, ...base];
    }
    return base;
});
const productSheetOptions = computed(() =>
    (props.productSheets || []).map((ps) => ({
        id: ps.id,
        name: ps.reference ? `${ps.reference} (${ps.type ?? ''})` : ps.name,
    })),
);
const machineOptions = computed(() =>
    (props.machines || []).map((m) => ({
        id: m.id,
        name: m.code ? `${m.code} · ${m.name}` : m.name,
    })),
);

const generatedName = computed(() => {
    const a = form.value.action?.trim().toUpperCase() ?? '';
    const p = form.value.garment_part?.trim().toUpperCase() ?? '';
    if (a && p) return `${a} ${p}`;
    return a || p || '';
});

const generatedDescription = computed(() => {
    const parts: string[] = [];
    if (generatedName.value) parts.push(generatedName.value);
    if (form.value.measure != null && form.value.measure > 0) parts.push(`${Number(form.value.measure).toFixed(2)} cm`);
    if (form.value.quantity != null && form.value.quantity > 0) parts.push(String(form.value.quantity));
    const sectionName = form.value.section_id
        ? (props.sections.find((s) => s.id === form.value.section_id)?.name ?? '')
        : '';
    if (sectionName) parts.push(sectionName);
    return parts.join(' ').toUpperCase();
});

const selectedProductSheetName = computed(() => {
    const id = props.defaultProductSheetId ?? form.value.product_sheet_id_reference;
    const ps = props.productSheets?.find((s) => s.id === id);
    return ps ? `${ps.reference ?? ps.name} (${ps.type ?? ''})` : '-';
});

function addNewAction() {
    const v = newActionValue.value.trim().toUpperCase();
    if (!v) return;
    if (!actionsList.value.includes(v)) {
        actionsList.value.push(v);
        form.value.action = v;
    }
    newActionValue.value = '';
    showActionInput.value = false;
}

function addNewGarmentPart() {
    const v = newGarmentPartValue.value.trim().toUpperCase();
    if (!v) return;
    if (!garmentPartsList.value.includes(v)) {
        garmentPartsList.value.push(v);
        form.value.garment_part = v;
    }
    newGarmentPartValue.value = '';
    showGarmentPartInput.value = false;
}

function validate(): string | null {
    if (!form.value.action?.trim()) return 'La acción es obligatoria.';
    if (!form.value.garment_part?.trim()) return 'La parte de prenda es obligatoria.';
    if (!form.value.section_id && !isEditing.value) return 'La sección es obligatoria.';
    const sam = form.value.sam_base ?? 0;
    if ((!isEditing.value || form.value.sam_base != null) && (sam <= 0 || !Number.isFinite(sam))) {
        return 'El SAM base es obligatorio y debe ser mayor a 0 (ej: 0.25 min).';
    }
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
        section_id: form.value.section_id,
        measure: form.value.measure,
        quantity: form.value.quantity ?? 1,
        sam_base: form.value.sam_base ?? 0,
        product_sheet_id_reference: form.value.product_sheet_id_reference,
        machine_id_reference: form.value.machine_id_reference,
        is_active: form.value.is_active ?? true,
    };
    emit('submit', payload);
}

defineExpose({ submit, validate });
</script>

<template>
    <div class="space-y-4">
        <!-- Preview nombre -->
        <div v-if="generatedName" class="rounded-md border bg-muted/50 px-3 py-2">
            <p class="mb-0.5 text-[11px] text-muted-foreground">Nombre de la operación</p>
            <p class="font-mono text-sm font-bold">{{ generatedName }}</p>
            <p v-if="generatedDescription" class="mt-0.5 text-xs uppercase text-muted-foreground">{{ generatedDescription }}</p>
        </div>

        <!-- Código (solo edición) + Sección -->
        <div class="grid grid-cols-2 gap-4">
            <div v-if="isEditing" class="space-y-1.5">
                <Label>Código</Label>
                <InputField
                    :model-value="form.codigo ?? ''"
                    label=""
                    class="bg-muted/50 font-mono"
                    disabled
                    placeholder="Generado al guardar"
                />
            </div>
            <SelectField
                v-model="form.section_id"
                label="Sección"
                placeholder="Selecciona sección"
                :options="sectionOptions"
                required
            />
        </div>

        <!-- Acción + Parte -->
        <div class="grid grid-cols-2 gap-4">
            <div>
                <Label>Acción <span class="text-destructive">*</span></Label>
                <SelectField
                    v-if="!showActionInput"
                    v-model="form.action"
                    :options="actionOptions"
                    placeholder="Selecciona acción"
                    required
                >
                    <template #header>
                        <Button type="button" size="sm" variant="outline" class="w-full justify-center gap-1 text-xs" @click="showActionInput = true">
                            <Plus class="h-4 w-4" /> Nueva acción
                        </Button>
                    </template>
                </SelectField>
                <div v-else class="flex gap-2">
                    <InputField v-model="newActionValue" label="" placeholder="Ej: CERRAR, COSER" class="flex-1" />
                    <Button size="sm" @click="addNewAction">Agregar</Button>
                    <Button size="sm" variant="outline" @click="showActionInput = false; newActionValue = ''">Cancelar</Button>
                </div>
            </div>
            <div>
                <Label>Parte de prenda <span class="text-destructive">*</span></Label>
                <SelectField
                    v-if="!showGarmentPartInput"
                    v-model="form.garment_part"
                    :options="garmentPartOptions"
                    placeholder="Selecciona parte"
                    required
                >
                    <template #header>
                        <Button type="button" size="sm" variant="outline" class="w-full justify-center gap-1 text-xs" @click="showGarmentPartInput = true">
                            <Plus class="h-4 w-4" /> Nueva parte
                        </Button>
                    </template>
                </SelectField>
                <div v-else class="flex gap-2">
                    <InputField v-model="newGarmentPartValue" label="" placeholder="Ej: BOLSILLO, PRETINA" class="flex-1" />
                    <Button size="sm" @click="addNewGarmentPart">Agregar</Button>
                    <Button size="sm" variant="outline" @click="showGarmentPartInput = false; newGarmentPartValue = ''">Cancelar</Button>
                </div>
            </div>
        </div>

        <!-- Medida + Cantidad + SAM base -->
        <div class="grid grid-cols-3 gap-4">
            <div class="space-y-1.5">
                <Label>Medida (cm)</Label>
                <InputField v-model.number="form.measure" label="" type="number" step="0.01" min="0" placeholder="0.00" />
            </div>
            <div class="space-y-1.5">
                <Label>Cantidad</Label>
                <Input v-model.number="form.quantity" type="number" step="1" min="1" placeholder="1" />
            </div>
            <div class="space-y-1.5">
                <Label>SAM base (min) <span class="text-destructive">*</span></Label>
                <InputField v-model.number="form.sam_base" label="" type="number" step="0.01" min="0" placeholder="0.25" class="font-mono" />
                <p class="text-[10px] text-muted-foreground">Tiempo real con cronómetro</p>
            </div>
        </div>

        <!-- Descripción auto-generada -->
        <div v-if="generatedDescription" class="space-y-1.5 rounded-md border bg-muted/30 px-3 py-2">
            <Label class="text-muted-foreground">Descripción (auto-generada)</Label>
            <p class="font-mono text-sm uppercase">{{ generatedDescription }}</p>
        </div>

        <!-- Referencia sábana + Máquina -->
        <div class="grid grid-cols-2 gap-4">
            <div v-if="productSheets?.length && !lockProductSheet">
                <Label>Referencia (sábana de producto)</Label>
                <SelectField v-model="form.product_sheet_id_reference" placeholder="Opcional" :options="productSheetOptions" />
            </div>
            <div v-else-if="lockProductSheet && defaultProductSheetId">
                <Label>Referencia (sábana de producto)</Label>
                <div class="text-sm text-muted-foreground py-2">{{ selectedProductSheetName }}</div>
            </div>
            <div v-if="machines?.length">
                <Label>Máquina de referencia</Label>
                <SelectField v-model="form.machine_id_reference" placeholder="Opcional" :options="machineOptions" />
            </div>
        </div>

        <!-- Estado (solo edición) -->
        <div v-if="isEditing" class="flex items-center gap-3 rounded-md border px-3 py-2.5">
            <input type="checkbox" id="op-active" v-model="form.is_active" class="h-4 w-4 rounded" />
            <label for="op-active" class="text-sm">Operación activa</label>
            <Badge :variant="form.is_active ? 'default' : 'secondary'" class="ml-auto text-[10px]">{{ form.is_active ? 'Activa' : 'Inactiva' }}</Badge>
        </div>
    </div>
</template>
