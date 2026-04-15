/**
 * Domain types for Product Sheet (Sábana de Productos)
 * Related to operational lists, fabric bases, operations, sections, and machines
 */

export type OperationSection = {
    id: number | string;
    name: string;
    type?: string | null;
    description?: string | null;
};

export type OperationOption = {
    id: number | string;
    name: string;
    code?: string | null;
    section_id?: number | string | null;
    description?: string | null;
};

export type MachineOption = {
    id: number | string;
    name: string;
    code?: string | null;
    section_id?: number | string | null;
};

export type FabricBaseOption = {
    id: number | string;
    name: string;
    nombre?: string;
    codigo?: string;
    factor?: number;
};

export type ProductOperationPayload = {
    id?: number;
    section_id: number | null;
    operation_id: number | null;
    machine_id: number | null;
    optimal_time: string;
    quantity?: number;
    fabric_base_id?: number | string | null;
    operator_efficiency_factor?: number;
    machine_supplement_factor?: number;
    sequence?: number;
    notes?: string | null;
};

export type ProductSheet = {
    id: number;
    type: 'muestra' | 'producto';
    sample_reference?: string | null;
    product_reference?: string | null;
    product_reference_2?: string | null;
    export_reference?: string | null;
    version?: number | null;
    billing_price?: number | null;
    costo?: number | null;
    selling_price?: number | null;
    latest_total_cost?: number | null;
    status?: 'complete' | 'incomplete' | string;
    is_enabled?: boolean;
    section_id?: number | null;
    group_id?: number | null;
    brand_id?: number | null;
    world_id?: number | null;
    fabric_base_id?: number | null;
    yarn_base_id?: number | null;
    operational_list_id?: number | null;
    section?: { id: number; name: string } | null;
    group?: { id: number; name: string } | null;
    brand?: { id: number; name: string } | null;
    world?: { id: number; name: string } | null;
    fabric_base?: { id: number; name: string } | null;
    yarn_base?: { id: number; name: string } | null;
    sizes?: string | Record<string, boolean> | null;
    woven_product?: boolean | number;
    description?: string | null;
    flat_sketch_path?: string | null;
    processes?: string | Record<string, boolean> | null;
    parent_id?: number | null;
    has_production_orders?: boolean;
    has_derived_product?: boolean;
};

export type Programmer = ProductSheet;

export type CostParameters = {
    operator_efficiency_factor: number;
    machine_supplement_factor: number;
};

export type ProductOperationListPageProps = {
    productSheet: ProductSheet;
    operations: ProductOperationPayload[];
    sections: OperationSection[];
    operationOptions: OperationOption[];
    machines: MachineOption[];
    fabricBases: FabricBaseOption[];
};

export function normalizeSizes(sizes: unknown): Record<string, boolean> {
    if (sizes == null) return {};
    if (typeof sizes === 'string') {
        try {
            const parsed = JSON.parse(sizes);
            if (parsed && typeof parsed === 'object') {
                return parsed;
            }
        } catch {
            return {};
        }
    }
    if (typeof sizes === 'object' && !Array.isArray(sizes)) {
        return sizes as Record<string, boolean>;
    }
    if (Array.isArray(sizes)) {
        return Object.fromEntries(sizes.map((v) => [String(v), true]));
    }
    return {};
}
