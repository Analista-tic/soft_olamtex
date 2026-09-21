<script setup lang="ts">
import AlertStack from '@/components/common/AlertStack.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import DeleteButton from '@/components/common/DeleteButton.vue';
import DialogActionButtons from '@/components/common/DialogActionButtons.vue';
import DraggableTableRow from '@/components/DraggableTableRow.vue';
import FormDialogContent from '@/components/FormDialogContent.vue';
import FormDialogFooter from '@/components/FormDialogFooter.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';
import SelectField from '@/components/SelectField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { TooltipProvider } from '@/components/ui/tooltip';
import type {
    FabricBaseOption,
    MachineOption,
    OperationOption,
    OperationSection,
    ProductOperationDialogInitialData,
    ProductOperationDialogRow,
    ProductOperationEntityCreatedData,
    ProductOperationPayload,
    ProductSheetDialogOption,
} from '@/domain/product-sheet/types';
import { CornerDownLeft, GripVertical, Lightbulb, Pencil, Plus, PlusCircle, X } from 'lucide-vue-next';
import { computed, PropType, ref, watch } from 'vue';
import AddNew from './AddNew.vue';

const props = defineProps({
    open: Boolean,
    mode: {
        type: String as PropType<'create' | 'edit'>,
        default: 'create',
    },
    initialData: {
        type: Object as PropType<ProductOperationDialogInitialData | null>,
        default: null,
    },
    productSheets: {
        type: Array as () => ProductSheetDialogOption[],
        default: () => [],
    },
    excludedProductSheetIds: {
        type: Array as () => (number | string)[],
        default: () => [],
    },
    sections: {
        type: Array as () => OperationSection[],
        default: () => [],
    },
    operations: {
        type: Array as () => OperationOption[],
        default: () => [],
    },
    fabricBases: {
        type: Array as () => FabricBaseOption[],
        default: () => [],
    },
    machines: {
        type: Array as () => MachineOption[],
        default: () => [],
    },
    actions: {
        type: Array as () => string[],
        default: () => [],
    },
    garmentParts: {
        type: Array as () => string[],
        default: () => [],
    },
    readonly: {
        type: Boolean,
        default: false,
    },
    preparationGroups: {
        type: Array as () => string[],
        default: () => [],
    },
    defaultProductSheetId: {
        type: [Number, String] as PropType<number | string | null>,
        default: null,
    },
});

const emit = defineEmits<{
    (event: 'save', payload: ProductOperationPayload[]): void;
    (event: 'close'): void;
}>();

function normalizeComparableId(value: number | string | null | undefined): string {
    return String(value ?? '').trim();
}

function idsMatch(a: number | string | null | undefined, b: number | string | null | undefined): boolean {
    const left = normalizeComparableId(a);
    const right = normalizeComparableId(b);
    return left !== '' && right !== '' && left === right;
}

function buildProductSheetLabel(sheet: ProductSheetDialogOption): string {
    const parts = [
        String(sheet.sample_reference ?? '').trim().toUpperCase(),
        String(sheet.product_reference ?? '').trim().toUpperCase(),
        String(sheet.product_reference_2 ?? '').trim().toUpperCase(),
    ].filter(Boolean);
    const version = Number(sheet.version ?? 1);
    return (parts.length ? parts.join(' / ') : '-') + ` / v${version}`;
}

const validationError = ref('');
const localOpen = ref(props.open);

function setValidationError(message: string) {
    validationError.value = message;
}

function clearValidationError() {
    validationError.value = '';
}

const sectionsList = ref<OperationSection[]>([]);
const operationsList = ref<OperationOption[]>([]);
const locallyCreatedOperations = ref<OperationOption[]>([]);
const machinesList = ref<MachineOption[]>([]);
const fabricBasesList = ref<FabricBaseOption[]>([]);
const preparationGroupsList = ref<string[]>([]);
const preparationGroupSearchTerm = ref('');
const pendingAutoAddOperation = ref(false);
let rowKeySequence = 0;

const NONE_FABRIC_BASE_LABEL = 'NS / NINGUNA';

function buildRowKey(prefix = 'row'): string {
    rowKeySequence += 1;
    return `${prefix}-${rowKeySequence}`;
}

function upsertOperationOption(target: OperationOption[], operation: OperationOption): OperationOption[] {
    const existingIndex = target.findIndex((item) => idsMatch(item.id, operation.id));
    if (existingIndex >= 0) {
        const next = [...target];
        next[existingIndex] = { ...next[existingIndex], ...operation };
        return next;
    }

    return [...target, operation];
}

/** Fabric base heredada de la sábana seleccionada. */
const selectedSheetFabricBaseId = computed(() => {
    if (!productSheetId.value) return null;
    const sheet = props.productSheets.find((s) => idsMatch(s.id, productSheetId.value));
    return sheet?.fabric_base_id ?? null;
});

const selectedSheetFabricFactor = computed(() => {
    const id = selectedSheetFabricBaseId.value;
    if (id == null) return 1.0;
    const opt = fabricBasesList.value.find((f) => idsMatch(f.id, id));
    // `factor_base` y `factor_base_tela` eran las formas que tenía el JSON de settings
    // antes de que las bases pasaran a la tabla fabric_bases, que expone solo `factor`.
    const factor = opt?.factor ?? null;
    const num = Number(factor);
    return Number.isFinite(num) && num > 0 ? num : 1.0;
});

const selectedSheetFabricBaseName = computed(() => {
    const id = selectedSheetFabricBaseId.value;
    if (id == null) return NONE_FABRIC_BASE_LABEL;
    const opt = fabricBasesList.value.find((f) => idsMatch(f.id, id));
    return opt?.nombre ?? opt?.name ?? opt?.codigo ?? String(id);
});

