import { mapContactDto, type Item } from '@/domain/MasterData';

import type {
    CashReceiptApplicationDto,
    CashReceiptDto,
    CreateCashReceiptDto,
    CreateQuoteDto,
    CreateSaleDocumentDto,
    CreateSaleCreditDebitNoteDto,
    CreateSalesInvoiceDto,
    CreateSalesOrderDto,
    SalePaymentDto,
    QuoteItemDto,
    SaleDocumentDto,
    SaleDocumentItemDto,
    SaleNoteItemDto,
    SaleDocumentSummaryDto,
    SalesInvoiceItemDto,
    SalesOrderItemDto,
    UpdateSaleCreditDebitNoteDto,
    UpdateSaleDocumentDto,
} from './dto';
import type {
    CashReceipt,
    CashReceiptApplication,
    Quote,
    QuoteItem,
    SaleCreditDebitNote,
    SaleDocument,
    SaleDocumentItem,
    SalePayment,
    SaleNoteItem,
    SaleDocumentSummary,
    SaleDocumentUser,
    SalesInvoice,
    SalesInvoiceItem,
    SalesInvoiceWithholding,
    SalesOrder,
    SalesOrderItem,
} from './types';

function mapSaleDocumentUser(dto?: { id?: number; name?: string } | null): SaleDocumentUser | undefined {
    if (!dto) {
        return undefined;
    }

    return {
        id: dto.id,
        name: dto.name,
    };
}

function mapSaleDocumentSummary(dto?: SaleDocumentSummaryDto | null): SaleDocumentSummary | undefined {
    if (!dto) {
        return undefined;
    }

    return {
        ...dto,
        contact: dto.contact ? mapContactDto(dto.contact) : undefined,
        client: dto.client ? mapContactDto(dto.client) : undefined,
    };
}

export function mapSaleDocumentItem(dto: SaleDocumentItemDto): SaleDocumentItem {
    const rawItem = (dto as { item?: unknown }).item;

    return {
        ...dto,
        item: rawItem && typeof rawItem === 'object' ? (rawItem as Item) : undefined,
    };
}

export function mapQuoteItem(raw: QuoteItemDto | SaleDocumentItemDto): QuoteItem {
    const item = mapSaleDocumentItem(raw);

    return {
        ...item,
        unit_price: Number(raw.unit_price ?? raw.price ?? 0),
        discount: Number(raw.discount ?? 0),
        tax_rate: Number(raw.tax_rate ?? raw.tax ?? 0),
        subtotal: Number(raw.subtotal ?? raw.total ?? 0),
    };
}

export function mapSalesOrderItem(raw: SalesOrderItemDto | SaleDocumentItemDto): SalesOrderItem {
    const item = mapSaleDocumentItem(raw);

    return {
        ...item,
        unit_price: Number(raw.unit_price ?? raw.price ?? 0),
        discount: Number(raw.discount ?? 0),
        tax_rate: Number(raw.tax_rate ?? raw.tax ?? 0),
        subtotal: Number(raw.subtotal ?? raw.total ?? 0),
    };
}

export function mapSalesInvoiceItem(raw: SalesInvoiceItemDto | SaleDocumentItemDto): SalesInvoiceItem {
    const item = mapSaleDocumentItem(raw);

    return {
        ...item,
        unit_price: Number(raw.unit_price ?? raw.price ?? 0),
        discount: Number(raw.discount ?? 0),
        tax_rate: Number(raw.tax_rate ?? raw.tax ?? 0),
        subtotal: Number(raw.subtotal ?? raw.total ?? 0),
    };
}

export function mapSaleNoteItem(raw: SaleNoteItemDto | SaleDocumentItemDto): SaleNoteItem {
    const item = mapSaleDocumentItem(raw);

    return {
        ...item,
        unit_price: Number(raw.unit_price ?? raw.price ?? 0),
        discount: Number(raw.discount ?? 0),
        tax_rate: Number(raw.tax_rate ?? raw.tax ?? 0),
        subtotal: Number(raw.subtotal ?? raw.total ?? 0),
    };
}

