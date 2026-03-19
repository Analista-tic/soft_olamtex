import axios from 'axios';
import { defineStore } from 'pinia';
import { ref } from 'vue';

import { defaultPaginatedMeta, type PaginatedMeta, useDocumentStore } from '@/composables/useDocumentStore';
import type {
    CashReceipt,
    CashReceiptApplication,
    CashReceiptDto,
    CashReceiptFilters,
    CreateCashReceiptDto,
    CreateQuoteDto,
    CreateSaleCreditDebitNoteDto,
    CreateSalesInvoiceDto,
    CreateSalesOrderDto,
    Quote,
    QuoteFilters,
    SaleCreditDebitNote,
    SaleDocumentDto,
    SaleNoteFilters,
    SalesInvoice,
    SalesInvoiceFilters,
    SalesOrder,
    SalesOrderFilters,
    UpdateQuoteDto,
    UpdateSaleCreditDebitNoteDto,
    UpdateSalesInvoiceDto,
    UpdateSalesOrderDto,
} from '@/domain/Income';
import {
    mapCashReceipt,
    mapCashReceiptApplication,
    mapQuote,
    mapSaleCreditDebitNote,
    mapSalesInvoice,
    mapSalesOrder,
    QUOTE_STATUSES,
    SALES_ORDER_STATUSES,
} from '@/domain/Income';

const defaultQuoteFilters = (): QuoteFilters => ({
    page: 1,
    per_page: 10,
    search: '',
    status: 'all',
    contact_id: null,
    date_from: '',
    date_to: '',
});

const defaultSalesOrderFilters = (): SalesOrderFilters => ({
    page: 1,
    per_page: 10,
    search: '',
    status: 'all',
    contact_id: null,
    date_from: '',
    date_to: '',
    origin: undefined,
});

const defaultSalesInvoiceFilters = (): SalesInvoiceFilters => ({
    page: 1,
    per_page: 10,
    search: '',
    status: 'all',
    payment_status: 'all',
    contact_id: null,
    date_from: '',
    date_to: '',
    origin: undefined,
});

const defaultNoteFilters = (): SaleNoteFilters => ({
    page: 1,
    per_page: 10,
    search: '',
    type: undefined,
    status: 'all',
    contact_id: null,
    date_from: '',
    date_to: '',
});

const defaultCashReceiptFilters = (): CashReceiptFilters => ({
    page: 1,
    per_page: 10,
    search: '',
    contact_id: null,
    date_from: null,
    date_to: null,
});

function setFilters<TFilters extends object>(current: { value: TFilters }, defaults: () => TFilters, filters: TFilters): void {
    current.value = {
        ...defaults(),
        ...filters,
    };
}

function upsertItem<TItem extends { id: number }>(
    items: { value: TItem[] },
    meta: { value: PaginatedMeta },
    item: TItem,
): void {
    const current = [...items.value];
    const index = current.findIndex((candidate) => candidate.id === item.id);

    if (index === -1) {
        current.unshift(item);
        meta.value = {
            ...meta.value,
            total: meta.value.total + 1,
        };
    } else {
        current[index] = item;
    }

    items.value = current;
}

function removeItem<TItem extends { id: number }>(
    items: { value: TItem[] },
    meta: { value: PaginatedMeta },
    id: number,
): void {
    items.value = items.value.filter((item) => item.id !== id);
    meta.value = {
        ...meta.value,
        total: Math.max(0, meta.value.total - 1),
    };
}

