import type { FabricRollViewDto } from './dto';
import type { PurchaseInvoiceItem } from '@/domain/Expenses';

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
    purchase_document_item_id: number | null;
    purchase_document_item?: PurchaseInvoiceItem;
    quantity: number;
    roll_width?: number | null;
    location?: string | null;
    description?: string | null;
    tolerance_status?: string | null;
    partial_output: number;
    actual_output: number;
    entry_date: string;
    available_quantity: number; // Computed attribute
    is_depleted: boolean; // Computed attribute
    created_at?: string | null;
    updated_at?: string | null;
}

export interface FabricRollView extends FabricRollViewDto {}

export interface FabricRollReceptionForm {
    location: string;
    fabric_color_id: number | null;
    fabric_order_item_id: number | undefined;
    received_quantity: number;
    width: number;
    quantity: number;
}

export interface FabricItemOption {
    id: number;
    name: string;
    reference: string | null;
}

export interface RollProgrammingRow {
    id: number;
    source: 'order' | 'manual';
    op_code: string | null;
    manual_order_code: string | null;
    roll_number: string | null;
    location: string | null;
    fabric_name: string | null;
    color: string | null;
    type: string | null;
    programmed_meters: number;
    available_meters: number | null;
    consumed_real_meters: number;
    difference_meters: number;
}

export interface ManualRollProgrammingLine {
    id: number;
    fabric_roll_id: number;
    roll_number: string | null;
    location: string | null;
    manual_order_code: string;
    programmed_meters: number;
    consumed_meters: number;
    remaining_meters: number;
    notes: string | null;
    has_item: boolean;
}

export interface ManualRollProgrammingHistoryEntry {
    id: number;
    created_at: string | null;
    user_name: string | null;
    quantity: number | null;
    reason: string | null;
}

export interface ManualOrderCodeSummary {
    manual_order_code: string;
    rolls_count: number;
    programmed_meters: number;
    consumed_meters: number;
    created_at: string | null;
}
