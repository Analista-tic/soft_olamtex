import axios from 'axios';
import type { OpListResponseDto, OpDetailResponseDto, FetchOpListParams } from './dto';
import type { OpOption, OpBasicInfo } from './types';
import { mapOpToOption, mapOpDetailToBasicInfo } from './mappers';

// ============================================================================
// ENDPOINTS - Ajustar según las rutas reales del backend
// ============================================================================
const ENDPOINTS = {
    /** Endpoint para listar OPs (GET) */
    OP_LIST: '/produccion/ops/data', // Ajustar según ruta real
    /** Endpoint para detalle de una OP (GET) - usa :id como placeholder */
    OP_DETAIL: '/produccion/ops/:id', // Ajustar según ruta real
} as const;

// ============================================================================
// PARÁMETROS DE LA API - Ajustar nombres según el backend
// ============================================================================
const API_PARAMS = {
    includeSamples: 'include_samples', // Ajustar si usa otro nombre
    includeProducts: 'include_products', // Ajustar si usa otro nombre
    search: 'search', // Ajustar si usa "q", "query", "filter", etc.
} as const;

/**
 * Parámetros para fetchOpOptions
 */
export interface FetchOpOptionsParams {
    includeSamples: boolean;
    includeProducts: boolean;
    search?: string;
}

/**
 * Obtiene lista de OPs formateadas como opciones para selectores
 *
 * @param params - Parámetros de filtrado
 * @returns Lista de OpOption para uso en combobox/autocomplete
 */
export async function fetchOpOptions(params: FetchOpOptionsParams): Promise<OpOption[]> {
    const apiParams: FetchOpListParams = {
        [API_PARAMS.includeSamples]: params.includeSamples,
        [API_PARAMS.includeProducts]: params.includeProducts,
    };

    if (params.search) {
        apiParams[API_PARAMS.search] = params.search;
    }

    const response = await axios.get<OpListResponseDto>(ENDPOINTS.OP_LIST, {
        params: apiParams,
    });

    // Mapear DTOs a OpOption
    return response.data.data.map(mapOpToOption);
}

/**
 * Obtiene información básica de una OP específica
 *
 * @param opId - ID de la OP
 * @returns Información básica de la OP
 */
export async function fetchOpBasicInfo(opId: string | number): Promise<OpBasicInfo> {
    const url = ENDPOINTS.OP_DETAIL.replace(':id', String(opId));

    const response = await axios.get<OpDetailResponseDto>(url);

    return mapOpDetailToBasicInfo(response.data.data);
}
