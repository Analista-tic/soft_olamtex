import type { ProductSheetDto, ProgrammerDto, OpBasicInfoDto, ProductionOrderDetailResponseDto } from './dto';
import type { ProductSheet, Programmer, OpOption, OpBasicInfo } from './types';
import type { Option } from '@/domain/shared';

// Helpers from TechnicalDataSheet
export function normalizeSearch(text: string): string {
    return text
        .toLowerCase()
        .trim()
        .replace(/\s+/g, ' ')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/\s+-\s*|\s*-\s+/g, '-');
}

function extractOpType(opCode: string | null | undefined): 'MU' | 'MF' | 'MUT' | 'MFT' {
    const code = String(opCode ?? '').trim().toUpperCase();
    if (code.startsWith('MUT')) return 'MUT';
    if (code.startsWith('MU')) return 'MU';
    if (code.startsWith('MFT')) return 'MFT';
    if (code.startsWith('MF')) return 'MF';
    return 'MF';
}

function getGroupLabel(type: 'MU' | 'MF' | 'MUT' | 'MFT'): string {
    if (type === 'MF' || type === 'MFT') return 'Productos';
    if (type === 'MU' || type === 'MUT') return 'Muestras';
    return 'Otros';
}

function buildReference(dto: ProgrammerDto): string {
    const sampleRef = dto.product_sheet?.sample_reference?.trim() ?? '';
    const productRef = dto.product_sheet?.product_reference?.trim() ?? '';

    if (sampleRef && productRef) return sampleRef === productRef ? sampleRef : `${sampleRef} - ${productRef}`;
    if (sampleRef) return sampleRef;
    if (productRef) return productRef;
    return 'Sin referencia';
}

function parseSizes(sizes: unknown): string[] {
    if (!sizes) return [];
    if (Array.isArray(sizes)) return sizes.map(String);
    if (typeof sizes === 'string') {
        try {
            const parsed = JSON.parse(sizes);
            if (Array.isArray(parsed)) return parsed.map(String);
            if (typeof parsed === 'object') return Object.keys(parsed).filter((k) => (parsed as Record<string, any>)[k]);
        } catch {
            return sizes.split(',').map((s) => s.trim()).filter(Boolean);
        }
    }
    if (typeof sizes === 'object') return Object.keys(sizes as Record<string, boolean>).filter((k) => (sizes as Record<string, boolean>)[k]);
    return [];
}

const toNullableText = (value: unknown): string | null => {
    if (value === null || value === undefined) return null;
    const text = String(value).trim();
    return text !== '' ? text : null;
};

const readCatalogName = (value: unknown): string | null => {
    if (!value) return null;
    if (typeof value === 'string') return toNullableText(value);
    if (typeof value === 'object') return toNullableText((value as { name?: unknown }).name);
    return null;
};

// Map product-sheet DTOs
export function mapProductSheetDto(dto: ProductSheetDto): ProductSheet {
    return {
        id: (dto.id ?? 0) as number,
        type: dto.type as any,
        derived_from_product_sheet_id: (dto as any).derived_from_product_sheet_id ?? null,
        has_derived_product: (dto as any).has_derived_product ?? false,
        reference: (dto.reference as string) ?? String(dto.id ?? ''),
        sample_reference: (dto as any).sample_reference ?? null,
        product_reference: (dto as any).product_reference ?? null,
        display_reference: (dto as any).display_reference ?? null,
        value: (dto as any).value ?? dto.id,
        label: (dto as any).label ?? (dto as any).display_reference ?? dto.reference,
        section_id: (dto as any).section_id ?? (dto.section as any)?.id ?? 0,
        group_id: (dto as any).group_id ?? (dto.group as any)?.id ?? 0,
        brand_id: (dto as any).brand_id ?? (dto.brand as any)?.id ?? 0,
        world_id: (dto as any).world_id ?? (dto.world as any)?.id ?? 0,
        base_id: (dto as any).base_id ?? (dto as any).fabric_base_id,
        fabric_base_id: (dto as any).fabric_base_id ?? (dto as any).base_id,
        yarn_base_id: (dto as any).yarn_base_id ?? null,
        billing_price: (dto as any).billing_price as any,
        selling_price: (dto as any).selling_price as any,
        sizes: (dto as any).sizes,
        processes: (dto as any).processes ?? undefined,
        description: dto.description ?? undefined,
        section: (dto as any).section ?? undefined,
        group: (dto as any).group ?? undefined,
    } as ProductSheet;
}

export function mapProgrammerDto(dto: ProgrammerDto): Programmer {
    return {
        id: dto.id,
        op_code: dto.op_code ?? null,
        product_sheet_id: dto.product_sheet_id,
        product_sheet: dto.product_sheet ? mapProductSheetDto(dto.product_sheet) : undefined,
        season: (dto.season as any) ?? null,
        destination: (dto.destination as any) ?? undefined,
        destination_id: dto.destination_id ?? null,
        priority: dto.priority ?? null,
        isEcommerce: Boolean(dto.is_ecommerce ?? dto.isEcommerce ?? false),
        ecommerce_units: dto.ecommerce_units ?? null,
        units: dto.units ?? null,
        estimated_delivery_date: dto.estimated_delivery_date ?? null,
        status: (dto.status as any) ?? 'incomplete',
        is_pending_completion: dto.is_pending_completion ?? false,
        pending_fields: dto.pending_fields ?? [],
        created_at: dto.created_at ?? '',
        updated_at: dto.updated_at ?? '',
    } as Programmer;
}

