export function getAllowedFabricItemIds(opId: string | number | null | undefined): number[] {
    if (typeof window === 'undefined' || opId === null || opId === undefined || opId === '') return [];
    try {
        const raw = window.localStorage.getItem(`tDS_rawMaterial_${opId}`);
        if (!raw) return [];
        const parsed = JSON.parse(raw) as Array<Record<string, unknown>>;
        if (!Array.isArray(parsed)) return [];
        const ids = parsed
            .map((row) => {
                const val = row['telaItemId'] ?? row['tela_item_id'] ?? row['telaItemId'];
                const num = Number(val);
                return Number.isFinite(num) && num > 0 ? num : null;
            })
            .filter((n): n is number => n !== null);
        return Array.from(new Set(ids));
    } catch {
        return [];
    }
}
