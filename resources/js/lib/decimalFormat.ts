/**
 * Formato numérico de la interfaz (es-CO): coma como separador decimal.
 * El almacenamiento y la API usan siempre punto (`50.25`); la coma es solo presentación.
 */

/** Convierte texto con coma o punto decimal (`"50,25"`, `"50.25"`) a número; `null` si no es válido. */
export function parseDecimalInput(value: string | number | null | undefined): number | null {
    if (value === null || value === undefined) return null;
    if (typeof value === 'number') return Number.isFinite(value) ? value : null;
    const text = value.trim().replace(',', '.');
    if (text === '') return null;
    const num = Number(text);
    return Number.isFinite(num) ? num : null;
}

/** Formatea para mostrar en un campo: `"50.000"` → `"50,00"`. Vacío si no hay valor. */
export function formatDecimalInput(value: string | number | null | undefined, decimals = 2): string {
    const num = parseDecimalInput(value);
    if (num === null) return value === null || value === undefined ? '' : String(value);
    return num.toFixed(decimals).replace('.', ',');
}

/** Convierte lo escrito por el usuario al formato de la API (punto decimal). */
export function toApiDecimal(value: string | number | null | undefined): string {
    const num = parseDecimalInput(value);
    if (num === null) return value === null || value === undefined ? '' : String(value).trim();
    return String(num);
}