function mapSalesInvoiceWithholding(raw: Record<string, unknown>): SalesInvoiceWithholding {
    return {
        retention_id: typeof raw.retention_id === 'number' ? raw.retention_id : null,
        name: String(raw.name ?? ''),
        percentage: Number(raw.percentage ?? 0),
        base_amount: raw.base_amount == null ? null : Number(raw.base_amount),
    };
}

export function mapSaleDocument(dto: SaleDocumentDto): SaleDocument {
    return {
        ...dto,
        id: Number(dto.id ?? 0),
        items: (dto.items ?? []).map(mapSaleDocumentItem),
        parent: mapSaleDocumentSummary(dto.parent),
        children: dto.children?.map(mapSaleDocumentSummary).filter(Boolean) as SaleDocumentSummary[] | undefined,
        client: dto.client ? mapContactDto(dto.client) : undefined,
        contact: dto.contact ? mapContactDto(dto.contact) : dto.client ? mapContactDto(dto.client) : undefined,
        creator: mapSaleDocumentUser(dto.creator),
        executor: mapSaleDocumentUser(dto.executor),
    };
}

export function mapQuote(raw: SaleDocumentDto): Quote {
    const base = mapSaleDocument(raw);

    return {
        ...base,
        due_date: raw.due_date ?? null,
        payment_terms: raw.payment_terms === 'cash' || raw.payment_terms === 'credit' ? raw.payment_terms : null,
        payment_days: raw.payment_days ?? null,
        customer_order_number: raw.customer_order_number ?? null,
        items: (raw.items ?? []).map((item) => mapQuoteItem(item)),
        client: raw.client ? mapContactDto(raw.client) : raw.contact ? mapContactDto(raw.contact) : (base.client as Quote['client']),
        status:
            raw.status && ['draft', 'sent', 'accepted', 'rejected', 'expired', 'converted'].includes(raw.status)
                ? (raw.status as Quote['status'])
                : undefined,
    };
}

export function mapSalesOrder(raw: SaleDocumentDto): SalesOrder {
    const base = mapSaleDocument(raw);
    const relatedQuote =
        raw.parent?.document_type === 'quote'
            ? ({
                  id: raw.parent.id,
                  document_number: raw.parent.document_number ?? null,
                  document_type: 'quote',
                  parent_document_id: null,
                  quote_id: null,
                  contact_id: raw.parent.contact_id ?? raw.contact_id ?? null,
                  date: raw.parent.date,
                  due_date: null,
                  delivery_date: null,
                  description: null,
                  payment_terms: null,
                  payment_days: null,
                  payment_status: 'unpaid',
                  amount_paid: 0,
                  customer_order_number: null,
                  status: raw.parent.status as Quote['status'],
                  withholdings: null,
                  items: [],
                  client: (base.client ?? base.contact) as Quote['client'],
                  contact: base.contact ?? base.client ?? null,
              } as Quote)
            : null;

    return {
        ...base,
        parent_document_id: raw.parent_document_id ?? null,
        quote: relatedQuote,
        due_date: raw.due_date ?? null,
        payment_terms: raw.payment_terms === 'cash' || raw.payment_terms === 'credit' ? raw.payment_terms : null,
        payment_days: raw.payment_days ?? null,
        customer_order_number: raw.customer_order_number ?? null,
        items: (raw.items ?? []).map((item) => mapSalesOrderItem(item)),
        client: raw.client ? mapContactDto(raw.client) : raw.contact ? mapContactDto(raw.contact) : (base.client as SalesOrder['client']),
        status:
            raw.status && ['draft', 'confirmed', 'in_process', 'invoiced', 'cancelled'].includes(raw.status)
                ? (raw.status as SalesOrder['status'])
                : undefined,
    };
}

