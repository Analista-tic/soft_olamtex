/**
 * Mapper centralizado para meses en español.
 * Convierte números 1-12 a nombres de meses.
 */

export const MONTH_LABELS: Record<number, string> = {
    1: 'ENERO',
    2: 'FEBRERO',
    3: 'MARZO',
    4: 'ABRIL',
    5: 'MAYO',
    6: 'JUNIO',
    7: 'JULIO',
    8: 'AGOSTO',
    9: 'SEPTIEMBRE',
    10: 'OCTUBRE',
    11: 'NOVIEMBRE',
    12: 'DICIEMBRE',
};

/**
 * Retorna el nombre del mes en español dado un valor numérico (1-12).
 * Soporta number, string numérico, o null/undefined.
 *
 * @param value - Número o string representando el mes (1-12)
 * @returns Nombre del mes en español o '—' si no es válido
 *
 * @example
 * monthLabel(2)      // 'FEBRERO'
 * monthLabel('3')    // 'MARZO'
 * monthLabel(null)   // '—'
 * monthLabel(13)     // '—'
 * monthLabel('MAYO') // 'MAYO' (ya es texto, lo retorna)
 */
export function monthLabel(value: number | string | null | undefined): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    // Si ya es texto de mes válido, retornarlo directamente
    if (typeof value === 'string') {
        const upperValue = value.toUpperCase().trim();
        const isMonthName = Object.values(MONTH_LABELS).includes(upperValue);
        if (isMonthName) {
            return upperValue;
        }

        const monthYearMatch = upperValue.match(/^([A-ZÁÉÍÓÚÑ]+)\s+(\d{4})$/);
        if (monthYearMatch) {
            const monthName = monthYearMatch[1];
            const year = monthYearMatch[2];
            const validMonthName = Object.values(MONTH_LABELS).includes(monthName);
            if (validMonthName) {
                return `${monthName} ${year}`;
            }
        }
    }

    // Convertir a número
    const monthNumber = typeof value === 'string' ? parseInt(value, 10) : value;

    if (!Number.isFinite(monthNumber) || monthNumber < 1 || monthNumber > 12) {
        return '—';
    }

    return MONTH_LABELS[monthNumber] ?? '—';
}
