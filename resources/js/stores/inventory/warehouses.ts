import axios from 'axios';
import { defineStore } from 'pinia';

import type { Warehouse } from '@/domain/Inventory';

type WarehousesResponse = Warehouse[];

interface WarehousesState {
    items: Warehouse[];
    loading: boolean;
    loaded: boolean;
}

// Token de la última llamada en curso: si dos fetch se solapan (uno normal + uno
// forzado casi al mismo tiempo), descarta la respuesta que ya no es la más reciente
// en vez de dejar que un dato viejo pise a uno más nuevo.
let requestToken = 0;

export const useWarehousesStore = defineStore('warehouses', {
    state: (): WarehousesState => ({
        items: [],
        loading: false,
        loaded: false,
    }),
    actions: {
        async fetchWarehouses(force = false): Promise<void> {
            if (!force && (this.loaded || this.loading)) return;
            const token = ++requestToken;
            this.loading = true;
            try {
                const response = await axios.get<WarehousesResponse>('/bodegas/list');
                if (token !== requestToken) return;
                this.items = response.data;
                this.loaded = true;
            } finally {
                if (token === requestToken) this.loading = false;
            }
        },
        invalidate(): void {
            this.loaded = false;
        },
    },
});
