export type { FabricOrder, FabricOrderItem, FabricRoll, FabricRollReceptionForm, FabricRollView } from './types';

export type { FabricOrderDto, FabricOrderItemDto, FabricRollDto, FabricRollViewDto } from './dto';

export { mapFabricOrderDto, mapFabricOrderItemDto, mapFabricRollDto, mapFabricRollViewDto } from './mappers';

type UnitValue = string | number | null | undefined;

function extractNumericToken(raw: string): string {
    const match = raw.match(/-?[\d.,]+/);
    return match ? match[0] : '';
}

function normalizeNumericToken(token: string): string {
    const clean = token.replace(/\s+/g, '').replace(/\u00A0/g, '');
    if (!clean) return '';

    const hasDot = clean.includes('.');
    const hasComma = clean.includes(',');

    if (hasDot && hasComma) {
        const lastDot = clean.lastIndexOf('.');
        const lastComma = clean.lastIndexOf(',');
        const decimalSeparator = lastDot > lastComma ? '.' : ',';
        const thousandsSeparator = decimalSeparator === '.' ? ',' : '.';

        return clean.replace(new RegExp(`\\${thousandsSeparator}`, 'g'), '').replace(decimalSeparator, '.');
    }

    if (hasComma) {
        const commas = clean.match(/,/g)?.length ?? 0;
        if (commas > 1) return clean.replace(/,/g, '');

        const [, decimals = ''] = clean.split(',');
        if (decimals.length === 3) return clean.replace(',', '');

        return clean.replace(',', '.');
    }

    if (hasDot) {
        const dots = clean.match(/\./g)?.length ?? 0;
        if (dots > 1) {
            const lastDot = clean.lastIndexOf('.');
            const integerPart = clean.slice(0, lastDot).replace(/\./g, '');
            const decimalPart = clean.slice(lastDot + 1);
            return `${integerPart}.${decimalPart}`;
        }
    }

    return clean;
}

export function toUnitNumber(value: UnitValue): number | null {
    if (value === null || value === undefined) return null;
    if (typeof value === 'number') return Number.isFinite(value) ? value : null;

    const text = String(value).trim();
    if (!text) return null;

    const token = extractNumericToken(text);
    if (!token) return null;

    const parsed = Number(normalizeNumericToken(token));
    return Number.isFinite(parsed) ? parsed : null;
}

export function formatNumberEsCO(
    value: UnitValue,
    options: { maximumFractionDigits?: number; minimumFractionDigits?: number; fallback?: string } = {},
): string {
    const { maximumFractionDigits = 1, minimumFractionDigits = 0, fallback = '-' } = options;

    const parsed = toUnitNumber(value);
    if (parsed === null) return fallback;

    return new Intl.NumberFormat('es-CO', {
        minimumFractionDigits,
        maximumFractionDigits,
    }).format(parsed);
}

export function formatWidthToMeters(value: UnitValue, fallback = '-'): string {
    const parsed = toUnitNumber(value);
    if (parsed === null) return fallback;

    const raw = String(value ?? '').toLowerCase();
    const hasCm = /\bcm\b/.test(raw);
    const hasMeters = /(^|[^a-z])m($|[^a-z])/i.test(raw);

    let widthMeters = parsed;
    if (hasCm) {
        widthMeters = parsed / 100;
    } else if (!hasMeters && Math.abs(parsed) > 10) {
        widthMeters = parsed / 100;
    }

    return `${formatNumberEsCO(widthMeters, { maximumFractionDigits: 1, fallback })} m`;
}

export function formatMeters(value: UnitValue, fallback = '-'): string {
    const parsed = toUnitNumber(value);
    if (parsed === null) return fallback;

    const raw = String(value ?? '').toLowerCase();
    const normalized = /\bcm\b/.test(raw) ? parsed / 100 : parsed;

    return `${formatNumberEsCO(normalized, { maximumFractionDigits: 2, fallback })} m`;
}