function isSectionPreparacion(sectionId: number | string | null): boolean {
    if (sectionId === null || sectionId === undefined || String(sectionId).trim() === '') return false;
    const section = sectionsList.value.find((s) => idsMatch(s.id, sectionId));
    return (section?.type ?? '').toLowerCase() === 'preparacion';
}

/**
 * Sugiere un grupo de preparación existente a partir del garment_part de la
 * operación (ej. "MANGA LARGA" -> "MANGA" si ese grupo ya existe). Nunca crea
 * un grupo nuevo aquí: si no hay coincidencia exacta o de palabra completa,
 * devuelve null y el usuario elige/crea uno manualmente, igual que hoy — así
 * se evita generar grupos casi-duplicados a partir de texto libre.
 */
function suggestPreparationGroup(garmentPart: string | null | undefined, groups: string[]): string | null {
    const normalized = String(garmentPart ?? '').trim().toUpperCase().replace(/\s+/g, ' ');
    if (!normalized) return null;

    const tokens = normalized.split(' ');

    return groups.find((g) => {
        const groupNorm = g.trim().toUpperCase();
        return groupNorm !== '' && (groupNorm === normalized || tokens.includes(groupNorm));
    }) ?? null;
}

const createEmptyRow = (): ProductOperationDialogRow => ({
    row_key: buildRowKey('new'),
    id: null,
    section_id: null,
    operation_id: null,
    optimal_time: '0.00',
    factor_operario: 100,
    grupo_preparacion: null,
    row_notes: '',
    sam_total_base: 0,
});
const currentRow = ref<ProductOperationDialogRow>(createEmptyRow());

function syncListsFromProps() {
    sectionsList.value = Array.isArray(props.sections) ? [...props.sections] : [];
    const operationCatalog = Array.isArray(props.operations) ? [...props.operations] : [];
    operationsList.value = locallyCreatedOperations.value.reduce<OperationOption[]>(
        (acc, operation) => upsertOperationOption(acc, operation),
        operationCatalog,
    );
    machinesList.value = Array.isArray(props.machines) ? [...props.machines] : [];
    fabricBasesList.value = Array.isArray(props.fabricBases) ? [...props.fabricBases] : [];
    preparationGroupsList.value = Array.isArray(props.preparationGroups)
        ? [...new Set(props.preparationGroups.map((g) => String(g ?? '').trim()).filter(Boolean))]
        : [];
}

function normalizePreparationGroupName(value: unknown): string {
    return String(value ?? '')
        .trim()
        .replace(/\s+/g, ' ');
}

function addPreparationGroupFromSelect(rawName?: string) {
    const group = normalizePreparationGroupName(rawName ?? preparationGroupSearchTerm.value);

    if (!group) {
        const msg = 'Escribe el nombre del grupo para agregarlo.';
        setValidationError(msg);
        return;
    }

    const exists = preparationGroupsList.value.some((g) => g.toLowerCase() === group.toLowerCase());
    if (exists) {
        currentRow.value.grupo_preparacion = preparationGroupsList.value.find((g) => g.toLowerCase() === group.toLowerCase()) ?? group;
        clearValidationError();
        return;
    }

    preparationGroupsList.value = [...preparationGroupsList.value, group];
    currentRow.value.grupo_preparacion = group;
    preparationGroupSearchTerm.value = '';
    clearValidationError();
}

function addPreparationGroupFromEditRow(rawName?: string) {
    const group = normalizePreparationGroupName(rawName ?? preparationGroupSearchTerm.value);

    if (!group) {
        const msg = 'Escribe el nombre del grupo para agregarlo.';
        setValidationError(msg);
        return;
    }

    const existing = preparationGroupsList.value.find((g) => g.toLowerCase() === group.toLowerCase());
    if (!existing) {
        preparationGroupsList.value = [...preparationGroupsList.value, group];
    }

    if (editRow.value) {
        editRow.value.grupo_preparacion = existing ?? group;
    }

    preparationGroupSearchTerm.value = '';
    clearValidationError();
}

watch(
    () => props.open,
    (value) => {
        localOpen.value = value;
        if (value) {
            clearValidationError();
            syncListsFromProps();
            initializeForm();
        }
    },
    { immediate: true },
);
watch(localOpen, (value) => {
    if (!value) emit('close');
});

watch(
    () => props.operations,
    () => {
        syncListsFromProps();
    },
);

watch(
    () => currentRow.value.operation_id,
    (opId) => {
        if (!opId) return;
        const op = operationsList.value.find((o) => idsMatch(o.id, opId));
        if (!op) return;
        if (op.section_id) currentRow.value.section_id = Number(op.section_id);
        // Auto-set factor_operario from operation (percentage: 85, 100, 120...)
        currentRow.value.factor_operario = Number(op.factor_operario ?? 100);
        // SAM base
        const samTotal = Number(op.sam_base ?? 0);
        currentRow.value.sam_total_base = Number.isFinite(samTotal) ? samTotal : 0;
        currentRow.value.optimal_time = currentRow.value.sam_total_base.toFixed(2);
        // Grupo de preparación: se sugiere desde garment_part si la sección
        // es PREPARACION y hay una coincidencia confiable; si no, se limpia
        // para que el usuario elija/cree uno manualmente (como hoy).
        if (isSectionPreparacion(currentRow.value.section_id)) {
            currentRow.value.grupo_preparacion = suggestPreparationGroup(op.garment_part, preparationGroupsList.value);
        } else {
            currentRow.value.grupo_preparacion = null;
        }

        if (pendingAutoAddOperation.value) {
            pendingAutoAddOperation.value = false;
            void Promise.resolve().then(() => {
                if (currentRow.value.operation_id === opId) {
                    addOperation();
                }
            });
        }
    },
);

