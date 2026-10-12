import type {
    FabricBaseOption,
    MachineOption,
    OperationOption,
    OperationSection,
    ProductOperationDialogInitialData,
    ProductOperationPayload,
    ProductSheetDialogOption,
} from '@/domain/product-sheet/types';
import axios from 'axios';
import { ref } from 'vue';

/** Mismos datos iniciales que usa la página de Listados operacionales (con el id de cada línea). */
export type ListadoDialogInitialData = ProductOperationDialogInitialData;

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

            const efficiencyToPercent = (factor: unknown) => {
                const value = Number(factor ?? 1) || 1;
                return value > 0 ? Number((100 / value).toFixed(2)) : 100;
            };
            opsInitialData.value = {
                productSheetId,
                operations: sorted.map((row) => ({
                    // Sin el id, guardar recrearía cada línea (y chocaría con la operación que ya existe).
                    id: row.id ?? null,
                    section_id: row.operation_section_id ?? row.section_id ?? null,
                    operation_id: row.operation_id ?? null,
                    optimal_time: Number(row.optimal_time ?? row.sam ?? row.sam_base ?? 0).toFixed(4),
                    factor_operario: efficiencyToPercent(row.operator_efficiency_factor),
                    grupo_preparacion: row.grupo_preparacion ?? null,
                    row_notes: row.notes ?? '',
                })),
                operatorEfficiencyFactor: efficiencyToPercent(sorted[0]?.operator_efficiency_factor),
                notes: sorted[0]?.notes ?? '',
            };
            editOpsOpen.value = true;
        } catch (e) {
            console.error('Error cargando listado operacional:', e);
            options?.onError?.('Error al cargar el listado operacional.');
        } finally {
            catalogLoading.value = false;
        }
    }

    async function handleSaveOps(payload: ProductOperationPayload[], productSheetId: number) {
        isSavingOps.value = true;
        try {
            const operations = payload
                .filter((p) => p.operation_id)
                .map((p) => ({
                    id: p.id ?? null,
                    product_sheet_id: productSheetId,
                    operation_id: p.operation_id,
                    sequence: p.sequence,
                    optimal_time: Number(p.optimal_time),
                    operator_efficiency_factor: p.operator_efficiency_factor,
                    machine_supplement_factor: p.machine_supplement_factor,
                    grupo_preparacion: p.grupo_preparacion ?? null,
                    notes: p.notes ?? null,
                }));

            // Nunca sincronizar vacío: borraría todo el listado.
            if (operations.length === 0) {
                options?.onError?.('El listado operacional no puede quedar vacío.');
                return;
            }

            // Mismo guardado que la página de Listados: actualiza por id, crea las nuevas y quita solo las retiradas.
            // (Antes borraba todo y solo recreaba filas con campos que el diálogo no envía, dejando el listado vacío.)
            await axios.post('/produccion/listados-operacionales/sync', {
                product_sheet_id: productSheetId,
                operations,
            });

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
