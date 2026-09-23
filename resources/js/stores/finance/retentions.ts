import axios from 'axios';
import { defineStore } from 'pinia';

import type { RetentionDto } from '@/domain/Finance/dto';
import { mapRetentionDto } from '@/domain/Finance/mappers';
import type { Retention } from '@/domain/Finance/types';

type RetentionsResponse = RetentionDto[];

interface RetentionsState {
    items: Retention[];
    loading: boolean;
    loaded: boolean;
}

// Token de la última llamada en curso: si dos fetch se solapan (uno normal + uno
// forzado casi al mismo tiempo), descarta la respuesta que ya no es la más reciente
// en vez de dejar que un dato viejo pise a uno más nuevo.
let requestToken = 0;

export const useRetentionsStore = defineStore('retentions', {
    state: (): RetentionsState => ({
        items: [],
        loading: false,
        loaded: false,
    }),
    actions: {
        async fetchRetentions(force = false): Promise<void> {
            if (!force && (this.loaded || this.loading)) return;
            const token = ++requestToken;
            this.loading = true;
            try {
                const response = await axios.get<RetentionsResponse>('/retenciones/list');
                if (token !== requestToken) return;
                this.items = Array.isArray(response.data) ? response.data.map(mapRetentionDto) : [];
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
