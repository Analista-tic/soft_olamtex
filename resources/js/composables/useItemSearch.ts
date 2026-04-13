import axios from 'axios';
import { ref } from 'vue';

export type ItemSearchOption = {
    id: number;
    name: string;
    reference: string | null;
    label: string;
    unit_measure: string | null;
    item_type: string;
    img_url: string | null;
};

type UseItemSearchOptions = {
    group?: string;
    debounceMs?: number;
    initialLimit?: number;
};

export function useItemSearch(opts: UseItemSearchOptions = {}) {
    const { group, debounceMs = 300, initialLimit = 20 } = opts;

    const options = ref<ItemSearchOption[]>([]);
    const isLoading = ref(false);
    const query = ref('');

    // Caché simple en memoria: evita repetir la misma búsqueda
    const cache = new Map<string, ItemSearchOption[]>();

    let debounceTimer: ReturnType<typeof setTimeout> | null = null;

    async function fetchOptions(q: string): Promise<void> {
        const cacheKey = `${group ?? ''}::${q}`;
        if (cache.has(cacheKey)) {
            options.value = cache.get(cacheKey)!;
            return;
        }

        isLoading.value = true;
        try {
            const params: Record<string, string | number> = {
                q,
                limit: q === '' ? initialLimit : 30,
            };
            if (group) params.group = group;

            const response = await axios.get('/item/search', { params });
            const result = Array.isArray(response.data) ? response.data : [];
            cache.set(cacheKey, result);
            options.value = result;
        } catch (err) {
            console.error('useItemSearch: error al buscar ítems', err);
            options.value = [];
        } finally {
            isLoading.value = false;
        }
    }

    function search(q: string): void {
        query.value = q;
        if (debounceTimer) clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => fetchOptions(q), debounceMs);
    }

    function loadInitial(): void {
        fetchOptions('');
    }

    function clearCache(): void {
        cache.clear();
    }

    return { options, isLoading, query, search, loadInitial, clearCache };
}
