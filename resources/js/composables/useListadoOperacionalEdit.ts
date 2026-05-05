import type {
    FabricBaseOption,
    MachineOption,
    OperationOption,
    OperationSection,
    ProductOperationPayload,
    ProductSheetDialogOption,
} from '@/domain/product-sheet/types';
import axios from 'axios';
import { ref } from 'vue';

export type ListadoDialogInitialData = {
    productSheetId: number | null;
    operations: {
        section_id: number | null;
        operation_id: number | null;
        machine_id: number | null;
        optimal_time: string;
        quantity: number;
        fabric_base_id: number | string | null;
        operator_efficiency_factor: number;
        grupo_preparacion?: string | null;
        row_notes?: string;
    }[];
    operatorEfficiencyFactor: number;
    notes: string;
};

export function useListadoOperacionalEdit(options?: { onSaved?: () => void; onError?: (msg: string) => void }) {
    const editOpsOpen = ref(false);
    const catalogLoading = ref(false);
    const isSavingOps = ref(false);
    const catalogSections = ref<OperationSection[]>([]);
    const catalogOperations = ref<OperationOption[]>([]);
    const catalogMachines = ref<MachineOption[]>([]);
    const catalogFabricBases = ref<FabricBaseOption[]>([]);
    const catalogProductSheets = ref<ProductSheetDialogOption[]>([]);
    const catalogPreparationGroups = ref<string[]>([]);
    const catalogActions = ref<string[]>([]);
    const catalogGarmentParts = ref<string[]>([]);
    const opsInitialData = ref<ListadoDialogInitialData | null>(null);

    async function fetchCatalogOptions() {
        try {
            return await axios.get('/produccion/listados-operacionales/options');
        } catch {
            return await axios.get('/control-de-lotes/opciones-operaciones');
        }
    }

    async function openEditOpsDialog(productSheetId: number | null) {
        if (!productSheetId) return;
        catalogLoading.value = true;
        try {
            const [catalogResult, opsResult] = await Promise.allSettled([
                fetchCatalogOptions(),
                axios.get('/produccion/listados-operacionales/data', { params: { product_sheet_id: productSheetId } }),
            ]);

            if (catalogResult.status !== 'fulfilled') {
                throw catalogResult.reason;
            }

            const catalogData = catalogResult.value.data ?? {};
            catalogSections.value = Array.isArray(catalogData.sections) ? catalogData.sections : [];
            catalogOperations.value = Array.isArray(catalogData.operations) ? catalogData.operations : [];
            catalogMachines.value = Array.isArray(catalogData.machines) ? catalogData.machines : [];
            catalogFabricBases.value = Array.isArray(catalogData.fabric_bases) ? catalogData.fabric_bases : [];
                        catalogProductSheets.value = Array.isArray(catalogData.productSheets)
                                ? catalogData.productSheets
                                : Array.isArray(catalogData.product_sheets)
                                    ? catalogData.product_sheets
                                    : [];
            catalogPreparationGroups.value = Array.isArray(catalogData.preparation_groups) ? catalogData.preparation_groups : [];
                        catalogActions.value = Array.isArray(catalogData.actions) ? catalogData.actions : [];
                        catalogGarmentParts.value = Array.isArray(catalogData.garmentParts)
                                ? catalogData.garmentParts
                                : Array.isArray(catalogData.garment_parts)
                                    ? catalogData.garment_parts
                                    : [];

            const rows: any[] =
                opsResult.status === 'fulfilled' && Array.isArray(opsResult.value.data?.data)
                    ? opsResult.value.data.data
                    : [];
            const sorted = [...rows].sort((a, b) => (a.sequence ?? 0) - (b.sequence ?? 0));

            opsInitialData.value = {
                productSheetId,
                operations: sorted.map((row) => ({
                    section_id: row.operation_section_id ?? null,
                    operation_id: row.operation_id ?? null,
                    machine_id: row.machine_id ?? null,
                    optimal_time: Number(row.sam_base ?? row.optimal_time ?? 0).toFixed(4),
                    quantity: row.quantity ?? 1,
                    fabric_base_id: row.fabric_base_id ?? null,
                    operator_efficiency_factor: Number(row.operator_efficiency_factor ?? 1) || 1,
                    grupo_preparacion: row.grupo_preparacion ?? null,
                    row_notes: row.notes ?? '',
                })),
                operatorEfficiencyFactor: 1,
                notes: '',
            };
            editOpsOpen.value = true;
        } catch (e) {
            console.error('Error cargando listado operacional:', e);
            options?.onError?.('Error al cargar el listado operacional.');
        } finally {
            catalogLoading.value = false;
        }
    }

    async function handleSaveOps(payload: ProductOperationPayload[], orderId: number | string, productSheetId: number) {
        isSavingOps.value = true;
        try {
            // 1. Obtener IDs existentes
            const existingRes = await axios.get('/produccion/listados-operacionales/data', {
                params: { product_sheet_id: productSheetId },
            });
            const existingIds: number[] = (existingRes.data?.data ?? []).map((r: any) => r.id).filter(Boolean);

            // 2. Eliminar existentes
            if (existingIds.length > 0) {
                await axios.post('/produccion/listados-operacionales/bulk-delete', {
                    product_sheet_id: productSheetId,
                    operation_ids: existingIds,
                });
            }

            // 3. Crear nuevas
            const valid = payload.filter((p) => p.product_sheet_id && p.section_id && p.operation_id && p.machine_id);
            if (valid.length > 0) {
                await axios.post('/produccion/listados-operacionales/bulk', {
                    operations: valid.map((p) => ({
                        product_sheet_id: productSheetId,
                        operation_id: p.operation_id,
                        machine_id: p.machine_id,
                        operation_section_id: p.section_id,
                        sequence: p.sequence,
                        optimal_time: Number(p.optimal_time),
                        operator_efficiency_factor: p.operator_efficiency_factor,
                        machine_supplement_factor: p.machine_supplement_factor,
                        grupo_preparacion: p.grupo_preparacion ?? null,
                        notes: p.notes ?? null,
                        quantity: p.quantity ?? 1,
                        fabric_base_id: p.fabric_base_id ?? null,
                    })),
                });
            }

            // 4. Regenerar snapshot de la OP
            await axios.post(`/produccion/ordenes-produccion/${orderId}/regenerar-snapshot`);

            editOpsOpen.value = false;
            options?.onSaved?.();
        } catch (e: any) {
            console.error('Error guardando listado operacional:', e);
            const msg = e?.response?.data?.message ?? 'Error al guardar el listado operacional.';
            options?.onError?.(msg);
        } finally {
            isSavingOps.value = false;
        }
    }

    return {
        editOpsOpen,
        catalogLoading,
        isSavingOps,
        catalogSections,
        catalogOperations,
        catalogMachines,
        catalogFabricBases,
        catalogProductSheets,
        catalogPreparationGroups,
        catalogActions,
        catalogGarmentParts,
        opsInitialData,
        openEditOpsDialog,
        handleSaveOps,
    };
}
