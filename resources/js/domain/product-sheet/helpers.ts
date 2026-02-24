import type { ProductSheet } from './types';

export const PRODUCT_SHEET_TYPE_PRODUCT = 'producto';
export const PRODUCT_SHEET_TYPE_SAMPLE = 'muestra';

export function normalizeProductSheetType(value?: string | null): 'producto' | 'muestra' {
    const normalized = String(value ?? '')
        .trim()
        .toLowerCase();

    if (normalized === 'sample' || normalized === 'muestra') {
        return PRODUCT_SHEET_TYPE_SAMPLE;
    }

    if (normalized === 'product' || normalized === 'producto') {
        return PRODUCT_SHEET_TYPE_PRODUCT;
    }

    return PRODUCT_SHEET_TYPE_PRODUCT;
}

export function parseBooleanRecord(value: unknown): Record<string, boolean> {
    if (!value) return {};

    const mapArrayToRecord = (input: unknown[]): Record<string, boolean> => Object.fromEntries(input.map((size) => [String(size), true]));

    if (typeof value === 'string') {
        try {
            const parsed = JSON.parse(value);
            if (Array.isArray(parsed)) {
                return mapArrayToRecord(parsed);
            }
            if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
                return parsed as Record<string, boolean>;
            }
        } catch {
            return {};
        }

        return {};
    }

    if (Array.isArray(value)) {
        return mapArrayToRecord(value);
    }

    if (typeof value === 'object' && !Array.isArray(value)) {
        return value as Record<string, boolean>;
    }

    return {};
}

export function stringifyJsonRecord(value: unknown, fallback: Record<string, unknown> = {}): string {
    if (typeof value === 'string') {
        return value;
    }

    if (value && typeof value === 'object') {
        return JSON.stringify(value);
    }

    return JSON.stringify(fallback);
}

export function resolveProductSheetReference(sheet?: Partial<ProductSheet> | null): string {
    if (!sheet) return '';

    const type = normalizeProductSheetType(sheet.type);
    const sampleReference = String(sheet.sample_reference ?? '').trim();
    const productReference = String(sheet.product_reference ?? '').trim();
    const displayReference = String(sheet.display_reference ?? '').trim();
    const genericReference = String(sheet.reference ?? '').trim();

    if (type === PRODUCT_SHEET_TYPE_SAMPLE && sampleReference !== '') {
        return sampleReference;
    }

    if (type === PRODUCT_SHEET_TYPE_PRODUCT && productReference !== '') {
        return productReference;
    }

    return sampleReference || productReference || displayReference || genericReference;
}

export function formatCopCurrency(value: number | string | null | undefined): string {
    const numericValue = Number(value ?? 0);
    const safeValue = Number.isFinite(numericValue) ? numericValue : 0;

    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(safeValue);
}