watch(
    () => props.initialData,
    () => {
        if (localOpen.value && props.mode === 'edit') initializeForm();
    },
);

const isEditMode = computed(() => props.mode === 'edit');

/** True cuando la fila en alta necesita un grupo de preparación y no se pudo sugerir uno automáticamente. */
const needsManualPreparationGroup = computed(
    () => isSectionPreparacion(currentRow.value.section_id) && !currentRow.value.grupo_preparacion,
);

const availableProductSheets = computed(() => {
    const excluded = new Set((props.excludedProductSheetIds ?? []).map((id) => normalizeComparableId(id as number | string | null | undefined)));
    const editingId = props.mode === 'edit' && props.initialData?.productSheetId != null ? normalizeComparableId(props.initialData.productSheetId) : null;
    return props.productSheets.filter((sheet) => {
        const sheetId = normalizeComparableId(sheet.id);
        return !excluded.has(sheetId) || sheetId === editingId;
    });
});

const productSheetId = ref<number | string | null>(availableProductSheets.value[0]?.id ?? props.productSheets[0]?.id ?? null);

/** garment_type de la sábana actualmente seleccionada (superior | inferior | null). */
const selectedGarmentType = computed(() => {
    if (!productSheetId.value) return null;
    return props.productSheets.find((s) => idsMatch(s.id, productSheetId.value))?.garment_type ?? null;
});

watch(
    () => availableProductSheets.value,
    (next) => {
        if (!next.length) {
            productSheetId.value = null;
            return;
        }
        if (!next.some((s) => idsMatch(s.id, productSheetId.value))) productSheetId.value = next[0].id;
    },
    { deep: true },
);

const operationList = ref<ProductOperationDialogRow[]>([]);
const editingRowIndex = ref<number | null>(null);
const editRow = ref<ProductOperationDialogRow | null>(null);
const addNewDialogOpen = ref(false);

const availableOperationOptions = computed(() => {
    const selectedOperationIds = new Set(
        operationList.value
            .map((row) => row.operation_id)
            .filter((id): id is number => typeof id === 'number' && Number.isFinite(id)),
    );

    return operationsList.value.filter((operation) => !selectedOperationIds.has(Number(operation.id)));
});

const addNewEntity = ref<'section' | 'operation' | 'machine'>('section');

function openAddNewDialog(entity: 'section' | 'operation' | 'machine') {
    addNewEntity.value = entity;
    addNewDialogOpen.value = true;
}

function handleEntityCreated(data: ProductOperationEntityCreatedData) {
    if (addNewEntity.value === 'section') {
        sectionsList.value.push({ id: data.id, name: data.name ?? '', type: data.type ?? '' });
        currentRow.value.section_id = data.id;
    } else if (addNewEntity.value === 'operation') {
        const createdOperation: OperationOption = {
            id: data.id,
            name: data.name ?? `${data.action ?? ''} ${data.garment_part ?? ''}`.trim(),
            action: data.action,
            garment_part: data.garment_part,
            section_id: data.section_id ?? data.section?.id ?? null,
            sam_base: data.sam_base ?? null,
            factor_operario: data.factor_operario ?? 100,
            machine_id_reference: data.machine_id_reference ?? null,
            product_sheet_id_reference: data.product_sheet_id_reference ?? null,
        };
        locallyCreatedOperations.value = upsertOperationOption(locallyCreatedOperations.value, createdOperation);
        operationsList.value = upsertOperationOption(operationsList.value, createdOperation);

        currentRow.value.operation_id = data.id;
        if (data.section_id != null || data.section?.id != null) {
            currentRow.value.section_id = data.section_id ?? data.section?.id ?? null;
        }
        const samTotal = Number(data.sam_base ?? 0);
        currentRow.value.sam_total_base = Number.isFinite(samTotal) ? samTotal : 0;
        currentRow.value.optimal_time = currentRow.value.sam_total_base.toFixed(2);
        currentRow.value.factor_operario = Number(data.factor_operario ?? 100);
        addOperation();
    } else if (addNewEntity.value === 'machine') {
        const machineData = data as ProductOperationEntityCreatedData & { supplement_factor?: number };
        machinesList.value.push({
            id: data.id,
            code: data.code ?? '',
            name: data.name ?? '',
            supplement_factor: machineData.supplement_factor ?? 1.0,
        });
    }
    addNewDialogOpen.value = false;
}

function isEditingRow(i: number) {
    return editingRowIndex.value === i;
}
function startEditRow(i: number) {
    editingRowIndex.value = i;
    editRow.value = { ...operationList.value[i] };
}
function cancelEditRow() {
    editingRowIndex.value = null;
    editRow.value = null;
}

function saveEditRow(i: number) {
    if (!editRow.value) return;
    operationList.value[i] = {
        ...operationList.value[i],
        row_notes: editRow.value.row_notes,
        grupo_preparacion: editRow.value.grupo_preparacion,
    };
    cancelEditRow();
}

function getSupplementForRow(row: ProductOperationDialogRow): number {
    const op = operationsList.value.find((o) => idsMatch(o.id, row.operation_id));
    const m = machinesList.value.find((m) => idsMatch(m.id, op?.machine_id_reference));
    const f = m?.supplement_factor ?? 1;
    return typeof f === 'number' && f >= 1 ? f : 1;
}

/** SAM ajustado = SAM_base / (factor_operario / 100) */
function getSamAjustado(row: ProductOperationDialogRow): number {
    const samBase = Number(row.sam_total_base ?? row.optimal_time ?? 0);
    const factorOp = Number(row.factor_operario ?? 100);
    if (!Number.isFinite(samBase) || factorOp <= 0) return 0;
    return samBase / (factorOp / 100);
}