export const useIncomeStore = defineStore('income', () => {
    const quotes = ref<Quote[]>([]);
    const currentQuote = ref<Quote | null>(null);
    const quotesLoading = ref(false);
    const quoteFilters = ref<QuoteFilters>(defaultQuoteFilters());
    const quotesMeta = ref<PaginatedMeta>(defaultPaginatedMeta(10));

    const salesOrders = ref<SalesOrder[]>([]);
    const currentSalesOrder = ref<SalesOrder | null>(null);
    const salesOrdersLoading = ref(false);
    const salesOrderFilters = ref<SalesOrderFilters>(defaultSalesOrderFilters());
    const salesOrdersMeta = ref<PaginatedMeta>(defaultPaginatedMeta(10));

    const salesInvoices = ref<SalesInvoice[]>([]);
    const currentSalesInvoice = ref<SalesInvoice | null>(null);
    const salesInvoicesLoading = ref(false);
    const salesInvoiceFilters = ref<SalesInvoiceFilters>(defaultSalesInvoiceFilters());
    const salesInvoicesMeta = ref<PaginatedMeta>(defaultPaginatedMeta(10));

    const creditDebitNotes = ref<SaleCreditDebitNote[]>([]);
    const currentNote = ref<SaleCreditDebitNote | null>(null);
    const creditDebitNotesLoading = ref(false);
    const noteFilters = ref<SaleNoteFilters>(defaultNoteFilters());
    const notesMeta = ref<PaginatedMeta>(defaultPaginatedMeta(10));

    const cashReceipts = ref<CashReceipt[]>([]);
    const currentCashReceipt = ref<CashReceipt | null>(null);
    const cashReceiptsLoading = ref(false);
    const cashReceiptFilters = ref<CashReceiptFilters>(defaultCashReceiptFilters());
    const cashReceiptsMeta = ref<PaginatedMeta>(defaultPaginatedMeta(10));

    const pendingInvoicesForReceipt = ref<CashReceiptApplication[]>([]);

    const quoteStore = useDocumentStore<Quote, SaleDocumentDto, QuoteFilters, CreateQuoteDto, UpdateQuoteDto>({
        baseUrl: '/income/quotes',
        items: quotes,
        currentItem: currentQuote,
        loading: quotesLoading,
        meta: quotesMeta,
        filters: quoteFilters,
        mapItem: mapQuote,
    });

    const salesOrderStore = useDocumentStore<
        SalesOrder,
        SaleDocumentDto,
        SalesOrderFilters,
        CreateSalesOrderDto,
        UpdateSalesOrderDto
    >({
        baseUrl: '/income/sales-orders',
        items: salesOrders,
        currentItem: currentSalesOrder,
        loading: salesOrdersLoading,
        meta: salesOrdersMeta,
        filters: salesOrderFilters,
        mapItem: mapSalesOrder,
    });

    const salesInvoiceStore = useDocumentStore<
        SalesInvoice,
        SaleDocumentDto,
        SalesInvoiceFilters,
        CreateSalesInvoiceDto,
        UpdateSalesInvoiceDto
    >({
        baseUrl: '/income/sales-invoices',
        items: salesInvoices,
        currentItem: currentSalesInvoice,
        loading: salesInvoicesLoading,
        meta: salesInvoicesMeta,
        filters: salesInvoiceFilters,
        mapItem: mapSalesInvoice,
    });

    const noteStore = useDocumentStore<
        SaleCreditDebitNote,
        SaleDocumentDto,
        SaleNoteFilters,
        CreateSaleCreditDebitNoteDto,
        UpdateSaleCreditDebitNoteDto
    >({
        baseUrl: '/income/credit-debit-notes',
        items: creditDebitNotes,
        currentItem: currentNote,
        loading: creditDebitNotesLoading,
        meta: notesMeta,
        filters: noteFilters,
        mapItem: mapSaleCreditDebitNote,
    });

    const cashReceiptStore = useDocumentStore<
        CashReceipt,
        CashReceiptDto,
        CashReceiptFilters,
        CreateCashReceiptDto,
        CreateCashReceiptDto
    >({
        baseUrl: '/finanzas/recibos-caja',
        items: cashReceipts,
        currentItem: currentCashReceipt,
        loading: cashReceiptsLoading,
        meta: cashReceiptsMeta,
        filters: cashReceiptFilters,
        mapItem: mapCashReceipt,
    });

    async function fetchQuotes(filters: QuoteFilters = {}): Promise<void> {
        await quoteStore.fetchAll(filters);
    }

    async function fetchQuote(id: number): Promise<Quote> {
        const quote = await quoteStore.fetchOne(id);
        upsertQuote(quote);
        return quote;
    }

    async function createQuote(dto: CreateQuoteDto): Promise<Quote> {
        const quote = await quoteStore.create(dto);
        upsertQuote(quote);
        return quote;
    }

    async function updateQuote(id: number, dto: UpdateQuoteDto): Promise<Quote> {
        const quote = await quoteStore.update(id, dto);
        upsertQuote(quote);
        return quote;
    }

    async function deleteQuote(id: number): Promise<void> {
        await quoteStore.remove(id);
        removeQuote(id);
        if (currentQuote.value?.id === id) {
            currentQuote.value = null;
        }
    }

    async function convertToOrder(id: number): Promise<SalesOrder> {
        const response = await axios.post<{ data?: SaleDocumentDto } | SaleDocumentDto>(`/income/quotes/${id}/convert`);
        const payload = typeof response.data === 'object' && response.data !== null && 'data' in response.data ? response.data.data : response.data;
        const salesOrder = mapSalesOrder(payload as SaleDocumentDto);

        const existingQuote = quotes.value.find((quote) => quote.id === id);
        if (existingQuote) {
            const converted = {
                ...existingQuote,
                status: QUOTE_STATUSES.CONVERTED.value as Quote['status'],
            };

            upsertQuote(converted);

            if (currentQuote.value?.id === id) {
                currentQuote.value = converted;
            }
        }

        currentSalesOrder.value = salesOrder;
        upsertSalesOrder(salesOrder);

        return salesOrder;
    }

    function setQuoteFilters(filters: QuoteFilters): void {
        setFilters(quoteFilters, defaultQuoteFilters, filters);
    }

    function upsertQuote(quote: Quote): void {
        upsertItem(quotes, quotesMeta, quote);
    }

    function removeQuote(id: number): void {
        removeItem(quotes, quotesMeta, id);
    }

    async function fetchSalesOrders(filters: SalesOrderFilters = {}): Promise<void> {
        await salesOrderStore.fetchAll(filters);
    }

    async function fetchSalesOrder(id: number): Promise<SalesOrder> {
        const salesOrder = await salesOrderStore.fetchOne(id);
        upsertSalesOrder(salesOrder);
        return salesOrder;
    }

    async function createSalesOrder(dto: CreateSalesOrderDto): Promise<SalesOrder> {
        const salesOrder = await salesOrderStore.create(dto);
        upsertSalesOrder(salesOrder);
        return salesOrder;
    }

    async function updateSalesOrder(id: number, dto: UpdateSalesOrderDto): Promise<SalesOrder> {
        const salesOrder = await salesOrderStore.update(id, dto);
        upsertSalesOrder(salesOrder);
        return salesOrder;
    }

    async function deleteSalesOrder(id: number): Promise<void> {
        await salesOrderStore.remove(id);
        removeSalesOrder(id);
        if (currentSalesOrder.value?.id === id) {
            currentSalesOrder.value = null;
        }
    }

    async function convertToInvoice(id: number): Promise<SalesInvoice> {
        const response = await axios.post<{ data?: SaleDocumentDto } | SaleDocumentDto>(`/income/sales-orders/${id}/convert`);
        const payload = typeof response.data === 'object' && response.data !== null && 'data' in response.data ? response.data.data : response.data;
        const salesInvoice = mapSalesInvoice(payload as SaleDocumentDto);

        const existingSalesOrder = salesOrders.value.find((salesOrder) => salesOrder.id === id);
        if (existingSalesOrder) {
            const invoiced = {
                ...existingSalesOrder,
                status: SALES_ORDER_STATUSES.INVOICED.value as SalesOrder['status'],
            };

            upsertSalesOrder(invoiced);

            if (currentSalesOrder.value?.id === id) {
                currentSalesOrder.value = invoiced;
            }
        }

        currentSalesInvoice.value = salesInvoice;
        upsertSalesInvoice(salesInvoice);

        return salesInvoice;
    }

    function setSalesOrderFilters(filters: SalesOrderFilters): void {
        setFilters(salesOrderFilters, defaultSalesOrderFilters, filters);
    }

    function upsertSalesOrder(salesOrder: SalesOrder): void {
        upsertItem(salesOrders, salesOrdersMeta, salesOrder);
    }

    function removeSalesOrder(id: number): void {
        removeItem(salesOrders, salesOrdersMeta, id);
    }

    async function fetchSalesInvoices(filters: SalesInvoiceFilters = {}): Promise<void> {
        await salesInvoiceStore.fetchAll(filters);
    }

    async function fetchSalesInvoice(id: number): Promise<SalesInvoice> {
        const salesInvoice = await salesInvoiceStore.fetchOne(id);
        upsertSalesInvoice(salesInvoice);
        return salesInvoice;
    }

    async function createSalesInvoice(dto: CreateSalesInvoiceDto): Promise<SalesInvoice> {
        const salesInvoice = await salesInvoiceStore.create(dto);
        upsertSalesInvoice(salesInvoice);
        return salesInvoice;
    }

    async function updateSalesInvoice(id: number, dto: UpdateSalesInvoiceDto): Promise<SalesInvoice> {
        const salesInvoice = await salesInvoiceStore.update(id, dto);
        upsertSalesInvoice(salesInvoice);
        return salesInvoice;
    }

    async function deleteSalesInvoice(id: number): Promise<void> {
        await salesInvoiceStore.remove(id);
        removeSalesInvoice(id);
        if (currentSalesInvoice.value?.id === id) {
            currentSalesInvoice.value = null;
        }
    }

    async function emitSalesInvoice(id: number): Promise<SalesInvoice> {
        const response = await axios.post<{ data?: SaleDocumentDto } | SaleDocumentDto>(`/income/sales-invoices/${id}/emit`);
        const payload = typeof response.data === 'object' && response.data !== null && 'data' in response.data ? response.data.data : response.data;
        const salesInvoice = mapSalesInvoice(payload as SaleDocumentDto);

        currentSalesInvoice.value = salesInvoice;
        upsertSalesInvoice(salesInvoice);
        markParentSalesOrderAsInvoiced(salesInvoice.parent_document_id);

        return salesInvoice;
    }

    async function voidSalesInvoice(id: number, reason = ''): Promise<SalesInvoice> {
        const response = await axios.post<{ data?: SaleDocumentDto } | SaleDocumentDto>(`/income/sales-invoices/${id}/void`, {
            reason,
        });
        const payload = typeof response.data === 'object' && response.data !== null && 'data' in response.data ? response.data.data : response.data;
        const salesInvoice = mapSalesInvoice(payload as SaleDocumentDto);

        currentSalesInvoice.value = salesInvoice;
        upsertSalesInvoice(salesInvoice);
        markParentSalesOrderAsConfirmed(salesInvoice.parent_document_id);

        return salesInvoice;
    }

    function setSalesInvoiceFilters(filters: SalesInvoiceFilters): void {
        setFilters(salesInvoiceFilters, defaultSalesInvoiceFilters, filters);
    }

    function upsertSalesInvoice(salesInvoice: SalesInvoice): void {
        upsertItem(salesInvoices, salesInvoicesMeta, salesInvoice);
    }

    function removeSalesInvoice(id: number): void {
        removeItem(salesInvoices, salesInvoicesMeta, id);
    }

    function markParentSalesOrderAsInvoiced(parentDocumentId?: number | null): void {
        if (!parentDocumentId) {
            return;
        }

        const salesOrder = salesOrders.value.find((candidate) => candidate.id === parentDocumentId);
        if (!salesOrder) {
            return;
        }

        const updated = {
            ...salesOrder,
            status: SALES_ORDER_STATUSES.INVOICED.value as SalesOrder['status'],
        };

        upsertSalesOrder(updated);

        if (currentSalesOrder.value?.id === parentDocumentId) {
            currentSalesOrder.value = updated;
        }
    }

    function markParentSalesOrderAsConfirmed(parentDocumentId?: number | null): void {
        if (!parentDocumentId) {
            return;
        }

        const salesOrder = salesOrders.value.find((candidate) => candidate.id === parentDocumentId);
        if (!salesOrder) {
            return;
        }

        const updated = {
            ...salesOrder,
            status: SALES_ORDER_STATUSES.CONFIRMED.value as SalesOrder['status'],
        };

        upsertSalesOrder(updated);

        if (currentSalesOrder.value?.id === parentDocumentId) {
            currentSalesOrder.value = updated;
        }
    }

    async function fetchNotes(filters: SaleNoteFilters = {}): Promise<void> {
        await noteStore.fetchAll(filters);
    }

    async function fetchNote(id: number): Promise<SaleCreditDebitNote> {
        const note = await noteStore.fetchOne(id);
        upsertNote(note);
        return note;
    }

    async function createNote(dto: CreateSaleCreditDebitNoteDto): Promise<SaleCreditDebitNote> {
        const note = await noteStore.create(dto);
        upsertNote(note);
        return note;
    }

    async function updateNote(id: number, dto: UpdateSaleCreditDebitNoteDto): Promise<SaleCreditDebitNote> {
        const note = await noteStore.update(id, dto);
        upsertNote(note);
        return note;
    }

    async function deleteNote(id: number): Promise<void> {
        await noteStore.remove(id);
        removeNote(id);
        if (currentNote.value?.id === id) {
            currentNote.value = null;
        }
    }

    async function emitNote(id: number): Promise<SaleCreditDebitNote> {
        const response = await axios.post<{ data?: SaleDocumentDto } | SaleDocumentDto>(`/income/credit-debit-notes/${id}/emit`);
        const payload = typeof response.data === 'object' && response.data !== null && 'data' in response.data ? response.data.data : response.data;
        const note = mapSaleCreditDebitNote(payload as SaleDocumentDto);

        currentNote.value = note;
        upsertNote(note);

        if (note.parent_document_id) {
            await fetchSalesInvoice(note.parent_document_id);
        }

        return note;
    }

    async function voidNote(id: number, reason = ''): Promise<SaleCreditDebitNote> {
        const response = await axios.post<{ data?: SaleDocumentDto } | SaleDocumentDto>(`/income/credit-debit-notes/${id}/void`, {
            reason,
        });
        const payload = typeof response.data === 'object' && response.data !== null && 'data' in response.data ? response.data.data : response.data;
        const note = mapSaleCreditDebitNote(payload as SaleDocumentDto);

        currentNote.value = note;
        upsertNote(note);

        if (note.parent_document_id) {
            await fetchSalesInvoice(note.parent_document_id);
        }

        return note;
    }

    function setNoteFilters(filters: SaleNoteFilters): void {
        setFilters(noteFilters, defaultNoteFilters, filters);
    }

    function upsertNote(note: SaleCreditDebitNote): void {
        upsertItem(creditDebitNotes, notesMeta, note);
    }

    function removeNote(id: number): void {
        removeItem(creditDebitNotes, notesMeta, id);
    }

    async function fetchCashReceipts(filters: CashReceiptFilters = {}): Promise<void> {
        await cashReceiptStore.fetchAll(filters);
    }

    async function fetchCashReceipt(id: number): Promise<CashReceipt> {
        const receipt = await cashReceiptStore.fetchOne(id);
        upsertCashReceipt(receipt);
        return receipt;
    }

    async function createCashReceipt(dto: CreateCashReceiptDto): Promise<CashReceipt> {
        const receipt = await cashReceiptStore.create(dto);
        upsertCashReceipt(receipt);
        return receipt;
    }

    async function voidCashReceipt(id: number, reason = ''): Promise<CashReceipt> {
        const response = await axios.post<{ data?: CashReceiptDto } | CashReceiptDto>(`/finanzas/recibos-caja/${id}/void`, {
            reason,
        });
        const payload = typeof response.data === 'object' && response.data !== null && 'data' in response.data ? response.data.data : response.data;
        const receipt = mapCashReceipt(payload as CashReceiptDto);

        removeCashReceipt(id);
        if (currentCashReceipt.value?.id === id) {
            currentCashReceipt.value = null;
        }

        return receipt;
    }

    async function fetchPendingInvoices(contactId: number): Promise<CashReceiptApplication[]> {
        if (!contactId) {
            pendingInvoicesForReceipt.value = [];
            return [];
        }

        const response = await axios.get<{ data?: Array<Record<string, unknown>> } | Array<Record<string, unknown>>>(
            '/finanzas/recibos-caja/pending-invoices',
            {
                params: { contact_id: contactId },
            },
        );
        const rawData = Array.isArray(response.data) ? response.data : (response.data.data ?? []);
        const applications = rawData.map((item) => mapCashReceiptApplication(item));

        pendingInvoicesForReceipt.value = applications;

        return applications;
    }

    function setCashReceiptFilters(filters: CashReceiptFilters): void {
        setFilters(cashReceiptFilters, defaultCashReceiptFilters, filters);
    }

    function upsertCashReceipt(receipt: CashReceipt): void {
        upsertItem(cashReceipts, cashReceiptsMeta, receipt);
    }

    function removeCashReceipt(id: number): void {
        removeItem(cashReceipts, cashReceiptsMeta, id);
    }

    return {
        quotes,
        currentQuote,
        quotesLoading,
        quoteFilters,
        quotesMeta,
        salesOrders,
        currentSalesOrder,
        salesOrdersLoading,
        salesOrderFilters,
        salesOrdersMeta,
        salesInvoices,
        currentSalesInvoice,
        salesInvoicesLoading,
        salesInvoiceFilters,
        salesInvoicesMeta,
        creditDebitNotes,
        currentNote,
        creditDebitNotesLoading,
        noteFilters,
        notesMeta,
        cashReceipts,
        currentCashReceipt,
        cashReceiptsLoading,
        cashReceiptFilters,
        cashReceiptsMeta,
        pendingInvoicesForReceipt,
        fetchQuotes,
        fetchQuote,
        createQuote,
        updateQuote,
        deleteQuote,
        convertToOrder,
        setQuoteFilters,
        upsertQuote,
        removeQuote,
        fetchSalesOrders,
        fetchSalesOrder,
        createSalesOrder,
        updateSalesOrder,
        deleteSalesOrder,
        convertToInvoice,
        setSalesOrderFilters,
        upsertSalesOrder,
        removeSalesOrder,
        fetchSalesInvoices,
        fetchSalesInvoice,
        createSalesInvoice,
        updateSalesInvoice,
        deleteSalesInvoice,
        emitSalesInvoice,
        voidSalesInvoice,
        setSalesInvoiceFilters,
        upsertSalesInvoice,
        removeSalesInvoice,
        markParentSalesOrderAsInvoiced,
        markParentSalesOrderAsConfirmed,
        fetchNotes,
        fetchNote,
        createNote,
        updateNote,
        deleteNote,
        emitNote,
        voidNote,
        setNoteFilters,
        upsertNote,
        removeNote,
        fetchCashReceipts,
        fetchCashReceipt,
        createCashReceipt,
        voidCashReceipt,
        fetchPendingInvoices,
        setCashReceiptFilters,
        upsertCashReceipt,
        removeCashReceipt,
    };
});