export function mapSalesInvoice(raw: SaleDocumentDto): SalesInvoice {
    const base = mapSaleDocument(raw);

    return {
        ...base,
        parent_document_id: raw.parent_document_id ?? null,
        due_date: raw.due_date ?? null,
        payment_terms: raw.payment_terms === 'cash' || raw.payment_terms === 'credit' ? raw.payment_terms : null,
        payment_days: raw.payment_days ?? null,
        customer_order_number: raw.customer_order_number ?? null,
        payment_status:
            raw.payment_status && ['unpaid', 'partial', 'paid'].includes(raw.payment_status)
                ? (raw.payment_status as SalesInvoice['payment_status'])
                : 'unpaid',
        amount_paid: Number(raw.amount_paid ?? 0),
        withholdings: Array.isArray(raw.withholdings)
            ? raw.withholdings.map((withholding) => mapSalesInvoiceWithholding(withholding as Record<string, unknown>))
            : [],
        items: (raw.items ?? []).map((item) => mapSalesInvoiceItem(item)),
        client: raw.client ? mapContactDto(raw.client) : raw.contact ? mapContactDto(raw.contact) : (base.client as SalesInvoice['client']),
        status:
            raw.status && ['draft', 'issued', 'partial', 'paid', 'voided'].includes(raw.status)
                ? (raw.status as SalesInvoice['status'])
                : undefined,
        invoiced_at: raw.invoiced_at ?? null,
    };
}

export function mapSaleCreditDebitNote(raw: SaleDocumentDto): SaleCreditDebitNote {
    const base = mapSaleDocument(raw);
    const noteType = raw.document_type === 'sale_debit_note' ? 'debit' : 'credit';
    const invoiceSource = raw.parent;
    const salesInvoice =
        invoiceSource && invoiceSource.document_type === 'sales_invoice'
            ? ({
                  id: invoiceSource.id,
                  document_number: invoiceSource.document_number ?? null,
                  document_type: 'sales_invoice',
                  parent_document_id: null,
                  contact_id: invoiceSource.contact_id ?? raw.contact_id ?? null,
                  date: invoiceSource.date,
                  due_date: null,
                  delivery_date: null,
                  description: null,
                  payment_terms: null,
                  payment_days: null,
                  payment_status: 'unpaid',
                  amount_paid: 0,
                  customer_order_number: null,
                  status: invoiceSource.status as SalesInvoice['status'],
                  withholdings: [],
                  items: [],
                  client: (base.client ?? base.contact) as SaleCreditDebitNote['client'],
                  contact: base.contact ?? base.client ?? null,
              } as SalesInvoice)
            : null;

    return {
        ...base,
        note_type: noteType,
        parent_document_id: Number(raw.parent_document_id ?? 0),
        salesInvoice,
        due_date: raw.due_date ?? null,
        items: (raw.items ?? []).map((item) => mapSaleNoteItem(item)),
        client: raw.client ? mapContactDto(raw.client) : raw.contact ? mapContactDto(raw.contact) : (base.client as SaleCreditDebitNote['client']),
        status:
            raw.status && ['draft', 'issued', 'voided'].includes(raw.status)
                ? (raw.status as SaleCreditDebitNote['status'])
                : undefined,
    };
}

export function mapSalePayment(raw: SalePaymentDto): SalePayment {
    const invoice = raw.invoice
        ? 'items' in raw.invoice
            ? mapSalesInvoice(raw.invoice as SaleDocumentDto)
            : mapSaleDocumentSummary(raw.invoice as SaleDocumentSummaryDto)
        : null;

    return {
        id: raw.id,
        cash_receipt_id: raw.cash_receipt_id ?? null,
        cash_receipt_number: raw.cash_receipt_number ?? null,
        sale_invoice_id: Number(raw.sale_invoice_id ?? 0),
        payment_date: raw.payment_date ?? null,
        amount: Number(raw.amount ?? 0),
        reference: raw.reference ?? null,
        notes: raw.notes ?? null,
        created_by_user_id: raw.created_by_user_id ?? null,
        invoice,
    };
}

