import type { OpBasicInfoDto, ProgrammerDto, ProductionOrderDetailResponseDto } from './dto';
import type { OpOption, OpBasicInfo, OpType } from './types';
import type { Option } from '@/domain/shared';

/**
 * Normaliza texto para bÃºsqueda:
 * - Convierte a minÃºsculas
 * - Elimina espacios al inicio y fin
 * - Reemplaza mÃºltiples espacios por uno solo
 * - Quita tildes/diacrÃ­ticos
 * - Normaliza guiones: " - ", " -", "- " -> "-"
 */
export function normalizeSearch(text: string): string {
    return text
        .toLowerCase()
        .trim()
        .replace(/\s+/g, ' ') // MÃºltiples espacios -> uno solo
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '') // Quita diacrÃ­ticos
        .replace(/\s+-\s*|\s*-\s+/g, '-'); // Normaliza guiones
}

/**
 * Extrae el tipo de OP desde el cÃ³digo de OP
 */
function extractOpType(opCode: string | null | undefined): OpType {
    const code = String(opCode ?? '').trim().toUpperCase();
    if (code.startsWith('MUT')) return 'MUT';
    if (code.startsWith('MU')) return 'MU';
    if (code.startsWith('MFT')) return 'MFT';
    if (code.startsWith('MF')) return 'MF';
    return 'MF'; // Default
}

/**
 * Determina el label de grupo segÃºn el tipo de OP
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
 * Construye la referencia desde el product_sheet
 */
function buildReference(dto: ProgrammerDto): string {
    const sampleRef = dto.product_sheet?.sample_reference?.trim() ?? '';
    const productRef = dto.product_sheet?.product_reference?.trim() ?? '';

    if (sampleRef && productRef) {
        return sampleRef === productRef ? sampleRef : `${sampleRef} - ${productRef}`;
    }
    if (sampleRef) return sampleRef;
    if (productRef) return productRef;
    return 'Sin referencia';
}

/**
 * Mapea un ProgrammerDto a OpOption para uso en selectores
 */
export function mapProgrammerToOption(dto: ProgrammerDto): OpOption {
    const opCode = dto.op_code ?? '';
    const reference = buildReference(dto);
    const type = extractOpType(dto.op_code);
    const name = `${opCode} — ${reference}`;
    const searchText = normalizeSearch(`${opCode} ${reference} ${type}`);

    return {
        id: dto.id,
        opCode,
        reference,
        type,
        groupLabel: getGroupLabel(type),
        name,
        searchText,
    };
}

/**
 * Mapea un ProgrammerDto a Option simple para SelectField
 */
export function mapProgrammerToSelectOption(dto: ProgrammerDto): Option {
    const opCode = dto.op_code ?? '';
    const reference = buildReference(dto);
    const type = extractOpType(dto.op_code);
    const name = `${opCode} — ${reference}`;

    return {
        id: dto.id,
        name,
        groupLabel: getGroupLabel(type),
    };
}

/**
 * Parsea el campo sizes que puede venir en varios formatos
 */
function parseSizes(sizes: unknown): string[] {
    if (!sizes) return [];
    if (Array.isArray(sizes)) return sizes.map(String);
    if (typeof sizes === 'string') {
        try {
            const parsed = JSON.parse(sizes);
            if (Array.isArray(parsed)) return parsed.map(String);
            if (typeof parsed === 'object') return Object.keys(parsed).filter((k) => parsed[k]);
        } catch {
            return sizes.split(',').map((s) => s.trim()).filter(Boolean);
        }
    }
    if (typeof sizes === 'object') {
        return Object.keys(sizes as Record<string, boolean>).filter((k) => (sizes as Record<string, boolean>)[k]);
    }
    return [];
}

const toNullableNumber = (value: unknown): number | null => {
    if (value === null || value === undefined || value === '') return null;
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
};

export function mapOpBasicInfoDtoToOpBasicInfo(dto: OpBasicInfoDto): OpBasicInfo {
    return {
        id: dto.id,
        opCode: dto.opCode,
        reference: dto.reference,
        type: extractOpType(dto.opCode),
        status: 'N/A',
        season: dto.season ?? null,
        destination: dto.destination ?? null,
        units: 0,
        deliveryDate: dto.date ?? null,
        flatDrawingUrl: dto.flatDrawingUrl ?? null,
        section: dto.section ?? null,
        group: dto.group ?? null,
        world: dto.world ?? null,
        base: null,
        brand: dto.brand ?? null,
        sizes: [],
        priceBilling: null,
        priceSale: null,
        ecommerce: false,
        priority: toNullableNumber(dto.priority),
    };
}

/**
 * Mapea la respuesta de detalle a OpBasicInfo
 */
export function mapDetailResponseToBasicInfo(response: ProductionOrderDetailResponseDto): OpBasicInfo {
    const order = response.production_order;
    const sheet = response.product_sheet;
    const programmer = Array.isArray(response.programmers)
        ? response.programmers[0]
        : response.programmer;

    const opCode = order?.op_code ?? programmer?.op_code ?? '';
    const type = extractOpType(opCode);

    // Construir referencia
    const sampleRef = sheet?.sample_reference?.trim() ?? '';
    const productRef = sheet?.product_reference?.trim() ?? '';
    let reference = 'Sin referencia';
    if (sampleRef && productRef) {
        reference = sampleRef === productRef ? sampleRef : `${sampleRef} - ${productRef}`;
    } else if (sampleRef) {
        reference = sampleRef;
    } else if (productRef) {
        reference = productRef;
    }

    // Determinar status
    const statusRaw = order?.status ?? programmer?.status ?? 'incomplete';
    const status = statusRaw === 'complete' ? 'Completo' : 'Pendiente';

    return {
        id: order?.id ?? programmer?.id ?? 0,
        opCode,
        reference,
        type,
        status,
        season: order?.season_display ?? (String(order?.season ?? programmer?.season ?? '') || null),
        destination: order?.destination?.name ?? null,
        units: order?.units ?? programmer?.units ?? 0,
        deliveryDate: order?.estimated_delivery_date ?? programmer?.estimated_delivery_date ?? null,
        flatDrawingUrl: sheet?.flat_sketch_path ?? null,
        section: sheet?.section?.name ?? null,
        group: sheet?.group?.name ?? null,
        world: sheet?.world?.name ?? null,
        base: sheet?.fabric_base?.name ?? null,
        brand: sheet?.brand?.name ?? null,
        sizes: parseSizes(sheet?.sizes),
        priceBilling: sheet?.billing_price != null ? Number(sheet.billing_price) : null,
        priceSale: sheet?.selling_price != null ? Number(sheet.selling_price) : null,
        ecommerce: Boolean(programmer?.ecommerce_units),
        source: order?.source ?? programmer?.source ?? null,
        priority: order?.priority != null ? Number(order.priority) : (programmer?.priority != null ? Number(programmer.priority) : null),
        baseYarn: sheet?.yarn_base?.name ?? null,
        estimatedDeliveryTime: null, // No disponible en la respuesta actual
    };
}


