import axios from 'axios';
import type { Contact } from '@/domain/MasterData';
import { defineStore } from 'pinia';

type ContactsResponse = Contact[];

interface ContactsState {
    items: Contact[];
    loading: boolean;
    loaded: boolean;
}

// Token de la última llamada en curso: si dos fetch se solapan (uno normal + uno
// forzado casi al mismo tiempo), descarta la respuesta que ya no es la más reciente
// en vez de dejar que un dato viejo pise a uno más nuevo.
let requestToken = 0;

export const useContactsStore = defineStore('contacts', {
    state: (): ContactsState => ({
        items: [],
        loading: false,
        loaded: false,
    }),
    actions: {
        async fetchContacts(force = false): Promise<void> {
            if (!force && (this.loaded || this.loading)) return;
            const token = ++requestToken;
            this.loading = true;
            try {
                const response = await axios.get<ContactsResponse>('/contactos/list');
                if (token !== requestToken) return;
                this.items = response.data;
                this.loaded = true;
            } catch (error) {
                if (token !== requestToken) return;
                console.error('Error cargando contactos:', error);
            } finally {
                if (token === requestToken) this.loading = false;
            }
        },
        invalidate(): void {
            this.loaded = false;
        },
    },
});
