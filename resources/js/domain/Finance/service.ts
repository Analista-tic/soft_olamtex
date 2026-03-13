import axios from 'axios';
import type {
    CreditDebitNote,
    CreditDebitNoteListQuery,
    CreditDebitNotePayload,
    AccountingAccountListItem,
    AccountingAccountListQuery,
    AccountingAccountMovementQuery,
    AccountingAccountMovementRow,
    AccountingAccountMovementSummary,
    AccountingAccountPayload,
    ExpenseVoucherListItem,
    ExpenseVoucherListQuery,
    ExpenseVoucherRecord,
    Retention,
    RetentionListQuery,
    RetentionPayload,
    Tax,
    TaxPayload,
    TreasuryOpenDocument,
    TreasurySelectOption,
    TreasuryThirdParty,
    TreasuryVoucherDraft,
} from './types';
import type {
    CreditDebitNoteDto,
    AccountingAccountDto,
    AccountingAccountMovementRowDto,
    AccountingAccountMovementSummaryDto,
    ExpenseVoucherRecordDto,
    ExpenseVoucherListItemDto,
    RetentionDto,
    TaxDto,
    TreasuryOpenDocumentDto,
    TreasurySelectOptionDto,
    TreasuryThirdPartyDto,
} from './dto';
import {
    mapCreditDebitNoteFromApi,
    mapCreditDebitNoteToApi,
    mapExpenseVoucherPayloadToApi,
    mapExpenseVoucherListItemFromApi,
    mapExpenseVoucherRecordFromApi,
    mapAccountingAccountFromApi,
    mapAccountingAccountMovementRowFromApi,
    mapAccountingAccountMovementSummaryFromApi,
    mapRetentionDto,
    mapTreasuryOpenDocumentFromApi,
    mapTreasuryOptionFromApi,
    mapTreasuryThirdPartyFromApi,
    mapTaxDto,
} from './mappers';

const TAX_ENDPOINT = '/impuestos';
const TAX_DATA_ENDPOINT = '/impuestos/data';
const RETENTION_ENDPOINT = '/retenciones';
const RETENTION_DATA_ENDPOINT = '/retenciones/data';
const CREDIT_DEBIT_NOTE_ENDPOINT = '/notas-credito-debito';
const CREDIT_DEBIT_NOTE_DATA_ENDPOINT = '/notas-credito-debito/data';
const TREASURY_SUPPLIERS_ENDPOINT = '/finanzas/tesoreria/proveedores';
const TREASURY_CUSTOMERS_ENDPOINT = '/finanzas/tesoreria/clientes';
const TREASURY_PAYABLE_DOCUMENTS_ENDPOINT = '/finanzas/tesoreria/documentos-por-pagar';
const TREASURY_RECEIVABLE_DOCUMENTS_ENDPOINT = '/finanzas/tesoreria/documentos-por-cobrar';
const TREASURY_ACCOUNTS_ENDPOINT = '/finanzas/tesoreria/cuentas';
const TREASURY_PAYMENT_METHODS_ENDPOINT = '/finanzas/tesoreria/metodos-pago';
const EXPENSE_VOUCHERS_ENDPOINT = '/finanzas/comprobantes-egreso';
const EXPENSE_VOUCHERS_DATA_ENDPOINT = '/finanzas/comprobantes-egreso/data';
const ACCOUNT_CHART_ENDPOINT = '/finanzas/catalogo-cuentas';
const ACCOUNT_CHART_DATA_ENDPOINT = '/finanzas/catalogo-cuentas/data';

export async function fetchTaxes(): Promise<Tax[]> {
    const res = await axios.get<{ data: TaxDto[] } | TaxDto[]>(TAX_DATA_ENDPOINT);
    const rows = Array.isArray(res.data) ? res.data : Array.isArray(res.data?.data) ? res.data.data : [];
    return rows.map(mapTaxDto);
}

export async function fetchTax(id: number): Promise<Tax> {
    const res = await axios.get<TaxDto>(`${TAX_ENDPOINT}/${id}`);
    return mapTaxDto(res.data);
}

export async function createTax(payload: TaxPayload): Promise<Tax> {
    const res = await axios.post<TaxDto>(TAX_ENDPOINT, payload);
    return mapTaxDto(res.data);
}

