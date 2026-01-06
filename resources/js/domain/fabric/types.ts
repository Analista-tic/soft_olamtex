import type { PurchaseInvoiceItem } from '../purchases';

export interface FabricOrderItem {
    id?: number;
    fabric: string;
    base_fabric_id?: string | number;
    base_fabric?: { id: number; name: string } | null;
    provider_color: string;
    provider_color_code: string;
    quantity: number;
    estimated_width: number;
    price: number;
}

export interface FabricOrder {
    id: number | null;
    fabric_provider_id: string;
    fabric_provider?: { id: number; name: string } | null;
    status: string;
    items?: FabricOrderItem[];
}

export interface FabricRoll {
    id: number;
    roll_number: string;
    purchase_document_item_id: number;
    purchase_document_item?: PurchaseInvoiceItem;
    quantity: number;
    roll_width?: number;
    location?: string;
    description?: string;
    tolerance_status?: string;
    partial_output: number;
    actual_output: number;
    entry_date: string;
    available_quantity: number; // Computed attribute
    is_depleted: boolean; // Computed attribute
    created_at?: string;
    updated_at?: string;
}
export interface FabricRollView {
    id: number;
    roll: string;
    location: string | null;
    fabric: string;
    color: string;
    color_code: string;
    width: string;
    roll_qty: string;
    partial_output: string;
    partial_availability: string;
    exit_quantity: string;
    quantity_value?: number | string;
    exit_quantity_value?: number | string;
    real_availability: string;
    order_number: string;
    provider: string;
    created_at: string | null;
    creator: string | null;
    status: 'available' | 'depleted';
}

export interface FabricRollReceptionForm {
    location: string;
    fabric_color_id: number | null;
    fabric_order_item_id: number | undefined;
    received_quantity: number;
    width: number;
    quantity: number;
}
