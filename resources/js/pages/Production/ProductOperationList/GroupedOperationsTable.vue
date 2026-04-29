<script setup lang="ts">
import DraggableTableRow from '@/components/DraggableTableRow.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';
import InputField from '@/components/InputField.vue';
import SelectField from '@/components/SelectField.vue';
import type { ProductOperationGroupedRow, ProductOperationSectionInfo } from '@/domain/product-sheet/types';
import { Check, GripVertical, Pencil, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    operationList: ProductOperationGroupedRow[];
    sections: ProductOperationSectionInfo[];
    editingRowIndex: number | null;
    editRow: ProductOperationGroupedRow | null;
    readonly: boolean;
    onRemoveOperation: (index: number) => void;
    onEditStart: (index: number) => void;
    onEditSave: (index: number) => void;
    onEditCancel: () => void;
    onUpdateEditRow: (field: string, value: any) => void;
    getSectionName: (sectionId: number | null) => string;
    getOperationName: (operationId: number | null) => string;
    getMachineDisplayWithSupplement: (row: ProductOperationGroupedRow) => string;
    getFabricBaseName: (fabricBaseId: number | string | null) => string;
    getFabricFactorForRow: (row: ProductOperationGroupedRow) => number;
    getSamOperacion: (row: ProductOperationGroupedRow) => number;
    getOpsPerHour: (row: ProductOperationGroupedRow) => string;
    machinesList: any[];
    fabricBaseOptions: any[];
}>();

const emit = defineEmits<{
    removeOperation: [index: number];
    editStart: [index: number];
    editSave: [index: number];
    editCancel: [];
    updateEditRow: [field: string, value: any];
    dragStart: [index: number];
    dragEnd: [];
    drop: [target: number];
}>();

const draggedIndex = ref<number | null>(null);

const grouped = computed(() => {
    const sectionTypes = ['PREPARACION', 'ENSAMBLE', 'TERMINACION'];
    const result: Record<string, Record<string, (ProductOperationGroupedRow & { originalIndex: number })[]>> = {};

    sectionTypes.forEach((type) => {
        result[type] = {};
    });

    props.operationList.forEach((row, originalIndex) => {
        const section = props.sections.find((s) => s.id === row.section_id);
        const sectionType = section?.type || 'ENSAMBLE';

        if (sectionType === 'PREPARACION' && row.grupo_preparacion) {
            if (!result[sectionType][row.grupo_preparacion]) {
                result[sectionType][row.grupo_preparacion] = [];
            }
            result[sectionType][row.grupo_preparacion].push({ ...row, originalIndex });
        } else if (sectionType !== 'PREPARACION') {
            if (!result[sectionType]['_default']) {
                result[sectionType]['_default'] = [];
            }
            result[sectionType]['_default'].push({ ...row, originalIndex });
        } else {
            // PREPARACION sin grupo
            if (!result['PREPARACION']['Sin clasificar']) {
                result['PREPARACION']['Sin clasificar'] = [];
            }
            result['PREPARACION']['Sin clasificar'].push({ ...row, originalIndex });
        }
    });

    return result;
});

function isEditingRow(origIndex: number): boolean {
    return props.editingRowIndex === origIndex;
}

function handleDragStart(idx: number) {
    draggedIndex.value = idx;
    emit('dragStart', idx);
}

function handleDragEnd() {
    draggedIndex.value = null;
    emit('dragEnd');
}

function handleDrop(target: number) {
    if (draggedIndex.value === null || draggedIndex.value === target) return;
    emit('drop', target);
}
</script>

