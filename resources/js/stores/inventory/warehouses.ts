import axios from 'axios';
import { defineStore } from 'pinia';

import type { Warehouse } from '@/domain/Inventory';
import { broadcastCatalogInvalidated, onCatalogInvalidatedElsewhere } from '@/lib/crossTabSync';

type WarehousesResponse = Warehouse[];

interface WarehousesState {
    items: Warehouse[];
    loading: boolean;
    loaded: boolean;
}

const CATALOG_KEY = 'warehouses';

// Token de la última llamada en curso: si dos fetch se solapan (uno normal + uno
// forzado casi al mismo tiempo), descarta la respuesta que ya no es la más reciente
// en vez de dejar que un dato viejo pise a uno más nuevo.
let requestToken = 0;

// Se registra una sola vez (no por instancia del store, que en Pinia es un singleton
// por pestaña de todos modos): si otra pestaña crea/edita una bodega, esta la
// refresca sola en vez de esperar a que el usuario recargue la página.
let crossTabSubscribed = false;

export const useWarehousesStore = defineStore('warehouses', {
    state: (): WarehousesState => ({
        items: [],
        loading: false,
        loaded: false,
    }),
    actions: {
        async fetchWarehouses(force = false): Promise<void> {
            if (!crossTabSubscribed) {
                crossTabSubscribed = true;
                onCatalogInvalidatedElsewhere(CATALOG_KEY, () => {
                    this.loaded = false;
                    if (this.items.length > 0) void this.fetchWarehouses(true);
                });
            }

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
            broadcastCatalogInvalidated(CATALOG_KEY);
        },
    },
});
