export function normalizeSizes(input: unknown): string[] {
    if (!input) return [];
    if (Array.isArray(input)) return input.map(String);
    if (typeof input === 'string') {
        try {
            const parsed = JSON.parse(input);
            if (Array.isArray(parsed)) return parsed.map(String);
            if (typeof parsed === 'object' && parsed !== null) return Object.keys(parsed).filter((k) => (parsed as Record<string, any>)[k]);
        } catch {
            return input.split(',').map((s) => s.trim()).filter(Boolean);
        }
    }
    if (typeof input === 'object') return Object.keys(input as Record<string, any>).filter((k) => (input as Record<string, any>)[k]);
    return [];
}

export function sizesToLabel(sizes: unknown): string {
    const normalized = normalizeSizes(sizes);
    if (!normalized || normalized.length === 0) return '';
    return normalized.join(', ');
}
