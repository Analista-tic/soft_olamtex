const DEFAULT_LOCALE = 'es-CO';

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
