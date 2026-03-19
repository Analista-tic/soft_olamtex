import type { ContactDto, ItemDto } from '@/domain/MasterData';

import type { SaleDocumentType } from './types';

export interface SaleDocumentUserDto {
    id?: number;
    name?: string;
}

export interface SaleDocumentSummaryDto {
    id?: number;
    document_number?: string | null;
    document_type?: SaleDocumentType | string;
    status?: string;
    date?: string;
    contact_id?: number | null;
    contact?: ContactDto | null;
    client?: ContactDto | null;
}

export interface SaleDocumentItemDto {
    id?: number;
    sale_document_id?: number;
    item_id: number | null;
    item?: ItemDto;
    item_name?: string;
    quantity: number | string;
    price: number | string;
    unit_price?: number | string;
    discount?: number;
    tax: number;
    tax_rate?: number;
    total: number;
    subtotal?: number;
    total_tax?: number;
    supplier_code?: string;
    supplier_color?: string;
    order_width?: number | string | null;
    description?: string;
}

export interface SaleDocumentDto {
    id?: number;
    document_number?: string | null;
    document_type: SaleDocumentType | string;
    parent_document_id?: number | null;
    quote_id?: number | null;
    contact_id?: number | null;
    date?: string;
    due_date?: string | null;
    delivery_date?: string | null;
    description?: string | null;
    payment_terms?: string | null;
    payment_days?: number | null;
    payment_status?: string;
    amount_paid?: number;
    customer_order_number?: string | null;
    status?: string;
    withholdings?: Array<Record<string, unknown>> | Record<string, unknown> | null;
    invoiced_at?: string | null;
    items: SaleDocumentItemDto[];
    parent?: SaleDocumentSummaryDto | null;
    children?: SaleDocumentSummaryDto[];
    client?: ContactDto | null;
    contact?: ContactDto | null;
    creator?: SaleDocumentUserDto | null;
    executor?: SaleDocumentUserDto | null;
    created_by_user_id?: number | null;
    executed_by_user_id?: number | null;
    created_at?: string;
    updated_at?: string;
}

export interface CreateSaleDocumentDto {
    document_type: SaleDocumentType | string;
    parent_document_id?: number | null;
    quote_id?: number | null;
    contact_id?: number | null;
    date?: string;
    due_date?: string | null;
    delivery_date?: string | null;
    description?: string | null;
    payment_terms?: string | null;
    payment_days?: number | null;
    payment_status?: string;
    amount_paid?: number;
    customer_order_number?: string | null;
    status?: string;
    withholdings?: Array<Record<string, unknown>> | Record<string, unknown> | null;
    executed_by_user_id?: number | null;
    items: SaleDocumentItemDto[];
}

export interface UpdateSaleDocumentDto extends Partial<CreateSaleDocumentDto> {
    items?: SaleDocumentItemDto[];
}

export interface QuoteItemDto extends SaleDocumentItemDto {
    unit_price: number | string;
    discount?: number;
    tax_rate?: number;
    subtotal?: number;
}

export interface CreateQuoteDto {
    document_number?: string | null;
    contact_id: number | null;
    date: string;
    due_date?: string | null;
    description?: string | null;
    payment_terms?: 'cash' | 'credit' | null;
    payment_days?: number | null;
    customer_order_number?: string | null;
    status?: 'draft' | 'sent' | 'accepted' | 'rejected' | 'expired' | 'converted';
    items: QuoteItemDto[];
}

export interface UpdateQuoteDto extends Partial<CreateQuoteDto> {
    items?: QuoteItemDto[];
}

export interface SalesOrderItemDto extends SaleDocumentItemDto {
    unit_price: number | string;
    discount?: number;
    tax_rate?: number;
    subtotal?: number;
}

export interface CreateSalesOrderDto {
    document_number?: string | null;
    parent_document_id?: number | null;
    contact_id: number | null;
    date: string;
    due_date?: string | null;
    description?: string | null;
    payment_terms?: 'cash' | 'credit' | null;
    payment_days?: number | null;
    customer_order_number?: string | null;
    status?: 'draft' | 'confirmed' | 'in_process' | 'invoiced' | 'cancelled';
    items: SalesOrderItemDto[];
}

export interface UpdateSalesOrderDto extends Partial<CreateSalesOrderDto> {
    items?: SalesOrderItemDto[];
}

export interface SalesInvoiceWithholdingDto {
    retention_id?: number | null;
    name: string;
    percentage: number;
    base_amount?: number | null;
}

export interface SalesInvoiceItemDto extends SaleDocumentItemDto {
    unit_price: number | string;
    discount?: number;
    tax_rate?: number;
    subtotal?: number;
}

export interface CreateSalesInvoiceDto {
    document_number?: string | null;
    parent_document_id?: number | null;
    contact_id: number | null;
    date: string;
    due_date?: string | null;
    description?: string | null;
    payment_terms?: 'cash' | 'credit' | null;
    payment_days?: number | null;
    customer_order_number?: string | null;
    status?: 'draft';
    withholdings?: SalesInvoiceWithholdingDto[];
    items: SalesInvoiceItemDto[];
}

export interface UpdateSalesInvoiceDto extends Partial<CreateSalesInvoiceDto> {
    items?: SalesInvoiceItemDto[];
}

export interface SaleNoteItemDto extends SaleDocumentItemDto {
    unit_price: number | string;
    discount?: number;
    tax_rate?: number;
    subtotal?: number;
}

export interface CreateSaleCreditDebitNoteDto {
    type: 'credit' | 'debit';
    document_number?: string | null;
    parent_document_id: number;
    contact_id?: number | null;
    date: string;
    due_date?: string | null;
    description?: string | null;
    status?: 'draft';
    items: SaleNoteItemDto[];
}

export interface UpdateSaleCreditDebitNoteDto extends Partial<CreateSaleCreditDebitNoteDto> {
    items?: SaleNoteItemDto[];
}

export interface SalePaymentDto {
    id?: number;
    cash_receipt_id?: number | null;
    cash_receipt_number?: number | null;
    sale_invoice_id: number;
    payment_date?: string | null;
    amount: number;
    reference?: string | null;
    notes?: string | null;
    created_by_user_id?: number | null;
    invoice?: SaleDocumentDto | SaleDocumentSummaryDto | null;
}

export interface CashReceiptAccountDto {
    id: number;
    code?: string;
    name?: string;
}

export interface CashReceiptDto {
    id: number;
    receipt_number: number;
    contact_id: number;
    contact?: ContactDto | null;
    client?: ContactDto | null;
    payment_date: string;
    account_id: number | null;
    account?: CashReceiptAccountDto | null;
    payment_method?: string | null;
    reference?: string | null;
    notes?: string | null;
    total?: number;
    payments?: SalePaymentDto[];
    salePayments?: SalePaymentDto[];
    created_by_user_id?: number | null;
    status?: string;
    journal_entry?: Record<string, unknown> | null;
    created_at?: string;
    updated_at?: string;
}

export interface CashReceiptApplicationDto {
    sale_invoice_id: number;
    amount: number;
}

export interface CreateCashReceiptDto {
    contact_id: number | null;
    payment_date: string;
    account_id: number | null;
    payment_method?: string | null;
    reference?: string | null;
    notes?: string | null;
    applications: CashReceiptApplicationDto[];
}
