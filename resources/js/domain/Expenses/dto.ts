import type { ContactDto, Item } from '@/domain/MasterData';

export interface PurchaseOrderItemDto {
    id?: number;
    item_id: number | null;
    item?: Item;
    item_name?: string;
    price: number;
    tax: number;
    quantity: number;
    description?: string;
    total: number;
    total_tax?: number;
    supplier_code?: string;
    supplier_color?: string;
    order_width?: number;
}
export interface PurchaseOrderDto {
    id?: number;
    document_number?: string | null;
    contact_id: number | null;
    contact?: ContactDto;
    supplier?: ContactDto;
    date: string;
    delivery_date: string;
    supplier_order_number: string;
    description?: string;
    items: PurchaseOrderItemDto[];
    created_at?: string;
    updated_at?: string;
    status?: string;
}

export interface PurchaseInvoiceWithholdingDto {
    name: string;
    percentage: number;
    base_amount: number | null;
}

export interface PurchaseInvoiceItemDto {
    id?: number;
    purchase_invoice_id?: number;
    item_id: number | null;
    item?: Item;
    item_name?: string;
    price: number | string | '';
    tax: number;
    quantity: number | string | '';
    description?: string;
    total: number;
    total_tax?: number;
    supplier_code?: string;
    supplier_color?: string;
    order_width?: string;
    original_order_item?: PurchaseOrderItemDto;
}

export interface PurchaseInvoiceDto {
    id?: number;
    contact_id: number | null;
    contact?: ContactDto;
    supplier?: ContactDto;
    provider_name?: string | null;
    date: string;
    delivery_date: string;
    document_number?: string | null;
    parent_document_id?: number | null;
    parent?: { document_number?: string | null };
    supplier_invoice_number?: string;
    description?: string;
    status?: string;
    items: PurchaseInvoiceItemDto[];
    created_at?: string;
    updated_at?: string;
    purchase_order_id?: number | null;
    withholdings?: PurchaseInvoiceWithholdingDto[];
    reason: string | null;
    evidence_path: string | null;
}

export interface PurchaseRequestItemDto {
    item_id: number | string | null;
    price: number | string;
    quantity: number | string;
    description: string;
    total: number;
    name?: string;
    item?: number | null;
    item_name?: string;
    total_tax?: number;
}

export interface PurchaseRequestDto {
    id?: number;
    request_number?: string;
    date?: string;
    provider?: ContactDto;
    requester?: { name?: string };
    estimated_amount?: number | null;
    description?: string;
    status?: string;
    items?: PurchaseRequestItemDto[];
}

export interface PurchaseRequestFormDto {
    id?: number;
    request_number: string;
    contact_id: number | string | null;
    requester_user_id: number | null;
    date: string;
    estimated_amount: number | null;
    description: string;
    items: PurchaseRequestItemDto[];
    status?: string | null;
}