export function mapCashReceipt(raw: CashReceiptDto): CashReceipt {
    const payments = (raw.payments ?? raw.salePayments ?? []).map((payment) => mapSalePayment(payment));

    return {
        id: Number(raw.id ?? 0),
        receipt_number: Number(raw.receipt_number ?? 0),
        contact_id: Number(raw.contact_id ?? 0),
        client: raw.client ? mapContactDto(raw.client) : raw.contact ? mapContactDto(raw.contact) : null,
        contact: raw.contact ? mapContactDto(raw.contact) : raw.client ? mapContactDto(raw.client) : null,
        payment_date: raw.payment_date ?? '',
        account_id: raw.account_id ?? null,
        account: raw.account
            ? {
                  id: Number(raw.account.id),
                  code: raw.account.code,
                  name: raw.account.name,
              }
            : null,
        payment_method: raw.payment_method ?? null,
        reference: raw.reference ?? null,
        notes: raw.notes ?? null,
        total: Number(raw.total ?? payments.reduce((sum, payment) => sum + Number(payment.amount ?? 0), 0)),
        payments,
        created_by_user_id: raw.created_by_user_id ?? null,
        status: raw.status === 'recorded' ? 'recorded' : 'recorded',
        journal_entry: raw.journal_entry ?? null,
        created_at: raw.created_at,
        updated_at: raw.updated_at,
    };
}

export function mapCashReceiptApplication(raw: Record<string, unknown>): CashReceiptApplication {
    return {
        sale_invoice_id: Number(raw.id ?? raw.sale_invoice_id ?? 0),
        invoice_number: String(raw.document_number ?? raw.invoice_number ?? ''),
        date: raw.date == null ? null : String(raw.date),
        due_date: raw.due_date == null ? null : String(raw.due_date),
        invoice_total: Number(raw.total ?? raw.invoice_total ?? 0),
        current_balance: Number(raw.current_balance ?? 0),
        amount_to_apply: Number(raw.amount_to_apply ?? 0),
    };
}

export function mapToCreateDto(document: SaleDocument): CreateSaleDocumentDto {
    return {
        document_type: document.document_type,
        parent_document_id: document.parent_document_id ?? null,
        quote_id: document.quote_id ?? null,
        contact_id: document.contact_id ?? document.contact?.id ?? document.client?.id ?? null,
        date: document.date,
        due_date: document.due_date ?? null,
        delivery_date: document.delivery_date ?? null,
        description: document.description ?? null,
        payment_terms: document.payment_terms ?? null,
        payment_days: document.payment_days ?? null,
        payment_status: document.payment_status,
        amount_paid: document.amount_paid ?? 0,
        customer_order_number: document.customer_order_number ?? null,
        status: document.status,
        withholdings: document.withholdings ?? null,
        executed_by_user_id: document.executed_by_user_id ?? document.executor?.id ?? null,
        items: (document.items ?? []).map((item) => ({
            id: item.id,
            sale_document_id: item.sale_document_id,
            item_id: item.item_id,
            item_name: item.item_name,
            quantity: item.quantity,
            price: item.price,
            tax: item.tax,
            total: item.total,
            total_tax: item.total_tax,
            supplier_code: item.supplier_code,
            supplier_color: item.supplier_color,
            order_width: item.order_width ?? null,
            description: item.description,
        })),
    };
}

export function mapToUpdateDto(document: SaleDocument): UpdateSaleDocumentDto {
    return {
        ...mapToCreateDto(document),
    };
}