/** SAM con suplemento = SAM_ajustado × suplemento_máquina */
function getSamConSuplemento(row: ProductOperationDialogRow): number {
    return getSamAjustado(row) * getSupplementForRow(row);
}

/** SAM total unitario = SAM con suplemento × factor base tela */
function getSamUnitario(row: ProductOperationDialogRow): number {
    return Number((getSamConSuplemento(row) * selectedSheetFabricFactor.value).toFixed(2));
}

function getOperationQuantity(row: ProductOperationDialogRow): number {
    const operation = operationsList.value.find((o) => idsMatch(o.id, row.operation_id));
    const quantity = Number(operation?.quantity ?? 1);
    return Number.isFinite(quantity) && quantity > 0 ? Math.floor(quantity) : 1;
}

function getSamOperacion(row: ProductOperationDialogRow): number {
    return Number((getSamUnitario(row) * getOperationQuantity(row)).toFixed(2));
}

function getOpsPerHour(row: ProductOperationDialogRow): number {
    const samOp = getSamOperacion(row);
    if (samOp <= 0) return 0;
    return Math.floor(60 / samOp);
}

function getOperationName(id: number | null): string {
    if (id == null) return '-';

    const found = operationsList.value.find((o) => idsMatch(o.id, id))?.name;
    if (found && String(found).trim() !== '') return found;

    return `Operación #${id}`;
}

function getOperationOptionLabel(operation: OperationOption): string {
    const code = operation.code ? `${String(operation.code)}  ` : '';
    const baseName = String(operation.name ?? '').trim() || `${operation.action ?? ''} ${operation.garment_part ?? ''}`.trim();

    const detailParts: string[] = [];
    if (operation.measure != null && Number(operation.measure) > 0) detailParts.push(`${Number(operation.measure)}CM`);
    if (operation.quantity != null && Number(operation.quantity) > 0) detailParts.push(`X${Number(operation.quantity)}`);
    const samBase = Number(operation.sam_base ?? 0);
    if (Number.isFinite(samBase) && samBase > 0) detailParts.push(`SAM ${samBase.toFixed(2)}`);

    return detailParts.length ? `${code}${baseName} ${detailParts.join(', ')}` : `${code}${baseName}`;
}

function handleEnterAddOperation(): void {
    if (props.readonly) return;
    if (!currentRow.value.operation_id) {
        pendingAutoAddOperation.value = true;
        return;
    }
    addOperation();
}

function getMachineName(id: string | number | null): string {
    const m = machinesList.value.find((m) => idsMatch(m.id, id));
    if (!m) return '-';
    const code = (m.code ?? '').trim();
    const name = (m.name ?? '').trim();
    if (code && name && code.toUpperCase() !== name.toUpperCase()) return `${code} · ${name}`;
    return code || name || '-';
}

function getMachineDisplayWithSupplement(row: ProductOperationDialogRow): string {
    const op = operationsList.value.find((o) => idsMatch(o.id, row.operation_id));
    const name = getMachineName(op?.machine_id_reference ?? null);
    const sup = getSupplementForRow(row);
    return sup !== 1 ? `${name} (×${sup.toFixed(2)})` : name;
}

function addOperation() {
    pendingAutoAddOperation.value = false;
    const samBase = Number.parseFloat(currentRow.value.optimal_time);

    if (!currentRow.value.operation_id) {
        setValidationError('Selecciona una operación.');
        return;
    }
    if (samBase <= 0) {
        const msg = 'La operación seleccionada no tiene SAM base. Regístralo primero en la base de datos de operaciones.';
        setValidationError(msg);
        return;
    }
    if (isSectionPreparacion(currentRow.value.section_id) && !currentRow.value.grupo_preparacion) {
        const msg = 'Para operaciones de PREPARACIÓN, debes seleccionar o crear un grupo de preparación.';
        setValidationError(msg);
        return;
    }

    const opId = currentRow.value.operation_id;
    const isDuplicate = operationList.value.some((r) => r.operation_id === opId);
    if (isDuplicate) {
        const msg = 'Esta operación ya fue añadida al listado. No se puede repetir en la misma sábana.';
        setValidationError(msg);
        return;
    }

    operationList.value.push({ ...currentRow.value });
    currentRow.value = createEmptyRow();
    pendingAutoAddOperation.value = false;
    clearValidationError();
}

const deleteConfirmOpen = ref(false);
const deleteConfirmIndex = ref<number | null>(null);

function confirmRemoveOperation(i: number) {
    deleteConfirmIndex.value = i;
    deleteConfirmOpen.value = true;
}

function removeOperation(i: number) {
    operationList.value.splice(i, 1);
    if (editingRowIndex.value === i) cancelEditRow();
    else if (editingRowIndex.value !== null && i < editingRowIndex.value) editingRowIndex.value -= 1;
}

function handleConfirmDelete() {
    if (deleteConfirmIndex.value !== null) {
        removeOperation(deleteConfirmIndex.value);
    }
    deleteConfirmIndex.value = null;
}

const draggedIndex = ref<number | null>(null);
const originalOrderSnapshot = ref<typeof operationList.value>([]);

function handleDragStart(i: number) {
    draggedIndex.value = i;
}
function handleDragEnd() {
    draggedIndex.value = null;
}
function handleDrop(target: number) {
    if (draggedIndex.value === null || draggedIndex.value === target) return;
    const items = [...operationList.value];
    const [d] = items.splice(draggedIndex.value, 1);
    items.splice(target, 0, d);
    operationList.value = items;
    draggedIndex.value = null;
}

