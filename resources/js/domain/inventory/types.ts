import type { Item } from '@/domain/MasterData';

// Only Warehouse remains in the inventory domain
export interface Warehouse {
    id?: number;
    name: string;
    observation?: string | null;
    created_at?: string | null;
    updated_at?: string | null;
}

// Tipos compartidos para bodegas
export type WarehouseOption = { id: number; name: string };

export interface InventoryMovement {
    id: number;
    document_number?: string | null;
    date?: string | null;
    type: 'entrada' | 'salida' | 'ajuste' | string;
    quantity: number;
    unit_price?: number | null;
    total_cost?: number | null;
    previous_stock: number;
    new_stock: number;
    previous_weighted_price: number;
    new_weighted_price: number;
    source_type?: string | null;
    source_id?: number | null;
    notes?: string | null;
    item_id?: number | null;
    warehouse_id?: number | null;
    item?: Pick<Item, 'id' | 'name'> | null;
    warehouse?: Pick<Warehouse, 'id' | 'name'> | null;
    created_by?: { id?: number; name?: string } | null;
}
