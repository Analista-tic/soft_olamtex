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

export const useRetentionsStore = defineStore('retentions', {
    state: (): RetentionsState => ({
        items: [],
        loading: false,
        loaded: false,
    }),
    actions: {
        async fetchRetentions(): Promise<void> {
            if (this.loaded || this.loading) return;
            this.loading = true;
            try {
                const response = await axios.get<RetentionsResponse>('/retenciones/list');
                this.items = Array.isArray(response.data) ? response.data.map(mapRetentionDto) : [];
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
