import type { OpType } from './types';

/**
 * DTO para listado de OPs (respuesta del endpoint de lista)
 * Ajustar nombres de campos según la respuesta real del backend
 */
export interface OpListItemDto {
    id: number;
    /** Código de la OP (ej: "MF-2024-001") */
    op_code: string; // Ajustar si el backend usa otro nombre (ej: "code", "op_number")
    /** Referencia o descripción corta */
    reference: string; // Ajustar si el backend usa otro nombre (ej: "ref", "description")
    /** Tipo de OP */
    type: OpType; // Ajustar si el backend usa otro nombre (ej: "op_type", "category")
}

/**
 * DTO para detalle básico de una OP
 * Ajustar nombres de campos según la respuesta real del backend
 */
export interface OpDetailDto {
    id: number;
    op_code: string;
    reference: string;
    type: OpType;
    status: string;
    season: string | null; // Ajustar si usa "temporada"
    destination: string | null; // Ajustar si usa "destino"
    units: number; // Ajustar si usa "cantidad", "qty"
    delivery_date: string | null; // Ajustar si usa "fecha_entrega"
    section: string | null; // Ajustar si usa "seccion"
    group: string | null; // Ajustar si usa "grupo"
    world: string | null; // Ajustar si usa "mundo"
    base: string | null;
    brand: string | null; // Ajustar si usa "marca"
    sizes: string[]; // Ajustar si usa "tallas" o viene como string separado
    price_billing: number | null; // Ajustar si usa "precio_facturacion"
    price_sale: number | null; // Ajustar si usa "precio_venta"
    ecommerce: boolean;
    source?: string | null; // Ajustar si usa "origen", "fuente"
    priority?: number | null; // Ajustar si usa "prioridad"
    base_yarn?: string | null; // Ajustar si usa "hilo_base"
    estimated_delivery_time?: number | null; // Ajustar si usa "tiempo_entrega_estimado"
}

/**
 * Parámetros para la petición de lista de OPs
 */
export interface FetchOpListParams {
    include_samples?: boolean; // Incluir muestras (MU, MUT)
    include_products?: boolean; // Incluir productos (MF, MFT)
    search?: string; // Búsqueda por texto
}

/**
 * Respuesta del endpoint de lista de OPs
 * Ajustar según la estructura real de la respuesta
 */
export interface OpListResponseDto {
    data: OpListItemDto[];
    // Si el endpoint usa paginación, agregar campos como:
    // meta?: { current_page: number; last_page: number; total: number };
}

/**
 * Respuesta del endpoint de detalle de OP
 * Ajustar según la estructura real de la respuesta
 */
export interface OpDetailResponseDto {
    data: OpDetailDto;
}
