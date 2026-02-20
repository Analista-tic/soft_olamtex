
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