const isOrderChanged = computed(() => {
    if (originalOrderSnapshot.value.length !== operationList.value.length) return false;
    return originalOrderSnapshot.value.some((row, i) => row.row_key !== operationList.value[i]?.row_key);
});

function resetOrder() {
    operationList.value = originalOrderSnapshot.value.map((row) => ({ ...row }));
}

function initializeForm() {
    cancelEditRow();
    if (props.mode === 'edit' && props.initialData) {
        productSheetId.value = props.initialData.productSheetId ?? props.productSheets[0]?.id ?? null;
        operationList.value = props.initialData.operations.map((row) => ({
            row_key: row.id != null ? `persisted-${row.id}` : buildRowKey('edit'),
            id: row.id ?? null,
            section_id: row.section_id,
            operation_id: row.operation_id,
            optimal_time: row.optimal_time,
            factor_operario: row.factor_operario ?? 100,
            grupo_preparacion: row.grupo_preparacion ?? null,
            row_notes: row.row_notes ?? '',
            sam_total_base: Number(row.optimal_time ?? 0),
        }));
        originalOrderSnapshot.value = operationList.value.map((row) => ({ ...row }));
        currentRow.value = createEmptyRow();
        return;
    }
    resetForm();
}

function saveOperations() {
    if (editingRowIndex.value !== null) {
        saveEditRow(editingRowIndex.value);
        if (editingRowIndex.value !== null) return;
    }
    const keys = operationList.value.map((r) => `${r.operation_id}`);
    const hasDuplicates = keys.some((k, i) => keys.indexOf(k) !== i);
    if (hasDuplicates) {
        const msg = 'Hay operaciones duplicadas en el listado (misma operación). Elimina duplicados antes de guardar.';
        setValidationError(msg);
        return;
    }

    const missingPreparationGroup = operationList.value.find(
        (row) => isSectionPreparacion(row.section_id) && !row.grupo_preparacion
    );
    if (missingPreparationGroup) {
        const msg = 'Todas las operaciones de PREPARACIÓN deben tener un grupo asignado. Edita las filas sin grupo.';
        setValidationError(msg);
        return;
    }

    if (operationList.value.length === 0) {
        setValidationError('El listado operacional no puede estar vacío. Agrega al menos una operación antes de guardar.');
        return;
    }

    const toNumOrNull = (v: number | string | null | undefined): number | null => {
        if (v == null) return null;
        if (typeof v === 'number' && Number.isFinite(v)) return v;
        const n = parseInt(String(v), 10);
        return Number.isFinite(n) ? n : null;
    };
    const sheetIdNumeric = toNumOrNull(productSheetId.value);
    const payload: ProductOperationPayload[] = operationList.value.map((row, i) => {
        const factorOp = Number(row.factor_operario ?? 100);
        return {
            id: row.id ?? null,
            product_sheet_id: sheetIdNumeric,
            operation_id: row.operation_id,
            sequence: (i + 1) * 10,
            optimal_time: row.optimal_time,
            operator_efficiency_factor: factorOp > 0 ? 100 / factorOp : 1,
            machine_supplement_factor: getSupplementForRow(row),
            grupo_preparacion: row.grupo_preparacion || undefined,
            notes: row.row_notes || undefined,
        };
    });

    clearValidationError();
    emit('save', payload);
    resetForm();
    localOpen.value = false;
}

function resetForm() {
    cancelEditRow();
    operationList.value = [];
    const defaultId = props.defaultProductSheetId;
    const isDefaultAvailable = defaultId != null && availableProductSheets.value.some((s) => idsMatch(s.id, defaultId));
    productSheetId.value = isDefaultAvailable ? defaultId : (availableProductSheets.value[0]?.id ?? null);
    currentRow.value = createEmptyRow();
    pendingAutoAddOperation.value = false;
}
</script>