export function mapToCreateQuoteDto(form: Partial<Quote>): CreateQuoteDto {
    return {
        document_number: form.document_number ?? null,
        contact_id: form.contact_id ?? form.client?.id ?? form.contact?.id ?? null,
        date: form.date ?? new Date().toISOString().slice(0, 10),
        due_date: form.due_date ?? null,
        description: form.description ?? null,
        payment_terms: form.payment_terms ?? null,
        payment_days: form.payment_terms === 'credit' ? (form.payment_days ?? 0) : null,
        customer_order_number: form.customer_order_number ?? null,
        status: form.status ?? 'draft',
        items: (form.items ?? []).map((item) => ({
            id: item.id,
            sale_document_id: item.sale_document_id,
            item_id: item.item_id,
            item_name: item.item_name,
            quantity: item.quantity,
            unit_price: Number((item as QuoteItem).unit_price ?? item.price ?? 0),
            discount: Number(item.discount ?? 0),
            tax_rate: Number((item as QuoteItem).tax_rate ?? item.tax ?? 0),
            subtotal: Number((item as QuoteItem).subtotal ?? item.total ?? 0),
            price: Number((item as QuoteItem).unit_price ?? item.price ?? 0),
            tax: Number((item as QuoteItem).tax_rate ?? item.tax ?? 0),
            total: Number((item as QuoteItem).subtotal ?? item.total ?? 0),
            total_tax: Number(item.total_tax ?? 0),
            supplier_code: item.supplier_code,
            supplier_color: item.supplier_color,
            order_width: item.order_width ?? null,
            description: item.description,
        })),
    };
}

export function mapToCreateSalesOrderDto(form: Partial<SalesOrder>): CreateSalesOrderDto {
    return {
        document_number: form.document_number ?? null,
        parent_document_id: form.parent_document_id ?? form.quote?.id ?? null,
        contact_id: form.contact_id ?? form.client?.id ?? form.contact?.id ?? null,
        date: form.date ?? new Date().toISOString().slice(0, 10),
        due_date: form.due_date ?? null,
        description: form.description ?? null,
        payment_terms: form.payment_terms ?? null,
        payment_days: form.payment_terms === 'credit' ? (form.payment_days ?? 0) : null,
        customer_order_number: form.customer_order_number ?? null,
        status: form.status ?? 'draft',
        items: (form.items ?? []).map((item) => ({
            id: item.id,
            sale_document_id: item.sale_document_id,
            item_id: item.item_id,
            item_name: item.item_name,
            quantity: item.quantity,
            unit_price: Number((item as SalesOrderItem).unit_price ?? item.price ?? 0),
            discount: Number(item.discount ?? 0),
            tax_rate: Number((item as SalesOrderItem).tax_rate ?? item.tax ?? 0),
            subtotal: Number((item as SalesOrderItem).subtotal ?? item.total ?? 0),
            price: Number((item as SalesOrderItem).unit_price ?? item.price ?? 0),
            tax: Number((item as SalesOrderItem).tax_rate ?? item.tax ?? 0),
            total: Number((item as SalesOrderItem).subtotal ?? item.total ?? 0),
            total_tax: Number(item.total_tax ?? 0),
            supplier_code: item.supplier_code,
            supplier_color: item.supplier_color,
            order_width: item.order_width ?? null,
            description: item.description,
        })),
    };
}

