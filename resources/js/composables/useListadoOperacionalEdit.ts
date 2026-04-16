import type { FabricBaseOption, MachineOption, OperationOption, OperationSection, ProductOperationPayload } from '@/domain/product-sheet/types';
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
    const catalogPreparationGroups = ref<string[]>([]);
    const opsInitialData = ref<ListadoDialogInitialData | null>(null);

    async function openEditOpsDialog(productSheetId: number | null) {
        if (!productSheetId) return;
        catalogLoading.value = true;
        try {
            const [catalogRes, opsRes] = await Promise.all([
                axios.get('/produccion/listados-operacionales/options'),
                axios.get('/produccion/listados-operacionales/data', { params: { product_sheet_id: productSheetId } }),
            ]);

            catalogSections.value = catalogRes.data.sections ?? [];
            catalogOperations.value = catalogRes.data.operations ?? [];
            catalogMachines.value = catalogRes.data.machines ?? [];
            catalogFabricBases.value = catalogRes.data.fabric_bases ?? [];
            catalogPreparationGroups.value = catalogRes.data.preparation_groups ?? [];

            const rows: any[] = Array.isArray(opsRes.data?.data) ? opsRes.data.data : [];
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
        catalogPreparationGroups,
        opsInitialData,
        openEditOpsDialog,
        handleSaveOps,
    };
}