<template>
    <AlertStack :error-message="validationError" error-type="warning" @close-error="clearValidationError" />
    <Dialog v-model:open="localOpen">
        <FormDialogContent class="flex max-h-[95vh] w-full max-w-[1300px] flex-col px-6 py-5">
            <DialogHeader class="flex-shrink-0 space-y-1">
                <DialogTitle class="text-lg font-semibold tracking-tight text-foreground">
                    {{ readonly ? 'Ver listado operacional' : isEditMode ? 'Editar listado operacional' : 'Listado operacional' }}
                </DialogTitle>
                <p class="text-sm text-muted-foreground/90">
                    {{
                        readonly
                            ? 'Este listado tiene órdenes de producción asociadas y no se puede modificar.'
                            : 'Busca una operación y añade factores, o crea una nueva con todos los datos.'
                    }}
                </p>
            </DialogHeader>

            <TooltipProvider>
                <div
                    class="min-h-0 flex-1 overflow-x-hidden overflow-y-auto overscroll-contain scroll-smooth pr-1"
                    style="scrollbar-width: thin"
                >
                    <!-- Datos generales -->
                    <div class="flex flex-wrap items-end gap-x-6 gap-y-3 px-1 pb-4" style="border-bottom: 1px solid hsl(var(--border) / 0.4)">
                        <div class="min-w-[220px] flex-1">
                            <SelectField
                                v-model="productSheetId"
                                label="Sábana de producto"
                                :options="availableProductSheets.map((s) => ({ id: s.id, name: buildProductSheetLabel(s) }))"
                                :disabled="isEditMode || readonly"
                                required
                            />
                        </div>
                        <div class="flex shrink-0 items-center gap-3 pb-1">
                            <div v-if="selectedGarmentType" class="flex items-center gap-1.5">
                                <span class="text-[10px] font-medium uppercase tracking-widest text-muted-foreground">Tipo de prenda</span>
                                <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold', selectedGarmentType === 'superior' ? 'bg-blue-100 text-blue-800' : 'bg-amber-100 text-amber-800']">{{ selectedGarmentType }}</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="text-[10px] font-medium uppercase tracking-widest text-muted-foreground">Base tela</span>
                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-800">
                                    {{ selectedSheetFabricBaseName }} <span class="ml-1 text-[10px] font-normal">(×{{ selectedSheetFabricFactor.toFixed(2) }})</span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Añadir operación -->
                    <div v-if="!readonly" class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <div>
                            <span class="text-sm font-medium text-foreground">Añadir operación</span>
                            <p class="mt-0.5 text-xs text-muted-foreground/80">Selecciona una operación; máquina y factor operario se auto-rellenan.</p>
                        </div>
                        <Button
                            variant="default"
                            size="sm"
                            class="flex h-8 items-center gap-2 rounded-full px-5 py-2 text-sm font-semibold shadow-sm"
                            @click="openAddNewDialog('operation')"
                        >
                            <PlusCircle class="h-2 w-4" />
                            Crear operación
                        </Button>
                    </div>

                    <!-- Fila para añadir (encabezados sutiles) -->
                    <div v-if="!readonly" class="grid grid-cols-12 gap-2 px-1 py-1 text-[11px] font-medium text-muted-foreground/80">
                        <div class="col-span-3 flex items-center gap-1">
                            Operación
                            <HelpTooltip text="Nombre de la operación según catálogo." />
                        </div>
                        <div class="col-span-2 flex items-center gap-1">
                            Máquina
                            <HelpTooltip text="Se auto-rellena desde la operación. El factor muestra el suplemento." />
                        </div>
                        <div class="col-span-1 flex items-center justify-end gap-1">
                            SAM
                            <HelpTooltip text="SAM base: tiempo estándar en minutos por unidad (del catálogo)." />
                        </div>
                        <div class="col-span-1 flex items-center justify-end gap-1">
                            F. op.
                            <HelpTooltip text="Factor operario (%) de la operación. SAM ajust. = SAM base / (F.op. / 100)." />
                        </div>
                        <div class="col-span-1 flex items-center justify-end gap-1">
                            SAM total
                            <HelpTooltip text="SAM total unitario: (SAM base / F.op.) × suplemento máq. × factor base tela." />
                        </div>
                        <div class="col-span-3 flex items-center gap-1">
                            Grupo prep.<span v-if="needsManualPreparationGroup" class="text-red-500">*</span>
                            <HelpTooltip
                                text="Solo para secciones de tipo PREPARACIÓN. Se sugiere desde la parte de prenda de la operación; si no hay coincidencia, elige o crea uno."
                            />
                        </div>
                        <div class="col-span-1"></div>
                    </div>
                    <div v-if="!readonly" class="grid grid-cols-12 items-end gap-2 px-1 py-2">
                        <div class="col-span-3">
                            <SelectField
                                v-model="currentRow.operation_id"
                                :options="availableOperationOptions.map((o) => ({ id: o.id, name: getOperationOptionLabel(o) }))"
                                placeholder="Buscar operación"
                                class="h-9 w-full"
                                @keydown.enter.prevent="handleEnterAddOperation"
                            >
                            </SelectField>
                        </div>
                        <div class="col-span-2">
                            <div
                                class="flex h-9 items-center truncate rounded-md bg-muted/30 px-2 text-xs text-muted-foreground"
                                            :title="currentRow.operation_id ? getMachineDisplayWithSupplement(currentRow) : 'Se auto-rellena al elegir operación'"
                            >
                                            {{ currentRow.operation_id ? getMachineDisplayWithSupplement(currentRow) : '—' }}
                            </div>
                        </div>
                        <div class="col-span-1">
                            <div
                                class="flex h-9 items-center justify-end rounded-md bg-muted/30 px-2 font-mono text-xs text-muted-foreground"
                                title="SAM base de la operación"
                            >
                                {{ (Number(currentRow.optimal_time) || 0).toFixed(2) }}
                            </div>
                        </div>
                        <div class="col-span-1">
                            <div
                                class="flex h-9 items-center justify-end rounded-md bg-muted/30 px-2 font-mono text-xs text-muted-foreground"
                                :title="`Factor operario: ${currentRow.factor_operario}%`"
                            >
                                {{ currentRow.factor_operario }}%
                            </div>
                        </div>
                        <div class="col-span-1">
                            <div class="flex h-9 items-center justify-end rounded-md bg-primary/5 px-2 font-mono text-xs font-semibold text-primary">
                                {{ getSamUnitario(currentRow).toFixed(2) }}
                            </div>
                        </div>
                        <div class="col-span-3">
                            <SelectField
                                v-if="isSectionPreparacion(currentRow.section_id)"
                                v-model="currentRow.grupo_preparacion"
                                :options="preparationGroupsList.map((g: string) => ({ id: g, name: g }))"
                                placeholder="Seleccionar grupo"
                                class="h-9 w-full"
                                :searchable="true"
                                :search-threshold="0"
                                search-placeholder="Buscar o escribir grupo"
                                action-label="Agregar grupo"
                                :action-disabled="readonly"
                                @search="(term) => (preparationGroupSearchTerm = String(term ?? ''))"
                                @action="(term) => addPreparationGroupFromSelect(String(term ?? ''))"
                            />
                            <div v-else class="flex h-9 items-center justify-center text-[10px] text-muted-foreground/50">—</div>
                        </div>
                        <div class="col-span-1">
                            <Button
                                size="sm"
                                class="w-full gap-1"
                                :disabled="!currentRow.operation_id"
                                @click="addOperation"
                            >
                                <Plus class="h-4 w-4" />
                                Añadir
                            </Button>
                        </div>
                        <div class="col-span-12">
                            <input data-no-uppercase
                                v-model="currentRow.row_notes"
                                type="text"
                                placeholder="Nota de la operación (opcional)"
                                class="h-7 w-full rounded-md border border-input bg-transparent px-2 text-xs text-muted-foreground placeholder:text-muted-foreground/50 focus:outline-none focus:ring-1 focus:ring-ring"
                                @keydown.enter.prevent="handleEnterAddOperation"
                            />
                        </div>
                        <div
                            v-if="needsManualPreparationGroup"
                            class="col-span-12 flex items-start gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-700"
                        >
                            <Lightbulb class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                            <span>
                                Esta operación es de sección PREPARACIÓN y no se pudo sugerir un grupo automáticamente desde su parte de
                                prenda — elige uno existente o escribe uno nuevo (ej. Cuello, Manga, Bolsillo) para poder añadirla.
                            </span>
                        </div>
                    </div>

                    <!-- Lista de operaciones añadidas -->
                    <div v-if="operationList.length > 0" class="mt-5 overflow-hidden">
                        <div v-if="!readonly" class="mb-2 flex items-center justify-between gap-2 rounded-md border border-border/50 bg-muted/20 px-3 py-2">
                            <p class="flex items-center gap-2 text-[11px] text-muted-foreground/85">
                                <GripVertical class="h-3 w-3 shrink-0" />
                                Arrastra para reordenar · Edita notas y grupo de preparación
                            </p>
                            <div class="flex items-center gap-2">
                                <button
                                    v-if="isOrderChanged"
                                    type="button"
                                    class="flex items-center gap-1 rounded-full border border-border bg-background px-2.5 py-0.5 text-[10px] font-medium text-muted-foreground transition-colors hover:border-primary/40 hover:text-primary"
                                    @click="resetOrder"
                                >
                                    <CornerDownLeft class="h-3 w-3" /> Restablecer orden
                                </button>
                                <span class="rounded-full bg-background px-2 py-0.5 text-[10px] font-semibold text-muted-foreground">
                                    {{ operationList.length }} filas
                                </span>
                            </div>
                        </div>
                        <div
                            class="rounded-lg border border-border/60 bg-muted/10"
                        >
                            <table class="w-full table-fixed text-sm">
                                <thead class="sticky top-0 z-10 bg-background/95 shadow-sm backdrop-blur supports-[backdrop-filter]:bg-background/85">
                                    <tr class="text-[11px] text-muted-foreground/90" style="border-bottom: 1px solid hsl(var(--border) / 0.5)">
                                        <th class="w-7 py-2.5"></th>
                                        <th class="w-7 px-3 py-2.5 text-left font-medium">#</th>
                                        <th class="w-[300px] px-3 py-2.5 text-left font-medium">
                                            <span class="inline-flex items-center gap-1">Operación <HelpTooltip text="Nombre de la operación." /></span>
                                        </th>
                                        <th class="w-52 px-3 py-2.5 text-left font-medium">
                                            <span class="inline-flex items-center gap-1">Máquina <HelpTooltip text="Máquina de la operación (auto)." /></span>
                                        </th>
                                        <th class="w-16 px-3 py-2.5 text-right font-medium">
                                            <span class="inline-flex items-center justify-end gap-1">SAM <HelpTooltip text="SAM base en minutos." /></span>
                                        </th>
                                        <th class="w-14 px-3 py-2.5 text-right font-medium">
                                            <span class="inline-flex items-center justify-end gap-1">F. op. <HelpTooltip text="Factor operario (%)." /></span>
                                        </th>
                                        <th class="w-20 px-3 py-2.5 text-right font-medium">
                                            <span class="inline-flex items-center justify-end gap-1">SAM total <HelpTooltip text="SAM total unitario: (SAM / F.op.) × supl. máq. × factor tela." /></span>
                                        </th>
                                        <th class="w-14 px-3 py-2.5 text-right font-medium">
                                            <span class="inline-flex items-center justify-end gap-1">Op./h <HelpTooltip text="Operaciones por hora (60 ÷ SAM total)." /></span>
                                        </th>
                                        <th class="w-32 px-3 py-2.5 text-left font-medium">
                                            <span class="inline-flex items-center gap-1">Grupo <HelpTooltip text="Grupo de preparación (solo secciones PREPARACIÓN)." /></span>
                                        </th>
                                        <th v-if="!readonly" class="w-16"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <DraggableTableRow
                                        v-for="(row, index) in operationList"
                                        :key="row.row_key"
                                        :index="index"
                                        :is-draggable="!readonly && editingRowIndex === null"
                                        @drag-start="handleDragStart"
                                        @drag-end="handleDragEnd"
                                        @drop="handleDrop"
                                    >
                                        <td class="px-3 py-2.5 text-xs text-muted-foreground/80">{{ index + 1 }}</td>
                                        <td class="px-3 py-2.5 text-xs">
                                            <span class="block truncate" :title="getOperationName(row.operation_id)">{{
                                                getOperationName(row.operation_id)
                                            }}</span>
                                            <span
                                                v-if="!isEditingRow(index) && row.row_notes"
                                                class="mt-0.5 block truncate text-[10px] italic text-muted-foreground/60"
                                                :title="row.row_notes"
                                            >{{ row.row_notes }}</span>
                                            <input data-no-uppercase
                                                v-if="isEditingRow(index) && editRow"
                                                v-model="editRow.row_notes"
                                                type="text"
                                                placeholder="Nota (opcional)"
                                                class="mt-1 h-6 w-full rounded border border-input bg-transparent px-1.5 text-[10px] text-muted-foreground placeholder:text-muted-foreground/50 focus:outline-none focus:ring-1 focus:ring-ring"
                                                @blur="saveEditRow(index)"
                                                @keydown.enter.prevent="saveEditRow(index)"
                                            />
                                        </td>
                                        <td class="px-3 py-2.5 text-xs">
                                            <span
                                                class="block truncate text-muted-foreground/90"
                                                :title="`Suplemento: ×${getSupplementForRow(row).toFixed(2)}`"
                                            >{{ getMachineDisplayWithSupplement(row) }}</span>
                                        </td>
                                        <td class="px-3 py-2.5 text-right font-mono text-xs text-muted-foreground/80">
                                            {{ (Number(row.optimal_time) || 0).toFixed(2) }}
                                        </td>
                                        <td class="px-3 py-2.5 text-right font-mono text-xs text-muted-foreground/80">
                                            {{ row.factor_operario }}%
                                        </td>
                                        <td class="px-3 py-2.5 text-right font-mono text-xs font-semibold text-primary">
                                            {{ getSamUnitario(row).toFixed(2) }}
                                        </td>
                                        <td class="px-3 py-2.5 text-right font-mono text-xs text-muted-foreground/80">
                                            {{ getOpsPerHour(row) }}
                                        </td>
                                        <td class="px-2 py-2 text-xs">
                                            <SelectField
                                                v-if="isEditingRow(index) && editRow && isSectionPreparacion(row.section_id)"
                                                v-model="editRow.grupo_preparacion"
                                                :options="preparationGroupsList.map((g: string) => ({ id: g, name: g }))"
                                                placeholder="Grupo"
                                                class="h-7 min-w-[110px]"
                                                :searchable="true"
                                                :search-threshold="0"
                                                search-placeholder="Buscar o escribir grupo"
                                                action-label="Agregar grupo"
                                                :action-disabled="readonly"
                                                @search="(term) => (preparationGroupSearchTerm = String(term ?? ''))"
                                                @action="(term) => addPreparationGroupFromEditRow(String(term ?? ''))"
                                                @update:model-value="saveEditRow(index)"
                                            />
                                            <span
                                                v-else-if="isSectionPreparacion(row.section_id) && row.grupo_preparacion"
                                                class="block truncate text-muted-foreground/90"
                                                :title="row.grupo_preparacion"
                                            >{{ row.grupo_preparacion }}</span>
                                            <span v-else class="text-muted-foreground/40">—</span>
                                        </td>
                                        <td v-if="!readonly" class="px-3 py-2">
                                            <div class="flex items-center justify-end gap-1">
                                                <Button
                                                    v-if="!isEditingRow(index)"
                                                    variant="ghost"
                                                    size="icon"
                                                    class="h-7 w-7"
                                                    title="Editar notas / grupo"
                                                    @click="startEditRow(index)"
                                                >
                                                    <Pencil class="h-3.5 w-3.5" />
                                                </Button>
                                                <Button
                                                    v-if="isEditingRow(index)"
                                                    variant="ghost"
                                                    size="icon"
                                                    class="h-7 w-7"
                                                    title="Cancelar"
                                                    @click="cancelEditRow"
                                                >
                                                    <X class="h-3.5 w-3.5" />
                                                </Button>
                                                <DeleteButton
                                                    v-if="!isEditingRow(index)"
                                                    class="h-7 w-7"
                                                    title="Eliminar"
                                                    @click="confirmRemoveOperation(index)"
                                                />
                                            </div>
                                        </td>
                                    </DraggableTableRow>
                                </tbody>
                            </table>
                        </div>
                        <!-- Totales -->
                        <div
                            class="mt-1 flex items-center justify-between rounded-b-md bg-muted/15 px-4 py-3"
                            style="border-top: 1px solid hsl(var(--border) / 0.4)"
                        >
                            <span class="text-xs font-medium text-muted-foreground">{{ operationList.length }} operación(es)</span>
                            <span class="font-mono text-sm font-semibold text-primary">
                                SAM total: {{ operationList.reduce((s, r) => s + getSamOperacion(r), 0).toFixed(2) }} min
                            </span>
                        </div>
                    </div>

                    <div v-else class="mt-5 py-10 text-center text-sm text-muted-foreground/80">
                        <p>Selecciona una operación y haz clic en <strong>Añadir</strong>.</p>
                        <p class="mt-1 text-xs">Máquina y factor operario se auto-rellenan. Si no existe la operación, créala.</p>
                    </div>

                    <AddNew
                        :is-open="addNewDialogOpen"
                        :entity="addNewEntity"
                        :sections="sectionsList"
                        :operations="operationsList"
                        :machines="machinesList"
                        :product-sheets="productSheets"
                        :actions="actions"
                        :garment-parts="garmentParts"
                        :default-section-id="currentRow.section_id"
                        :default-product-sheet-id="productSheetId"
                        @close="addNewDialogOpen = false"
                        @created="handleEntityCreated"
                    />
                </div>
            </TooltipProvider>

            <FormDialogFooter class="flex items-center justify-end gap-2">
                <DialogActionButtons
                    v-if="!readonly"
                    cancel-label="Cancelar"
                    :submit-label="operationList.length > 0 ? `Guardar (${operationList.length})` : 'Guardar (vaciar lista)'"
                    @cancel="localOpen = false"
                    @submit="saveOperations"
                />
                <Button v-else variant="outline" @click="localOpen = false">
                    Cerrar
                </Button>
            </FormDialogFooter>
        </FormDialogContent>
    </Dialog>

    <ConfirmDialog
        v-model:open="deleteConfirmOpen"
        title="Eliminar operación"
        description="¿Estás seguro de que deseas eliminar esta operación del listado?"
        @confirm="handleConfirmDelete"
    />
</template>
