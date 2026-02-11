export type SizesValue = string | string[] | Record<string, boolean> | null | undefined;

function mapArrayToRecord(value: string[]): Record<string, boolean> | null {
    const record: Record<string, boolean> = {};

    for (const size of value) {
        const key = String(size ?? '').trim();
        if (key !== '') {
            record[key] = true;
        }
    }

    return Object.keys(record).length > 0 ? record : null;
}

export function normalizeSizes(value: SizesValue): string | Record<string, boolean> | null {
    if (value === null || value === undefined) {
        return null;
    }

    if (Array.isArray(value)) {
        return mapArrayToRecord(value.map((size) => String(size)));
    }

    if (typeof value === 'object') {
        return value;
    }

    const text = value.trim();
    if (text === '' || text === '{}') {
        return null;
    }

    try {
        const parsed = JSON.parse(text) as unknown;

        if (Array.isArray(parsed)) {
            const list = parsed.map((size) => String(size));
            return mapArrayToRecord(list);
        }

        if (parsed && typeof parsed === 'object') {
            return parsed as Record<string, boolean>;
        }
    } catch {
        return text;
    }

    return text;
}

export function sizesToList(value: SizesValue): string[] {
    const normalized = normalizeSizes(value);
    if (!normalized) {
        return [];
    }

    if (typeof normalized === 'string') {
        return normalized
            .split(',')
            .map((size) => size.trim())
            .filter((size) => size !== '');
    }

    return Object.entries(normalized)
        .filter(([, enabled]) => Boolean(enabled))
        .map(([size]) => size);
}

export function sizesToLabel(value: SizesValue, emptyLabel = 'Sin tallas'): string {
    const sizes = sizesToList(value);
    return sizes.length > 0 ? sizes.join(', ') : emptyLabel;
}
