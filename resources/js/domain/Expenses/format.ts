import { formatDateDDMMYYYY, formatDateTimeDDMMYYYY } from '@/lib/date';

const currencyFormatter = new Intl.NumberFormat('es-CO', {
    style: 'currency',
    currency: 'COP',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
});

/** Moneda COP sin decimales; devuelve `fallback` si el valor no es numérico. */
export function formatCurrency(value?: number | string | null, fallback = '-'): string {
    const amount = Number(value ?? 0);
    return Number.isFinite(amount) ? currencyFormatter.format(amount) : fallback;
}

const quantityFormatter = new Intl.NumberFormat('es-CO', { maximumFractionDigits: 2 });

/** Cantidades de compra/recepción: máximo 2 decimales. */
export function formatQuantity(value?: number | string | null, fallback = '-'): string {
    const amount = Number(value ?? 0);
    return Number.isFinite(amount) ? quantityFormatter.format(amount) : fallback;
}

/** Fecha dd/mm/aaaa sin desfase de zona horaria. */
export function formatDate(value?: string | null, fallback = '-'): string {
    return formatDateDDMMYYYY(value, fallback);
}

/** Fecha y hora local (dd/mm/aaaa hh:mm a. m./p. m.) para timestamps como la recepción. */
export function formatDateTime(value?: string | null, fallback = '-'): string {
    return formatDateTimeDDMMYYYY(value, fallback);
}
