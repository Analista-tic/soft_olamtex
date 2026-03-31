import { format } from 'date-fns';

type DateInput = Date | string | null | undefined;

function buildDate(year: number, month: number, day: number): Date | null {
    const parsed = new Date(year, month - 1, day);
    if (Number.isNaN(parsed.getTime()) || parsed.getFullYear() !== year || parsed.getMonth() !== month - 1 || parsed.getDate() !== day) {
        return null;
    }
    return parsed;
}

export function parseToDate(value: DateInput): Date | null {
    if (!value) return null;
    if (value instanceof Date) {
        return Number.isNaN(value.getTime()) ? null : new Date(value.getFullYear(), value.getMonth(), value.getDate());
    }
    if (typeof value !== 'string') return null;

    const trimmed = value.trim();
    if (!trimmed) return null;

    const datePart = trimmed.includes('T') ? trimmed.split('T')[0] : trimmed.includes(' ') ? trimmed.split(' ')[0] : trimmed;
    const directMatch = /^(\d{4})-(\d{2})-(\d{2})$/.exec(datePart);
    if (directMatch) {
        const year = Number(directMatch[1]);
        const month = Number(directMatch[2]);
        const day = Number(directMatch[3]);
        return buildDate(year, month, day);
    }

    const parsed = new Date(trimmed);
    if (Number.isNaN(parsed.getTime())) return null;
    return new Date(parsed.getFullYear(), parsed.getMonth(), parsed.getDate());
}

export function toIsoDateString(value: DateInput): string | null {
    const parsed = parseToDate(value);
    if (!parsed) return null;
    const year = parsed.getFullYear();
    const month = String(parsed.getMonth() + 1).padStart(2, '0');
    const day = String(parsed.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

export function formatDateDDMMYYYY(value: DateInput, fallback = '-'): string {
    const parsed = parseToDate(value);
    if (!parsed) return fallback;
    return format(parsed, 'dd/MM/yyyy');
}
