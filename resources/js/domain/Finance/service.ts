import axios from 'axios';
import type { Tax, TaxPayload } from './types';
import type { TaxDto } from './dto';
import { mapTaxDto } from './mappers';

const ENDPOINT = '/settings/taxes';

export async function fetchTaxes(): Promise<Tax[]> {
    const res = await axios.get<TaxDto[]>(ENDPOINT);
    return res.data.map(mapTaxDto);
}

export async function fetchTax(id: number): Promise<Tax> {
    const res = await axios.get<TaxDto>(`${ENDPOINT}/${id}`);
    return mapTaxDto(res.data);
}

export async function createTax(payload: TaxPayload): Promise<Tax> {
    const res = await axios.post<TaxDto>(ENDPOINT, payload);
    return mapTaxDto(res.data);
}

export async function updateTax(id: number, payload: TaxPayload): Promise<Tax> {
    const res = await axios.put<TaxDto>(`${ENDPOINT}/${id}`, payload);
    return mapTaxDto(res.data);
}

export async function deleteTax(id: number): Promise<void> {
    await axios.delete(`${ENDPOINT}/${id}`);
}

// Retentions service
const RETENTION_ENDPOINT = '/settings/retentions';

import type { Retention, RetentionPayload } from './types';
import type { RetentionDto } from './dto';
import { mapRetentionDto } from './mappers';

export async function fetchRetentions(): Promise<Retention[]> {
    const res = await axios.get<RetentionDto[]>(RETENTION_ENDPOINT);
    return res.data.map(mapRetentionDto);
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
