export type NormalizedSizes = string | Record<string, boolean> | null;

function toEnabledSizeRecord(input: unknown[]): Record<string, boolean> {
    return Object.fromEntries(input.map((value) => [String(value), true]));
}

function toBooleanRecord(input: Record<string, unknown>): Record<string, boolean> {
    return Object.fromEntries(
        Object.entries(input).map(([key, value]) => [key, Boolean(value)]),
    );
}

function extractSizeKeys(input: NormalizedSizes): string[] {
    if (input == null) return [];

    if (typeof input === 'string') {
        return input
            .split(',')
            .map((size) => size.trim())
            .filter(Boolean);
    }

    return Object.entries(input)
        .filter(([, enabled]) => enabled)
        .map(([size]) => size);
}

export function normalizeSizes(input: unknown): NormalizedSizes {
    if (input == null) return null;

    if (Array.isArray(input)) {
        return toEnabledSizeRecord(input);
    }

    if (typeof input === 'string') {
        const text = input.trim();
        if (text === '') return null;

        try {
            const parsed = JSON.parse(text) as unknown;
            if (Array.isArray(parsed)) return toEnabledSizeRecord(parsed);
            if (parsed && typeof parsed === 'object') return toBooleanRecord(parsed as Record<string, unknown>);
        } catch {
            return text;
        }

        return text;
    }

    if (typeof input === 'object') {
        return toBooleanRecord(input as Record<string, unknown>);
    }

    return null;
}

export function sizesToLabel(sizes: unknown): string {
    const normalized = normalizeSizes(sizes);
    const sizeKeys = extractSizeKeys(normalized);
    return sizeKeys.join(', ');
}
