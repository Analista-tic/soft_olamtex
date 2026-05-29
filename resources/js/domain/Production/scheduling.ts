export type PriorityLetter = 'A' | 'B' | 'C' | 'D';

const MONTH_MAP: Record<string, number> = {
    ENERO: 1,
    FEBRERO: 2,
    MARZO: 3,
    ABRIL: 4,
    MAYO: 5,
    JUNIO: 6,
    JULIO: 7,
    AGOSTO: 8,
    SEPTIEMBRE: 9,
    OCTUBRE: 10,
    NOVIEMBRE: 11,
    DICIEMBRE: 12,
};

export function normalizePriorityLetter(value: unknown): PriorityLetter | null {
    if (value === null || value === undefined) return null;

    const normalized = String(value).trim().toUpperCase();
    if (normalized === 'A' || normalized === 'B' || normalized === 'C' || normalized === 'D') {
        return normalized;
    }

    return null;
}

export function parseSeasonMonthNumber(season: unknown): number | null {
    if (season === null || season === undefined) return null;

    const normalized = String(season).trim().toUpperCase();
    if (!normalized) return null;

    for (const [monthName, monthNumber] of Object.entries(MONTH_MAP)) {
        if (normalized.includes(monthName)) return monthNumber;
    }

    const asNum = Number(normalized);
    if (Number.isFinite(asNum) && asNum >= 1 && asNum <= 12) {
        return Math.floor(asNum);
    }

    return null;
}

export function parseSeasonYearNumber(season: unknown): number | null {
    if (season === null || season === undefined) return null;

    const normalized = String(season).trim().toUpperCase();
    if (!normalized) return null;

    const match = normalized.match(/(19|20)\d{2}/);
    if (!match) return null;

    const year = Number(match[0]);
    return Number.isFinite(year) ? year : null;
}

export function calculateEstimatedDeliveryDateByPriority(
    season: unknown,
    priority: unknown,
    referenceDate: Date = new Date(),
): string | null {
    const monthNum = parseSeasonMonthNumber(season);
    const normalizedPriority = normalizePriorityLetter(priority);

    if (monthNum === null || !normalizedPriority) return null;

    const explicitYear = parseSeasonYearNumber(season);
    let targetYear = explicitYear ?? referenceDate.getFullYear();

    if (!explicitYear && monthNum < referenceDate.getMonth() + 1) {
        targetYear += 1;
    }

    const finalDate =
        normalizedPriority === 'A'
            ? new Date(targetYear, monthNum - 2, 20)
            : normalizedPriority === 'B'
              ? new Date(targetYear, monthNum - 2, 27)
              : normalizedPriority === 'C'
                ? new Date(targetYear, monthNum - 1, 3)
                : new Date(targetYear, monthNum - 1, 10);

    const yyyy = finalDate.getFullYear();
    const mm = String(finalDate.getMonth() + 1).padStart(2, '0');
    const dd = String(finalDate.getDate()).padStart(2, '0');

    return `${yyyy}-${mm}-${dd}`;
}
