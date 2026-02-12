import type { OpListItemDto, OpDetailDto } from './dto';
import type { OpOption, OpBasicInfo, OpType } from './types';

/**
 * Normaliza texto para búsqueda:
 * - Convierte a minúsculas
 * - Elimina espacios al inicio y fin
 * - Reemplaza múltiples espacios por uno solo
 * - Quita tildes/diacríticos
 * - Normaliza guiones: " - ", " -", "- " -> "-"
 */
export function normalizeSearch(text: string): string {
    return text
        .toLowerCase()
        .trim()
        .replace(/\s+/g, ' ') // Múltiples espacios -> uno solo
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '') // Quita diacríticos
        .replace(/\s+-\s*|\s*-\s+/g, '-'); // Normaliza guiones
}

/**
 * Determina el label de grupo según el tipo de OP
 */
function getGroupLabel(type: OpType): string {
    if (type === 'MF' || type === 'MFT') {
        return 'Productos';
    }
    if (type === 'MU' || type === 'MUT') {
        return 'Muestras';
    }
    return 'Otros';
}

/**
 * Mapea un DTO de listado a OpOption para uso en selectores
 */
export function mapOpToOption(dto: OpListItemDto): OpOption {
    const name = `${dto.op_code} — ${dto.reference}`;
    const searchText = normalizeSearch(`${dto.op_code} ${dto.reference} ${dto.type}`);

    return {
        id: dto.id,
        opCode: dto.op_code,
        reference: dto.reference,
        type: dto.type,
        groupLabel: getGroupLabel(dto.type),
        name,
        searchText,
    };
}

/**
 * Mapea un DTO de detalle a OpBasicInfo
 */
export function mapOpDetailToBasicInfo(dto: OpDetailDto): OpBasicInfo {
    return {
        id: dto.id,
        opCode: dto.op_code,
        reference: dto.reference,
        type: dto.type,
        status: dto.status,
        season: dto.season,
        destination: dto.destination,
        units: dto.units,
        deliveryDate: dto.delivery_date,
        section: dto.section,
        group: dto.group,
        world: dto.world,
        base: dto.base,
        brand: dto.brand,
        sizes: dto.sizes,
        priceBilling: dto.price_billing,
        priceSale: dto.price_sale,
        ecommerce: dto.ecommerce,
        source: dto.source,
        priority: dto.priority,
        baseYarn: dto.base_yarn,
        estimatedDeliveryTime: dto.estimated_delivery_time,
    };
}
