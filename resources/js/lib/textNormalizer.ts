const DEFAULT_LOCALE = 'es-CO';

export type ChipCaseMode = 'preserve' | 'title-case' | 'upper-case';

export function normalizeChipDisplay(value: unknown): string {
    return String(value ?? '')
        .trim()
        .replace(/\s+/g, ' ');
}

export function normalizeChipTitleCaseDisplay(value: unknown, locale = DEFAULT_LOCALE): string {
    const normalized = normalizeChipDisplay(value);

    if (!normalized) {
        return '';
    }

    return normalizeTitleCaseWords(normalized, locale);
}

export function normalizeChipUpperCaseDisplay(value: unknown, locale = DEFAULT_LOCALE): string {
    const normalized = normalizeChipDisplay(value);

    if (!normalized) {
        return '';
    }

    return normalizeUpperCaseWords(normalized, locale);
}

export function normalizeChipKey(value: unknown, locale = DEFAULT_LOCALE): string {
    return normalizeChipDisplay(value)
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLocaleLowerCase(locale);
}

export function appendUniqueChip(list: string[], rawValue: unknown, locale = DEFAULT_LOCALE, caseMode: ChipCaseMode = 'preserve'): string[] {
    const candidate =
        caseMode === 'title-case'
            ? normalizeChipTitleCaseDisplay(rawValue, locale)
            : caseMode === 'upper-case'
              ? normalizeChipUpperCaseDisplay(rawValue, locale)
              : normalizeChipDisplay(rawValue);

    if (!candidate) {
        return list;
    }

    const candidateKey = normalizeChipKey(candidate, locale);
    const exists = list.some((item) => normalizeChipKey(item, locale) === candidateKey);

    return exists ? list : [...list, candidate];
}

export function normalizeUniqueChipList(values: Iterable<unknown>, locale = DEFAULT_LOCALE, caseMode: ChipCaseMode = 'preserve'): string[] {
    let normalized: string[] = [];

    for (const value of values) {
        normalized = appendUniqueChip(normalized, value, locale, caseMode);
    }

    return normalized;
}

export function normalizeTitleCaseWords(value: unknown, locale = DEFAULT_LOCALE): string {
    const text = String(value ?? '')
        .trim()
        .replace(/\s+/g, ' ');

    if (!text) {
        return '';
    }

    return text
        .toLocaleLowerCase(locale)
        .replace(/(^|\s)(\p{L})/gu, (_, separator: string, letter: string) => separator + letter.toLocaleUpperCase(locale));
}

export function normalizeUpperCaseWords(value: unknown, locale = DEFAULT_LOCALE): string {
    const text = String(value ?? '')
        .trim()
        .replace(/\s+/g, ' ');

    if (!text) {
        return '';
    }

    return text.toLocaleUpperCase(locale);
}
