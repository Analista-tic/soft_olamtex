import axios from 'axios';
import { defineStore } from 'pinia';

import type { Item } from '@/domain/MasterData';
import { mapItemsDto } from '@/domain/MasterData';

const DEFAULT_STATUS_FILTER = 'all' as const;
const DEFAULT_PER_PAGE = 10;

interface FetchItemsOptions {
    page?: number;
    search?: string;
    status?: typeof DEFAULT_STATUS_FILTER | boolean;
}

interface InventoryState {
    items: Item[];
    loading: boolean;
    total: number;
    currentPage: number;
    status: typeof DEFAULT_STATUS_FILTER | boolean;
    search: string;
}

export { DEFAULT_PER_PAGE, DEFAULT_STATUS_FILTER };
export type { Item };

export const useInventoryStore = defineStore('inventory', {
    state: (): InventoryState => ({
        items: [],
        loading: false,
        total: 0,
        currentPage: 1,
        status: DEFAULT_STATUS_FILTER,
        search: '',
    }),
    actions: {
        async fetchItems(options: FetchItemsOptions = {}): Promise<void> {
            const page = options.page ?? 1;
            const search = options.search ?? '';
            const status = options.status ?? this.status;

            this.loading = true;
            try {
                const params: Record<string, string | number | boolean> = {
                    page,
                    per_page: DEFAULT_PER_PAGE,
                    search,
                };

                if (status !== DEFAULT_STATUS_FILTER) {
                    params.status = status;
                }

                const response = await axios.get('/item/data', { params });
                const data = response.data;

                this.items = mapItemsDto(data.data ?? []);
                this.total = data.total ?? 0;
                this.currentPage = data.current_page ?? page;
                this.status = status;
                this.search = search;
            } finally {
                this.loading = false;
            }
        },
    },
});