export async function updateTax(id: number, payload: TaxPayload): Promise<Tax> {
    const res = await axios.put<TaxDto>(`${TAX_ENDPOINT}/${id}`, payload);
    return mapTaxDto(res.data);
}

export async function deleteTax(id: number): Promise<void> {
    await axios.delete(`${TAX_ENDPOINT}/${id}`);
}

export async function fetchRetentionsPage(params: RetentionListQuery = {}): Promise<{
    data: Retention[];
    total?: number;
    current_page?: number;
}> {
    const res = await axios.get<{ data: RetentionDto[]; total?: number; current_page?: number }>(RETENTION_DATA_ENDPOINT, { params });
    return {
        data: Array.isArray(res.data.data) ? res.data.data.map(mapRetentionDto) : [],
        total: res.data.total,
        current_page: res.data.current_page,
    };
}

export async function fetchRetention(id: number): Promise<Retention> {
    const res = await axios.get<RetentionDto>(`${RETENTION_ENDPOINT}/${id}`);
    return mapRetentionDto(res.data);
}

export async function createRetention(payload: RetentionPayload): Promise<Retention> {
    const res = await axios.post<RetentionDto>(RETENTION_ENDPOINT, payload);
    return mapRetentionDto(res.data);
}

export async function updateRetention(id: number, payload: RetentionPayload): Promise<Retention> {
    const res = await axios.put<RetentionDto>(`${RETENTION_ENDPOINT}/${id}`, payload);
    return mapRetentionDto(res.data);
}

export async function deleteRetention(id: number): Promise<void> {
    await axios.delete(`${RETENTION_ENDPOINT}/${id}`);
}

type CreditDebitNoteEntityResponse = CreditDebitNoteDto | { data: CreditDebitNoteDto };
type CreditDebitNoteListResponse = {
    data?: CreditDebitNoteDto[];
    total?: number;
    current_page?: number;
} | CreditDebitNoteDto[];

function isCreditDebitNoteEntityEnvelope(raw: CreditDebitNoteEntityResponse): raw is { data: CreditDebitNoteDto } {
    return typeof raw === 'object'
        && raw !== null
        && 'data' in raw
        && Boolean((raw as { data?: CreditDebitNoteDto }).data);
}

function pickCreditDebitNoteEntity(raw: CreditDebitNoteEntityResponse): CreditDebitNoteDto {
    if (isCreditDebitNoteEntityEnvelope(raw)) {
        return raw.data;
    }

    return raw as CreditDebitNoteDto;
}

function pickCreditDebitNoteRows(raw: CreditDebitNoteListResponse): CreditDebitNoteDto[] {
    if (Array.isArray(raw)) {
        return raw;
    }
    return Array.isArray(raw.data) ? raw.data : [];
}

export async function list(params: CreditDebitNoteListQuery = {}): Promise<{
    data: CreditDebitNote[];
    total?: number;
    current_page?: number;
}> {
    const res = await axios.get<CreditDebitNoteListResponse>(CREDIT_DEBIT_NOTE_DATA_ENDPOINT, { params });
    const rows = pickCreditDebitNoteRows(res.data).map(mapCreditDebitNoteFromApi);

    return {
        data: rows,
        total: !Array.isArray(res.data) ? res.data.total : undefined,
        current_page: !Array.isArray(res.data) ? res.data.current_page : undefined,
    };
}

export async function get(id: number | string): Promise<CreditDebitNote> {
    const res = await axios.get<CreditDebitNoteEntityResponse>(`${CREDIT_DEBIT_NOTE_ENDPOINT}/${id}`);
    return mapCreditDebitNoteFromApi(pickCreditDebitNoteEntity(res.data));
}

export async function create(payload: CreditDebitNotePayload): Promise<CreditDebitNote> {
    const res = await axios.post<CreditDebitNoteEntityResponse>(
        CREDIT_DEBIT_NOTE_ENDPOINT,
        mapCreditDebitNoteToApi(payload),
    );
    return mapCreditDebitNoteFromApi(pickCreditDebitNoteEntity(res.data));
}

