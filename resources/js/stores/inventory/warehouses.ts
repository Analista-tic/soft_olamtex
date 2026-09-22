import axios from 'axios';
import { defineStore } from 'pinia';

import type { Warehouse } from '@/domain/Inventory';

type WarehousesResponse = Warehouse[];

interface WarehousesState {
    items: Warehouse[];
    loading: boolean;
    loaded: boolean;
}

export const useWarehousesStore = defineStore('warehouses', {
    state: (): WarehousesState => ({
        items: [],
        loading: false,
        loaded: false,
    }),
    actions: {
        async fetchWarehouses(force = false): Promise<void> {
            if (!force && (this.loaded || this.loading)) return;
            this.loading = true;
            try {
                const response = await axios.get<WarehousesResponse>('/bodegas/list');
                this.items = response.data;
                this.loaded = true;
            } finally {
                this.loading = false;
            }
        },
        invalidate(): void {
            this.loaded = false;
        },
    },
});