export function mapToCreateSalesInvoiceDto(form: Partial<SalesInvoice>): CreateSalesInvoiceDto {
    return {
        document_number: form.document_number ?? null,
        parent_document_id: form.parent_document_id ?? null,
        contact_id: form.contact_id ?? form.client?.id ?? form.contact?.id ?? null,
        date: form.date ?? new Date().toISOString().slice(0, 10),
        due_date: form.due_date ?? null,
        description: form.description ?? null,
        payment_terms: form.payment_terms ?? null,
        payment_days: form.payment_terms === 'credit' ? (form.payment_days ?? 0) : null,
        customer_order_number: form.customer_order_number ?? null,
        status: 'draft',
        withholdings: (form.withholdings ?? [])
            .filter((withholding) => String(withholding.name ?? '').trim() !== '')
            .map((withholding) => ({
                retention_id: withholding.retention_id ?? null,
                name: String(withholding.name ?? '').trim(),
                percentage: Number(withholding.percentage ?? 0),
                base_amount: withholding.base_amount == null ? null : Number(withholding.base_amount),
            })),
        items: (form.items ?? []).map((item) => ({
            id: item.id,
            sale_document_id: item.sale_document_id,
            item_id: item.item_id,
            item_name: item.item_name,
            quantity: item.quantity,
            unit_price: Number((item as SalesInvoiceItem).unit_price ?? item.price ?? 0),
            discount: Number(item.discount ?? 0),
            tax_rate: Number((item as SalesInvoiceItem).tax_rate ?? item.tax ?? 0),
            subtotal: Number((item as SalesInvoiceItem).subtotal ?? item.total ?? 0),
            price: Number((item as SalesInvoiceItem).unit_price ?? item.price ?? 0),
            tax: Number((item as SalesInvoiceItem).tax_rate ?? item.tax ?? 0),
            total: Number((item as SalesInvoiceItem).subtotal ?? item.total ?? 0),
            total_tax: Number(item.total_tax ?? 0),
            supplier_code: item.supplier_code,
            supplier_color: item.supplier_color,
            order_width: item.order_width ?? null,
            description: item.description,
        })),
    };
}

export function mapToCreateSaleCreditDebitNoteDto(form: Partial<SaleCreditDebitNote>): CreateSaleCreditDebitNoteDto {
    return {
        type: form.note_type ?? (form.document_type === 'sale_debit_note' ? 'debit' : 'credit'),
        document_number: form.document_number ?? null,
        parent_document_id: Number(form.parent_document_id ?? form.salesInvoice?.id ?? 0),
        contact_id: form.contact_id ?? form.client?.id ?? form.contact?.id ?? null,
        date: form.date ?? new Date().toISOString().slice(0, 10),
        due_date: form.due_date ?? null,
        description: form.description ?? null,
        status: 'draft',
        items: (form.items ?? []).map((item) => ({
            id: item.id,
            sale_document_id: item.sale_document_id,
            item_id: item.item_id,
            item_name: item.item_name,
            quantity: item.quantity,
            unit_price: Number(item.unit_price ?? item.price ?? 0),
            discount: Number(item.discount ?? 0),
            tax_rate: Number(item.tax_rate ?? item.tax ?? 0),
            subtotal: Number(item.subtotal ?? item.total ?? 0),
            price: Number(item.unit_price ?? item.price ?? 0),
            tax: Number(item.tax_rate ?? item.tax ?? 0),
            total: Number(item.subtotal ?? item.total ?? 0),
            total_tax: Number(item.total_tax ?? 0),
            supplier_code: item.supplier_code,
            supplier_color: item.supplier_color,
            order_width: item.order_width ?? null,
            description: item.description,
        })),
    };
}

export function mapToUpdateSaleCreditDebitNoteDto(form: Partial<SaleCreditDebitNote>): UpdateSaleCreditDebitNoteDto {
    return {
        ...mapToCreateSaleCreditDebitNoteDto(form),
    };
}

export function mapToCreateCashReceiptDto(form: Partial<CashReceipt>): CreateCashReceiptDto {
    return {
        contact_id: form.contact_id ?? form.client?.id ?? form.contact?.id ?? null,
        payment_date: form.payment_date ?? new Date().toISOString().slice(0, 10),
        account_id: form.account_id ?? null,
        payment_method: form.payment_method ?? null,
        reference: form.reference ?? null,
        notes: form.notes ?? null,
        applications: (form.payments ?? []).map((payment) => ({
            sale_invoice_id: Number(payment.sale_invoice_id ?? 0),
            amount: Number(payment.amount ?? 0),
        })),
    };
}