export async function update(id: number | string, payload: CreditDebitNotePayload): Promise<CreditDebitNote> {
    const res = await axios.put<CreditDebitNoteEntityResponse>(
        `${CREDIT_DEBIT_NOTE_ENDPOINT}/${id}`,
        mapCreditDebitNoteToApi(payload),
    );
    return mapCreditDebitNoteFromApi(pickCreditDebitNoteEntity(res.data));
}

export async function remove(id: number | string): Promise<void> {
    await axios.delete(`${CREDIT_DEBIT_NOTE_ENDPOINT}/${id}`);
}

function pickRows<T>(raw: { data?: T[] } | T[] | undefined | null): T[] {
    if (!raw) return [];
    if (Array.isArray(raw)) return raw;
    return Array.isArray(raw.data) ? raw.data : [];
}

export async function fetchTreasurySuppliers(search = '', limit = 20): Promise<TreasuryThirdParty[]> {
    const res = await axios.get<{ data?: TreasuryThirdPartyDto[] } | TreasuryThirdPartyDto[]>(TREASURY_SUPPLIERS_ENDPOINT, {
        params: { search: search || undefined, limit },
    });

    return pickRows<TreasuryThirdPartyDto>(res.data).map(mapTreasuryThirdPartyFromApi);
}

export async function fetchTreasuryCustomers(search = '', limit = 20): Promise<TreasuryThirdParty[]> {
    const res = await axios.get<{ data?: TreasuryThirdPartyDto[] } | TreasuryThirdPartyDto[]>(TREASURY_CUSTOMERS_ENDPOINT, {
        params: { search: search || undefined, limit },
    });

    return pickRows<TreasuryThirdPartyDto>(res.data).map(mapTreasuryThirdPartyFromApi);
}

export async function fetchTreasuryPayableDocuments(contactId: number): Promise<TreasuryOpenDocument[]> {
    const res = await axios.get<{ data?: TreasuryOpenDocumentDto[] } | TreasuryOpenDocumentDto[]>(TREASURY_PAYABLE_DOCUMENTS_ENDPOINT, {
        params: { contact_id: contactId },
    });

    return pickRows<TreasuryOpenDocumentDto>(res.data).map(mapTreasuryOpenDocumentFromApi);
}

export async function fetchTreasuryReceivableDocuments(contactId: number): Promise<TreasuryOpenDocument[]> {
    const res = await axios.get<{ data?: TreasuryOpenDocumentDto[] } | TreasuryOpenDocumentDto[]>(TREASURY_RECEIVABLE_DOCUMENTS_ENDPOINT, {
        params: { contact_id: contactId },
    });

    return pickRows<TreasuryOpenDocumentDto>(res.data).map(mapTreasuryOpenDocumentFromApi);
}

export async function fetchTreasuryAccounts(): Promise<TreasurySelectOption[]> {
    const res = await axios.get<{ data?: TreasurySelectOptionDto[] } | TreasurySelectOptionDto[]>(TREASURY_ACCOUNTS_ENDPOINT);
    return pickRows<TreasurySelectOptionDto>(res.data).map(mapTreasuryOptionFromApi);
}

export async function fetchTreasuryPaymentMethods(): Promise<TreasurySelectOption[]> {
    const res = await axios.get<{ data?: TreasurySelectOptionDto[] } | TreasurySelectOptionDto[]>(TREASURY_PAYMENT_METHODS_ENDPOINT);
    return pickRows<TreasurySelectOptionDto>(res.data).map(mapTreasuryOptionFromApi);
}

export async function createExpenseVoucher(payload: TreasuryVoucherDraft): Promise<ExpenseVoucherRecord> {
    const res = await axios.post<{ data?: ExpenseVoucherRecordDto } | ExpenseVoucherRecordDto>(
        EXPENSE_VOUCHERS_ENDPOINT,
        mapExpenseVoucherPayloadToApi(payload),
    );
    const raw = (res.data as { data?: ExpenseVoucherRecordDto })?.data ?? (res.data as ExpenseVoucherRecordDto);
    return mapExpenseVoucherRecordFromApi(raw);
}

export async function updateExpenseVoucher(id: number, payload: TreasuryVoucherDraft): Promise<ExpenseVoucherRecord> {
    const res = await axios.put<{ data?: ExpenseVoucherRecordDto } | ExpenseVoucherRecordDto>(
        `${EXPENSE_VOUCHERS_ENDPOINT}/${id}`,
        mapExpenseVoucherPayloadToApi(payload),
    );
    const raw = (res.data as { data?: ExpenseVoucherRecordDto })?.data ?? (res.data as ExpenseVoucherRecordDto);
    return mapExpenseVoucherRecordFromApi(raw);
}