<template>
    <div v-if="operationList.length > 0" class="mt-5 space-y-4">
        <p v-if="!readonly" class="flex items-center gap-2 px-1 text-[11px] text-muted-foreground/80">
            <GripVertical class="h-3 w-3 shrink-0" />
            Agrupado por sección · Reordena arrastrando · Edita máquina, base tela, factor y grupo
        </p>

        <!-- Cada sección -->
        <div v-for="(sectionType, sectionIndex) in ['PREPARACION', 'ENSAMBLE', 'TERMINACION']" :key="sectionType">
            <div v-if="Object.keys(grouped[sectionType]).length > 0">
                <!-- Encabezado de sección -->
                <div class="mb-2 flex items-center gap-2 border-b-2 border-primary/30 px-2 py-1">
                    <span class="font-semibold text-primary">{{ sectionType }}</span>
                    <span class="text-[11px] text-muted-foreground"
                        >({{
                            Object.values(grouped[sectionType])
                                .flat()
                                .length
                        }}
                        operaciones)</span
                    >
                </div>

                <!-- Subgrupos para PREPARACION, operaciones directas para otros -->
                <div v-if="sectionType === 'PREPARACION'" class="space-y-3">
                    <div v-for="(grupoName, grupoIndex) in Object.keys(grouped[sectionType])" :key="`grupo-${grupoName}`">
                        <!-- Encabezado de grupo -->
                        <div class="mb-1 ml-2 flex items-center gap-2 border-l-4 border-secondary/50 pl-2 text-xs">
                            <span class="font-medium text-secondary">{{ grupoName }}</span>
                        </div>

                        <!-- Tabla de operaciones del grupo -->
                        <div class="overflow-hidden rounded-md bg-muted/20">
                            <table class="w-full table-fixed text-sm">
                                <thead class="sticky top-0 z-10 bg-background/95 backdrop-blur">
                                    <tr class="text-[10px] text-muted-foreground/80" style="border-bottom: 1px solid hsl(var(--border) / 0.5)">
                                        <th class="w-7 py-2"></th>
                                        <th class="w-7 px-2 py-2 text-left font-medium">#</th>
                                        <th class="px-2 py-2 text-left font-medium">
                                            <span class="inline-flex items-center gap-0.5"
                                                >Op. <HelpTooltip text="Operación"
                                            /></span>
                                        </th>
                                        <th class="w-20 px-2 py-2 text-left font-medium">
                                            <span class="inline-flex items-center gap-0.5"
                                                >Máquina <HelpTooltip text="Máquina y suplemento"
                                            /></span>
                                        </th>
                                        <th class="w-14 px-2 py-2 font-medium text-muted-foreground">
                                            <span class="inline-flex items-center justify-end gap-0.5"
                                                >SAM <HelpTooltip text="SAM base (min)"
                                            /></span>
                                        </th>
                                        <th class="w-20 px-2 py-2 text-left font-medium">
                                            <span class="inline-flex items-center gap-0.5"
                                                >Base tela <HelpTooltip text="Base tela con factor"
                                            /></span>
                                        </th>
                                        <th class="w-16 px-2 py-2 text-right font-medium">
                                            <span class="inline-flex items-center justify-end gap-0.5"
                                                >F. op. <HelpTooltip text="Factor operario"
                                            /></span>
                                        </th>
                                        <th class="w-16 px-2 py-2 text-right font-medium">
                                            <span class="inline-flex items-center justify-end gap-0.5"
                                                >SAM op. <HelpTooltip text="SAM total operación"
                                            /></span>
                                        </th>
                                        <th class="w-16 px-2 py-2 text-right font-medium">
                                            <span class="inline-flex items-center justify-end gap-0.5"
                                                >Op./h <HelpTooltip text="Operaciones por hora"
                                            /></span>
                                        </th>
                                        <th v-if="!readonly" class="w-12"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <DraggableTableRow
                                        v-for="(row, rowIndex) in grouped[sectionType][grupoName]"
                                        :key="`${sectionType}-${grupoName}-${rowIndex}`"
                                        :index="row.originalIndex"
                                        :is-draggable="!readonly && editingRowIndex === null"
                                        @drag-start="handleDragStart"
                                        @drag-end="handleDragEnd"
                                        @drop="handleDrop"
                                    >
                                        <td class="px-2 py-2 text-xs text-muted-foreground/70">{{ row.originalIndex + 1 }}</td>
                                        <td class="px-2 py-2 text-xs">
                                            <span class="block truncate" :title="getOperationName(row.operation_id)">{{
                                                getOperationName(row.operation_id)
                                            }}</span>
                                            <span
                                                v-if="!isEditingRow(row.originalIndex) && row.row_notes"
                                                class="mt-0.5 block truncate text-[9px] italic text-muted-foreground/50"
                                                :title="row.row_notes"
                                            >{{ row.row_notes }}</span>
                                            <input
                                                v-if="isEditingRow(row.originalIndex) && editRow"
                                                v-model="editRow.row_notes"
                                                type="text"
                                                placeholder="Nota"
                                                class="mt-1 h-6 w-full rounded border border-input bg-transparent px-1 text-[9px] placeholder:text-muted-foreground/30 focus:outline-none focus:ring-1 focus:ring-ring"
                                            />
                                        </td>
                                        <td class="px-2 py-2 text-xs">
                                            <SelectField
                                                v-if="isEditingRow(row.originalIndex) && editRow"
                                                v-model="editRow.machine_id"
                                                :options="
                                                    machinesList.map((m) => ({
                                                        id: m.id,
                                                        name: `${m.name} (×${(m.supplement_factor ?? 1).toFixed(2)})`,
                                                    }))
                                                "
                                                class="h-8 min-w-[100px]"
                                            />
                                            <span v-else class="truncate text-xs text-muted-foreground/80"
                                                >{{ getMachineDisplayWithSupplement(row)}}</span>
                                        </td>
                                        <td class="px-2 py-2 text-right font-mono text-xs text-muted-foreground/70">
                                            {{ (Number(row.optimal_time) || 0).toFixed(2) }}
                                        </td>
                                        <td class="px-2 py-2 text-xs">
                                            <SelectField
                                                v-if="isEditingRow(row.originalIndex) && editRow"
                                                v-model="editRow.fabric_base_id"
                                                :options="fabricBaseOptions"
                                                class="h-8 min-w-[80px]"
                                            />
                                            <span v-else class="truncate text-[11px] text-muted-foreground/70"
                                                >{{ getFabricBaseName(row.fabric_base_id) }}
                                                <span class="text-[9px]">(×{{ getFabricFactorForRow(row).toFixed(2) }})</span></span
                                            >
                                        </td>
                                        <td class="px-2 py-2 text-right">
                                            <InputField
                                                v-if="isEditingRow(row.originalIndex) && editRow"
                                                v-model.number="editRow.operator_efficiency_factor"
                                                label=""
                                                type="number"
                                                step="0.01"
                                                min="1"
                                                max="2"
                                                class="h-7 w-14 text-right text-xs"
                                                :class="{
                                                    'border-red-500':
                                                        Number(editRow.operator_efficiency_factor) < 1 ||
                                                        Number(editRow.operator_efficiency_factor) > 2,
                                                }"
                                            />
                                            <span v-else class="block text-right font-mono text-xs text-muted-foreground/70">{{
                                                (Number(row.operator_efficiency_factor) || 0).toFixed(2)
                                            }}</span>
                                        </td>
                                        <td class="px-2 py-2 text-right font-mono text-xs font-semibold text-primary">
                                            {{ Number(getSamOperacion(isEditingRow(row.originalIndex) && editRow ? editRow : row)).toFixed(2) }}
                                        </td>
                                        <td class="px-2 py-2 text-right font-mono text-xs text-muted-foreground/70">
                                            {{ getOpsPerHour(isEditingRow(row.originalIndex) && editRow ? editRow : row) }}
                                        </td>
                                        <td v-if="!readonly" class="px-2 py-2">
                                            <div class="flex items-center justify-end gap-1">
                                                <button
                                                    v-if="!isEditingRow(row.originalIndex)"
                                                    type="button"
                                                    @click="emit('editStart', row.originalIndex)"
                                                    class="p-1 text-muted-foreground hover:text-primary"
                                                >
                                                    <Pencil class="h-4 w-4" />
                                                </button>
                                                <button
                                                    v-else
                                                    type="button"
                                                    @click="emit('editSave', row.originalIndex)"
                                                    class="p-1 text-green-600 hover:text-green-700"
                                                >
                                                    <Check class="h-4 w-4" />
                                                </button>
                                                <button
                                                    type="button"
                                                    @click="emit('removeOperation', row.originalIndex)"
                                                    class="p-1 text-muted-foreground hover:text-destructive"
                                                >
                                                    <X class="h-4 w-4" />
                                                </button>
                                            </div>
                                        </td>
                                    </DraggableTableRow>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Operaciones directas para ENSAMBLE y TERMINACION -->
                <div v-else class="overflow-hidden rounded-md bg-muted/20">
                    <table class="w-full table-fixed text-sm">
                        <thead class="sticky top-0 z-10 bg-background/95 backdrop-blur">
                            <tr class="text-[10px] text-muted-foreground/80" style="border-bottom: 1px solid hsl(var(--border) / 0.5)">
                                <th class="w-7 py-2"></th>
                                <th class="w-7 px-2 py-2 text-left font-medium">#</th>
                                <th class="px-2 py-2 text-left font-medium">
                                    <span class="inline-flex items-center gap-0.5"
                                        >Op. <HelpTooltip text="Operación"
                                    /></span>
                                </th>
                                <th class="w-20 px-2 py-2 text-left font-medium">
                                    <span class="inline-flex items-center gap-0.5"
                                        >Máquina <HelpTooltip text="Máquina y suplemento"
                                    /></span>
                                </th>
                                <th class="w-14 px-2 py-2 text-right font-medium">
                                    <span class="inline-flex items-center justify-end gap-0.5"
                                        >SAM <HelpTooltip text="SAM base (min)"
                                    /></span>
                                </th>
                                <th class="w-20 px-2 py-2 text-left font-medium">
                                    <span class="inline-flex items-center gap-0.5"
                                        >Base tela <HelpTooltip text="Base tela con factor"
                                    /></span>
                                </th>
                                <th class="w-16 px-2 py-2 text-right font-medium">
                                    <span class="inline-flex items-center justify-end gap-0.5"
                                        >F. op. <HelpTooltip text="Factor operario"
                                    /></span>
                                </th>
                                <th class="w-16 px-2 py-2 text-right font-medium">
                                    <span class="inline-flex items-center justify-end gap-0.5"
                                        >SAM op. <HelpTooltip text="SAM total operación"
                                    /></span>
                                </th>
                                <th class="w-16 px-2 py-2 text-right font-medium">
                                    <span class="inline-flex items-center justify-end gap-0.5"
                                        >Op./h <HelpTooltip text="Operaciones por hora"
                                    /></span>
                                </th>
                                <th v-if="!readonly" class="w-12"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <DraggableTableRow
                                v-for="(row, rowIndex) in grouped[sectionType]['_default']"
                                :key="`${sectionType}-${rowIndex}`"
                                :index="row.originalIndex"
                                :is-draggable="!readonly && editingRowIndex === null"
                                @drag-start="handleDragStart"
                                @drag-end="handleDragEnd"
                                @drop="handleDrop"
                            >
                                <td class="px-2 py-2 text-xs text-muted-foreground/70">{{ row.originalIndex + 1 }}</td>
                                <td class="px-2 py-2 text-xs">
                                    <span class="block truncate" :title="getOperationName(row.operation_id)">{{
                                        getOperationName(row.operation_id)
                                    }}</span>
                                    <span
                                        v-if="!isEditingRow(row.originalIndex) && row.row_notes"
                                        class="mt-0.5 block truncate text-[9px] italic text-muted-foreground/50"
                                        :title="row.row_notes"
                                    >{{ row.row_notes }}</span>
                                    <input
                                        v-if="isEditingRow(row.originalIndex) && editRow"
                                        v-model="editRow.row_notes"
                                        type="text"
                                        placeholder="Nota"
                                        class="mt-1 h-6 w-full rounded border border-input bg-transparent px-1 text-[9px] placeholder:text-muted-foreground/30 focus:outline-none focus:ring-1 focus:ring-ring"
                                    />
                                </td>
                                <td class="px-2 py-2 text-xs">
                                    <SelectField
                                        v-if="isEditingRow(row.originalIndex) && editRow"
                                        v-model="editRow.machine_id"
                                        :options="
                                            machinesList.map((m) => ({
                                                id: m.id,
                                                name: `${m.name} (×${(m.supplement_factor ?? 1).toFixed(2)})`,
                                            }))
                                        "
                                        class="h-8 min-w-[100px]"
                                    />
                                    <span v-else class="truncate text-xs text-muted-foreground/80"
                                        >{{ getMachineDisplayWithSupplement(row)}}</span>
                                </td>
                                <td class="px-2 py-2 text-right font-mono text-xs text-muted-foreground/70">
                                    {{ (Number(row.optimal_time) || 0).toFixed(2) }}
                                </td>
                                <td class="px-2 py-2 text-xs">
                                    <SelectField
                                        v-if="isEditingRow(row.originalIndex) && editRow"
                                        v-model="editRow.fabric_base_id"
                                        :options="fabricBaseOptions"
                                        class="h-8 min-w-[80px]"
                                    />
                                    <span v-else class="truncate text-[11px] text-muted-foreground/70"
                                        >{{ getFabricBaseName(row.fabric_base_id) }}
                                        <span class="text-[9px]">(×{{ getFabricFactorForRow(row).toFixed(2) }})</span></span
                                    >
                                </td>
                                <td class="px-2 py-2 text-right">
                                    <InputField
                                        v-if="isEditingRow(row.originalIndex) && editRow"
                                        v-model.number="editRow.operator_efficiency_factor"
                                        label=""
                                        type="number"
                                        step="0.01"
                                        min="1"
                                        max="2"
                                        class="h-7 w-14 text-right text-xs"
                                        :class="{
                                            'border-red-500':
                                                Number(editRow.operator_efficiency_factor) < 1 ||
                                                Number(editRow.operator_efficiency_factor) > 2,
                                        }"
                                    />
                                    <span v-else class="block text-right font-mono text-xs text-muted-foreground/70">{{
                                        (Number(row.operator_efficiency_factor) || 0).toFixed(2)
                                    }}</span>
                                </td>
                                <td class="px-2 py-2 text-right font-mono text-xs font-semibold text-primary">
                                    {{ Number(getSamOperacion(isEditingRow(row.originalIndex) && editRow ? editRow : row)).toFixed(2) }}
                                </td>
                                <td class="px-2 py-2 text-right font-mono text-xs text-muted-foreground/70">
                                    {{ getOpsPerHour(isEditingRow(row.originalIndex) && editRow ? editRow : row) }}
                                </td>
                                <td v-if="!readonly" class="px-2 py-2">
                                    <div class="flex items-center justify-end gap-1">
                                        <button
                                            v-if="!isEditingRow(row.originalIndex)"
                                            type="button"
                                            @click="emit('editStart', row.originalIndex)"
                                            class="p-1 text-muted-foreground hover:text-primary"
                                        >
                                            <Pencil class="h-4 w-4" />
                                        </button>
                                        <button
                                            v-else
                                            type="button"
                                            @click="emit('editSave', row.originalIndex)"
                                            class="p-1 text-green-600 hover:text-green-700"
                                        >
                                            <Check class="h-4 w-4" />
                                        </button>
                                        <button
                                            type="button"
                                            @click="emit('removeOperation', row.originalIndex)"
                                            class="p-1 text-muted-foreground hover:text-destructive"
                                        >
                                            <X class="h-4 w-4" />
                                        </button>
                                    </div>
                                </td>
                            </DraggableTableRow>
                        </tbody>
                    </table>
                </div>

                <!-- Espaciador entre secciones -->
                <div class="mb-3"></div>
            </div>
        </div>
    </div>
</template>
