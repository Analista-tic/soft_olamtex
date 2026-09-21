const DEFAULT_LOCALE = 'es-CO';

export type ChipCaseMode = 'preserve' | 'title-case';

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

export function normalizeChipKey(value: unknown, locale = DEFAULT_LOCALE): string {
    return normalizeChipDisplay(value)
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLocaleLowerCase(locale);
}

export function appendUniqueChip(list: string[], rawValue: unknown, locale = DEFAULT_LOCALE, caseMode: ChipCaseMode = 'preserve'): string[] {
    const candidate = caseMode === 'title-case'
        ? normalizeChipTitleCaseDisplay(rawValue, locale)
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
export function normalizeUpperCaseWords(value: unknown, locale = DEFAULT_LOCALE): string {
    return normalizeChipDisplay(value).toLocaleUpperCase(locale);
}

// ── Reglas de guardado por tipo de dato ─────────────────────────────
// Almacenamiento ≠ presentación: estas reglas dejan el dato semánticamente correcto;
// el formato visual (mayúsculas, etc.) se aplica solo con CSS/helpers de presentación.
// Mantener en paridad con app/Support/TextNormalizer.php.

const NAME_PARTICLES = new Set(['de', 'del', 'la', 'las', 'los', 'y', 'e', 'da', 'do', 'dos', 'van', 'von']);

/** Email: sin espacios y en minúsculas. */
export function normalizeEmail(value: unknown, locale = DEFAULT_LOCALE): string {
    return String(value ?? '')
        .replace(/\s+/g, '')
        .toLocaleLowerCase(locale);
}

/** Código (SKU, referencia, NIT alfanumérico): espacios normalizados y en mayúsculas. */
export function normalizeCode(value: unknown, locale = DEFAULT_LOCALE): string {
    return normalizeUpperCaseWords(value, locale);
}

/** Nombre de catálogo controlado (subgrupos, operaciones, máquinas): en mayúsculas. */
export function normalizeCatalogName(value: unknown, locale = DEFAULT_LOCALE): string {
    return normalizeUpperCaseWords(value, locale);
}

/**
 * Nombre propio: conserva tildes y la capitalización escrita por la persona.
 * Solo si viene todo en mayúsculas o todo en minúsculas lo pasa a Title Case
 * (con partículas "de", "del", "la"… en minúscula).
 */
export function normalizePersonName(value: unknown, locale = DEFAULT_LOCALE): string {
    const text = normalizeChipDisplay(value);

    if (!text) {
        return '';
    }

    const hasUpper = text !== text.toLocaleLowerCase(locale);
    const hasLower = text !== text.toLocaleUpperCase(locale);

    if (hasUpper && hasLower) {
        return text;
    }

    return text
        .toLocaleLowerCase(locale)
        .split(' ')
        .map((word, index) =>
            index > 0 && NAME_PARTICLES.has(word) ? word : word.replace(/(^|[-'’])(\p{L})/gu, (_, sep: string, letter: string) => sep + letter.toLocaleUpperCase(locale)),
        )
        .join(' ');
}

/**
 * Solo presentación: muestra un nombre de persona natural con capitalización legible
 * cuando el dato guardado viene todo en mayúsculas o todo en minúsculas. No altera lo guardado.
 * Solo aplica si el tipo es explícitamente 'persona_natural'; personas jurídicas (razón social,
 * siglas como "S.A.S") o tipo desconocido se muestran tal cual, sin adivinar.
 */
export function formatPersonNameDisplay(value: unknown, personType?: string | null, locale = DEFAULT_LOCALE): string {
    const raw = String(value ?? '');

    if (String(personType ?? '').toLowerCase() !== 'persona_natural') {
        return raw;
    }

    return normalizePersonName(raw, locale) || raw;
}

/** Texto libre (notas, descripciones, direcciones): intacto, solo recorta extremos. */
export function normalizeFreeText(value: unknown): string {
    return String(value ?? '').trim();
}

/** Texto libre opcional: como `normalizeFreeText`, pero vacío → null (para payloads). */
export function normalizeOptionalText(value: unknown): string | null {
    const text = normalizeFreeText(value);
    return text === '' ? null : text;
}

/** Solo presentación: primera letra en mayúscula, el resto intacto. */
export function capitalizeFirst(value: unknown, locale = DEFAULT_LOCALE): string {
    const text = String(value ?? '');
    return text.charAt(0).toLocaleUpperCase(locale) + text.slice(1);
}

        .toLocaleLowerCase(locale)
        .replace(/(^|\s)(\p{L})/gu, (_, separator: string, letter: string) => separator + letter.toLocaleUpperCase(locale));
}
