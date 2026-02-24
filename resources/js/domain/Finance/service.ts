import axios from 'axios';
import type {
    Retention,
    RetentionListQuery,
    RetentionPayload,
    Tax,
    TaxPayload,
} from './types';
import type { RetentionDto, TaxDto } from './dto';
import { mapRetentionDto, mapTaxDto } from './mappers';

const TAX_ENDPOINT = '/impuestos';
const TAX_DATA_ENDPOINT = '/impuestos/data';
const RETENTION_ENDPOINT = '/retenciones';
const RETENTION_DATA_ENDPOINT = '/retenciones/data';

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
    const res = await axios.get<{ data: RetentionDto[]; total?: number; current_page?: number }>(
        RETENTION_DATA_ENDPOINT,
        { params },
    );
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