// TechnicalDataSheet mappers
export function mapProgrammerToOption(dto: ProgrammerDto): OpOption {
    const opCode = dto.op_code ?? '';
    const reference = buildReference(dto);
    const type = extractOpType(dto.op_code);
    const name = `${opCode} - ${reference}`;
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

export function mapProgrammerToSelectOption(dto: ProgrammerDto): Option {
    const opCode = dto.op_code ?? '';
    const reference = buildReference(dto);
    const type = extractOpType(dto.op_code);
    const name = `${opCode} - ${reference}`;
    return { id: dto.id, name, groupLabel: getGroupLabel(type) } as unknown as Option;
}

export function mapOpBasicInfoDtoToOpBasicInfo(dto: OpBasicInfoDto): OpBasicInfo {
    const base = toNullableText(dto.base) ?? toNullableText(dto.fabricBase) ?? readCatalogName(dto.fabric_base) ?? null;
    const baseYarn = toNullableText(dto.baseYarn) ?? toNullableText(dto.yarnBase) ?? readCatalogName(dto.yarn_base) ?? null;

    return {
        id: dto.id,
        opCode: dto.opCode,
        reference: dto.reference,
        type: extractOpType(dto.opCode),
        status: 'N/A',
        season: dto.season ?? null,
        destination: dto.destination ?? null,
        units: dto.units ?? null,
        deliveryDate: dto.date ?? null,
        flatDrawingUrl: dto.flatDrawingUrl ?? null,
        section: dto.section ?? null,
        group: dto.group ?? null,
        world: dto.world ?? null,
        base,
        brand: dto.brand ?? null,
        sizes: parseSizes(dto.sizes),
        priceBilling: null,
        priceSale: null,
        ecommerce: false,
        priority: toNullableText(dto.priority),
        baseYarn,
    };
}

export function mapDetailResponseToBasicInfo(response: ProductionOrderDetailResponseDto): OpBasicInfo {
    const order = response.production_order;
    const sheet = response.product_sheet;
    const programmer = Array.isArray(response.programmers) ? response.programmers[0] : response.programmer;

    const opCode = order?.op_code ?? programmer?.op_code ?? '';
    const type = extractOpType(opCode);

    const sampleRef = sheet?.sample_reference?.trim() ?? '';
    const productRef = sheet?.product_reference?.trim() ?? '';
    let reference = 'Sin referencia';
    if (sampleRef && productRef) reference = sampleRef === productRef ? sampleRef : `${sampleRef} - ${productRef}`;
    else if (sampleRef) reference = sampleRef;
    else if (productRef) reference = productRef;

    const statusRaw = order?.status ?? programmer?.status ?? 'incomplete';
    const status = statusRaw === 'complete' ? 'Completo' : 'Pendiente';

    return {
        id: order?.id ?? programmer?.id ?? 0,
        opCode,
        reference,
        type,
        status,
        season: toNullableText(order?.season_display ?? order?.season ?? programmer?.season) ?? null,
        destination: order?.destination?.name ?? null,
        units: order?.units ?? programmer?.units ?? null,
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
        priority: toNullableText(order?.priority ?? programmer?.priority ?? null),
        baseYarn: sheet?.yarn_base?.name ?? null,
        estimatedDeliveryTime: null,
    };
}

// Product-sheet utilities (moved here to keep production domain together)
export const PRODUCT_SHEET_TYPE_PRODUCT = 'producto';
export const PRODUCT_SHEET_TYPE_SAMPLE = 'muestra';

export function normalizeProductSheetType(value?: string | null): string {
    if (!value) return PRODUCT_SHEET_TYPE_PRODUCT;
    const v = String(value).toLowerCase();
    if (v.includes('mues') || v === 'sample' || v === 'muestra') return PRODUCT_SHEET_TYPE_SAMPLE;
    return PRODUCT_SHEET_TYPE_PRODUCT;
}

export function formatCopCurrency(value?: number | null): string {
    const n = Number(value ?? 0);
    try {
        return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP' }).format(n);
    } catch {
        return String(n);
    }
}

export function resolveProductSheetReference(product: ProductSheet): string {
    if (product.reference) return product.reference;
    if (product.id) return `REF-${product.id}`;
    return '-';
}

export function parseBooleanRecord(record: Record<string, any> | string | null): Record<string, boolean> {
    if (!record) return {};
    if (typeof record === 'string') {
        try {
            const parsed = JSON.parse(record);
            return Object.fromEntries(Object.entries(parsed).map(([k, v]) => [k, Boolean(v)]));
        } catch {
            return {};
        }
    }
    return Object.fromEntries(Object.entries(record).map(([k, v]) => [k, Boolean(v)]));
}

export function stringifyJsonRecord(record: unknown, fallback: unknown = ''): string {
    if (record == null) {
        try {
            return typeof fallback === 'string' ? fallback : JSON.stringify(fallback);
        } catch {
            return String(fallback ?? '');
        }
    }
    if (typeof record === 'string') return record;
    try {
        return JSON.stringify(record);
    } catch {
        return String(record);
    }
}
