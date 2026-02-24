<script setup lang="ts">
import DialogActionButtons from '@/components/common/DialogActionButtons.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

type Section = { id: number; name: string; type?: string };
type ProductSheetOption = { id: number; name: string };
type MachineOption = { id: number; name: string };
type OperationRecord = {
    id: number;
    codigo: string | null;
    action: string;
    garment_part: string;
    section_id: number | null;
    location: string | null;
    measure: number | null;
    quantity: number | null;
    description: string | null;
    sam_base: number | null;
    product_sheet_id_reference: number | null;
    machine_id_reference: number | null;
    is_active: boolean;
};

const props = defineProps<{
    open: boolean;
    operation: OperationRecord | null;
    sections: Section[];
    actions: string[];
    garmentParts: string[];
    productSheets: ProductSheetOption[];
    machines: MachineOption[];
}>();
const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'saved'): void;
}>();

const localOpen = ref(props.open);
watch(
    () => props.open,
    (v) => {
        localOpen.value = v;
    },
);
watch(localOpen, (v) => {
    if (!v) emit('close');
});

const isEditing = computed(() => !!props.operation);
const isSaving = ref(false);
const error = ref('');

const form = ref({
    codigo: '',
    action: '',
    garment_part: '',
    section_id: null as number | null,
    measure: null as number | null,
    quantity: 1 as number,
    sam_base: null as number | null,
    product_sheet_id_reference: null as number | null,
    machine_id_reference: null as number | null,
    is_active: true,
});

watch(
    () => props.open,
    (opened) => {
        if (!opened) return;
        error.value = '';
        if (props.operation) {
            form.value = {
                codigo: props.operation.codigo ?? '',
                action: props.operation.action ?? '',
                garment_part: props.operation.garment_part ?? '',
                section_id: props.operation.section_id,
                measure: props.operation.measure,
                quantity: props.operation.quantity ?? 1,
                sam_base: props.operation.sam_base,
                product_sheet_id_reference: props.operation.product_sheet_id_reference,
                machine_id_reference: props.operation.machine_id_reference ?? null,
                is_active: props.operation.is_active ?? true,
            };
        } else {
            form.value = {
                codigo: '',
                action: '',
                garment_part: '',
                section_id: null,
                measure: null,
                quantity: 1,
                sam_base: null,
                product_sheet_id_reference: null,
                machine_id_reference: null,
                is_active: true,
            };
        }
    },
);

const sectionOptions = computed(() =>
    props.sections.map((s) => ({ id: s.id, name: (s.name ?? '').toUpperCase() })),
);
const actionOptions = computed(() => {
    const base = (props.actions || []).map((a) => ({ id: a, name: (a ?? '').toUpperCase() }));
    const current = form.value.action?.trim();
    if (current && !base.some((o) => String(o.id).toUpperCase() === current.toUpperCase())) {
        return [{ id: current, name: current.toUpperCase() }, ...base];
    }
    return base;
});
const garmentPartOptions = computed(() => {
    const base = (props.garmentParts || []).map((g) => ({ id: g, name: (g ?? '').toUpperCase() }));
    const current = form.value.garment_part?.trim();
    if (current && !base.some((o) => String(o.id).toUpperCase() === current.toUpperCase())) {
        return [{ id: current, name: current.toUpperCase() }, ...base];
    }
    return base;
});
const productSheetOptions = computed(() => props.productSheets.map((ps) => ({ id: ps.id, name: ps.name })));
const machineOptions = computed(() => (props.machines || []).map((m) => ({ id: m.id, name: m.name })));

const generatedName = computed(() => {
    const a = form.value.action.trim();
    const p = form.value.garment_part.trim();
    if (!a && !p) return '';
    return `${a} ${p}`.trim().toUpperCase();
});

// Descripción auto-generada: Acción + Parte + Medida + Cantidad + Sección (mayúsculas, no editable)
const generatedDescription = computed(() => {
    const parts: string[] = [];
    if (generatedName.value) parts.push(generatedName.value);
    if (form.value.measure != null && form.value.measure > 0) parts.push(`${form.value.measure} cm`);
    if (form.value.quantity != null && form.value.quantity > 0) parts.push(String(form.value.quantity ?? 1));
    const sectionName = form.value.section_id ? (props.sections.find((s) => s.id === form.value.section_id)?.name ?? '') : '';
    if (sectionName) parts.push(sectionName);
    return parts.join(' ').toUpperCase();
});

async function save() {
    error.value = '';
    if (!form.value.action.trim()) {
        error.value = 'La acción es obligatoria.';
        return;
    }
    if (!form.value.garment_part.trim()) {
        error.value = 'La parte de prenda es obligatoria.';
        return;
    }
    if (!form.value.section_id && !isEditing.value) {
        error.value = 'La sección es obligatoria.';
        return;
    }
    const samBase = form.value.sam_base ?? 0;
    if ((!isEditing.value || form.value.sam_base != null) && (samBase <= 0 || !Number.isFinite(samBase))) {
        error.value = 'El SAM base es obligatorio y debe ser mayor a 0 (ej: 0.25 min).';
        return;
    }
    isSaving.value = true;
    try {
        const { codigo, ...rest } = form.value;
        const payload: Record<string, unknown> = {
            ...rest,
            action: (rest.action as string)?.trim().toUpperCase() ?? '',
            garment_part: (rest.garment_part as string)?.trim().toUpperCase() ?? '',
        };
        if (isEditing.value && props.operation) {
            await axios.put(`/produccion/operaciones/${props.operation.id}`, payload);
        } else {
            // No enviar codigo ni description: se generan en backend
            await axios.post('/produccion/operaciones', payload);
        }
        emit('saved');
    } catch (e: any) {
        const msg = e?.response?.data?.message ?? 'Error al guardar la operación.';
        error.value = msg;
    } finally {
        isSaving.value = false;
    }
}
</script>