export async function fetchExpenseVouchersPage(params: ExpenseVoucherListQuery = {}): Promise<{
    data: ExpenseVoucherListItem[];
    total: number;
    current_page: number;
    per_page: number;
}> {
    const res = await axios.get<{
        data?: ExpenseVoucherListItemDto[];
        total?: number;
        current_page?: number;
        per_page?: number;
    }>(EXPENSE_VOUCHERS_DATA_ENDPOINT, { params });

    return {
        data: Array.isArray(res.data.data) ? res.data.data.map(mapExpenseVoucherListItemFromApi) : [],
        total: Number(res.data.total ?? 0),
        current_page: Number(res.data.current_page ?? 1),
        per_page: Number(res.data.per_page ?? 10),
    };
}

// ── Catálogo de cuentas contables ────────────────────────────────────────────

export async function fetchAccountChartPage(params: AccountingAccountListQuery = {}): Promise<{
    data: AccountingAccountListItem[];
    total: number;
    current_page: number;
    per_page: number;
}> {
    const res = await axios.get<{
        data?: AccountingAccountDto[];
        total?: number;
        current_page?: number;
        per_page?: number;
    }>(ACCOUNT_CHART_DATA_ENDPOINT, { params });

    return {
        data: Array.isArray(res.data.data) ? res.data.data.map(mapAccountingAccountFromApi) : [],
        total: Number(res.data.total ?? 0),
        current_page: Number(res.data.current_page ?? 1),
        per_page: Number(res.data.per_page ?? 50),
    };
}

export async function createAccountingAccount(payload: AccountingAccountPayload): Promise<AccountingAccountListItem> {
    const res = await axios.post<{ data: AccountingAccountDto }>(ACCOUNT_CHART_ENDPOINT, payload);
    return mapAccountingAccountFromApi(res.data.data);
}

export async function updateAccountingAccount(id: number, payload: AccountingAccountPayload): Promise<AccountingAccountListItem> {
    const res = await axios.put<{ data: AccountingAccountDto }>(`${ACCOUNT_CHART_ENDPOINT}/${id}`, payload);
    return mapAccountingAccountFromApi(res.data.data);
}

export async function toggleAccountingAccountStatus(id: number): Promise<void> {
    await axios.post(`${ACCOUNT_CHART_ENDPOINT}/${id}/toggle-status`);
}

export async function fetchAccountingAccountOptions(allowsEntries = false): Promise<AccountingAccountListItem[]> {
    const res = await axios.get<{ data?: AccountingAccountDto[] }>(`${ACCOUNT_CHART_ENDPOINT}/options`, {
        params: {
            allows_entries: allowsEntries ? 1 : undefined,
        },
    });

    return Array.isArray(res.data.data) ? res.data.data.map(mapAccountingAccountFromApi) : [];
}

export async function fetchAccountingAccountMovements(
    accountId: number,
    params: AccountingAccountMovementQuery = {},
): Promise<{
    data: AccountingAccountMovementRow[];
    summary: AccountingAccountMovementSummary;
    document_type_options: Array<{ id: string; name: string }>;
    total: number;
    current_page: number;
    per_page: number;
}> {
    const res = await axios.get<{
        data?: AccountingAccountMovementRowDto[];
        summary?: AccountingAccountMovementSummaryDto;
        document_type_options?: Array<{ id: string; name: string }>;
        total?: number;
        current_page?: number;
        per_page?: number;
    }>(`${ACCOUNT_CHART_ENDPOINT}/${accountId}/movimientos/data`, { params });

    return {
        data: Array.isArray(res.data.data) ? res.data.data.map(mapAccountingAccountMovementRowFromApi) : [],
        summary: mapAccountingAccountMovementSummaryFromApi(res.data.summary ?? {}),
        document_type_options: Array.isArray(res.data.document_type_options) ? res.data.document_type_options : [],
        total: Number(res.data.total ?? 0),
        current_page: Number(res.data.current_page ?? 1),
        per_page: Number(res.data.per_page ?? 25),
    };
}
