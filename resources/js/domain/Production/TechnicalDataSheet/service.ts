import axios from 'axios';
import type { Option } from '@/domain/shared';
import type { OpBasicInfoDto } from './dto';
import type { OpBasicInfo } from './types';
import { mapOpBasicInfoDtoToOpBasicInfo } from './mappers';

const ENDPOINTS = {
    OPTIONS: '/produccion/ficha-tecnica/opciones',
    BASIC_INFO: '/produccion/ficha-tecnica/:id/basica',
} as const;

export interface FetchOpOptionsParams {
    search?: string;
}

export async function fetchOpOptions(params: FetchOpOptionsParams = {}): Promise<Option[]> {
    const response = await axios.get<Option[]>(ENDPOINTS.OPTIONS, {
        params: {
            search: params.search || undefined,
        },
    });

    return Array.isArray(response.data) ? response.data : [];
}

export async function fetchOpBasicInfo(opId: string | number): Promise<OpBasicInfo> {
    const url = ENDPOINTS.BASIC_INFO.replace(':id', String(opId));
    const response = await axios.get<OpBasicInfoDto>(url);

    return mapOpBasicInfoDtoToOpBasicInfo(response.data);
}