<template>
    <Dialog v-model:open="localOpen">
        <DialogContent class="flex max-h-[90vh] max-w-2xl flex-col p-4">
            <DialogHeader>
                <DialogTitle>{{ isEditing ? 'Editar operación' : 'Nueva operación' }}</DialogTitle>
                <DialogDescription>
                    {{
                        isEditing
                            ? 'Modifica los datos de la operación. La combinación acción + parte debe ser única.'
                            : 'Registra una nueva operación. La combinación acción + parte debe ser única.'
                    }}
                </DialogDescription>
            </DialogHeader>

            <div class="flex-1 overflow-y-auto p-1" style="scrollbar-width: thin">
                <div v-if="error" class="mb-4 rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                    {{ error }}
                </div>

                <!-- Preview nombre generado -->
                <div v-if="generatedName" class="mb-4 rounded-md border bg-muted/50 px-3 py-2">
                    <p class="mb-0.5 text-[11px] text-muted-foreground">Nombre de la operación</p>
                    <p class="font-mono text-sm font-bold">{{ generatedName }}</p>
                    <p v-if="generatedDescription" class="mt-0.5 text-xs uppercase text-muted-foreground">{{ generatedDescription }}</p>
                </div>

                <div class="grid gap-4">
                    <!-- Fila 1: Código + Sección -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <Label>Código</Label>
                            <InputField
                                :model-value="form.codigo ?? ''"
                                label=""
                                class="font-mono bg-muted/50"
                                disabled
                                placeholder="Generado al guardar"
                            />
                        </div>
                        <SelectField v-model="form.section_id" label="Sección" placeholder="Selecciona sección" :options="sectionOptions" required />
                    </div>

                    <!-- Fila 2: Acción + Parte de prenda -->
                    <div class="grid grid-cols-2 gap-4">
                        <SelectField
                            v-model="form.action"
                            label="Acción"
                            placeholder="Selecciona acción"
                            :options="actionOptions"
                            :allow-custom="true"
                            custom-option-label="Añadir nueva acción"
                            required
                        />
                        <SelectField
                            v-model="form.garment_part"
                            label="Parte de prenda"
                            placeholder="Selecciona parte"
                            :options="garmentPartOptions"
                            :allow-custom="true"
                            custom-option-label="Añadir nueva parte"
                            required
                        />
                    </div>

                    <!-- Fila 3: Medida + Cantidad + SAM base -->
                    <div class="grid grid-cols-3 gap-4">
                        <div class="space-y-1.5">
                            <Label>Medida (cm)</Label>
                            <InputField v-model.number="form.measure" label="" type="number" step="0.01" min="0" placeholder="0.00" />
                        </div>
                        <div class="space-y-1.5">
                            <Label>Cantidad</Label>
                            <Input v-model.number="form.quantity" type="number" step="1" min="1" placeholder="1" default-value="1"/>
                        </div>
                        <div class="space-y-1.5">
                            <Label>SAM base (min)</Label>
                            <InputField
                                v-model.number="form.sam_base"
                                label=""
                                type="number"
                                step="0.0001"
                                min="0"
                                placeholder="0.0000"
                                class="font-mono"
                            />
                            <p class="text-[10px] text-muted-foreground">Tiempo real con cronómetro</p>
                        </div>
                    </div>

                    <!-- Descripción auto-generada (solo lectura, no editable) -->
                    <div v-if="generatedDescription" class="space-y-1.5 rounded-md border bg-muted/30 px-3 py-2">
                        <Label class="text-muted-foreground">Descripción (auto-generada)</Label>
                        <p class="font-mono text-sm uppercase">{{ generatedDescription }}</p>
                    </div>

                    <!-- Referencia sábana + Máquina -->
                    <div class="grid grid-cols-2 gap-4">
                        <SelectField
                            v-model="form.product_sheet_id_reference"
                            label="Referencia (Sábana de producto)"
                            placeholder="Selecciona una sábana"
                            :options="productSheetOptions"
                        />
                        <SelectField
                            v-model="form.machine_id_reference"
                            label="Máquina de referencia"
                            placeholder="Selecciona máquina"
                            :options="machineOptions"
                        />
                    </div>

                    <!-- Estado (solo en edición) -->
                    <div v-if="isEditing" class="flex items-center gap-3 rounded-md border px-3 py-2.5">
                        <input
                            type="checkbox"
                            id="op-active"
                            v-model="form.is_active"
                            class="h-4 w-4 rounded border-input text-primary focus:ring-primary"
                        />
                        <label for="op-active" class="text-sm"> Operación activa </label>
                        <Badge :variant="form.is_active ? 'default' : 'secondary'" class="ml-auto text-[10px]">
                            {{ form.is_active ? 'Activa' : 'Inactiva' }}
                        </Badge>
                    </div>
                </div>
            </div>

            <DialogFooter class="mt-4 border-t pt-4">
                <DialogActionButtons
                    :cancel-label="'Cancelar'"
                    :submit-label="isEditing ? 'Actualizar' : 'Crear operación'"
                    :loading="isSaving"
                    @cancel="localOpen = false"
                    @submit="save"
                />
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
