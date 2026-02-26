import axios from 'axios';
import type {
    CreditDebitNote,
    CreditDebitNoteListQuery,
    CreditDebitNotePayload,
    Retention,
    RetentionListQuery,
    RetentionPayload,
    Tax,
    TaxPayload,
} from './types';
import type {
    CreditDebitNoteDto,
    RetentionDto,
    TaxDto,
} from './dto';
import {
    mapCreditDebitNoteFromApi,
    mapCreditDebitNoteToApi,
    mapRetentionDto,
    mapTaxDto,
} from './mappers';

const TAX_ENDPOINT = '/impuestos';
const TAX_DATA_ENDPOINT = '/impuestos/data';
const RETENTION_ENDPOINT = '/retenciones';
const RETENTION_DATA_ENDPOINT = '/retenciones/data';
const CREDIT_DEBIT_NOTE_ENDPOINT = '/notas-credito-debito';
const CREDIT_DEBIT_NOTE_DATA_ENDPOINT = '/notas-credito-debito/data';

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
