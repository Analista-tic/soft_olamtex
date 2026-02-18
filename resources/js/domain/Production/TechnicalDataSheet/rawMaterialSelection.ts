type StoredRawMaterialRow = {
    telaItemId?: number | string | null;
    itemId?: number | string | null;
};

function storageKey(opId: string | number): string {
    return `tDS_rawMaterial_${opId}`;
}

function toPositiveInt(value: unknown): number | null {
    const parsed = Number(value);
    if (!Number.isFinite(parsed)) return null;
    const rounded = Math.trunc(parsed);
    return rounded > 0 ? rounded : null;
}

export function getAllowedFabricItemIds(opId: string | number): number[] {
    if (typeof window === 'undefined') return [];

    try {
        const raw = window.localStorage.getItem(storageKey(opId));
        if (!raw) return [];

        const parsed = JSON.parse(raw) as StoredRawMaterialRow[];
        if (!Array.isArray(parsed)) return [];

        const ids = parsed
            .map((row) => toPositiveInt(row?.telaItemId ?? row?.itemId ?? null))
            .filter((value): value is number => value !== null);

        return Array.from(new Set(ids));
    } catch (error) {
        console.error('Error al leer seleccion de materia prima desde localStorage:', error);
        return [];
    }
}
