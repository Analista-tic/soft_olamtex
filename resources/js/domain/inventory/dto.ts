export interface WarehouseDto {
    id: number;
    name: string;
    observation: string;
    created_at?: string | null;
    updated_at?: string | null;
}
// Inventory only exposes WarehouseDto
