import axios from 'axios';
import type { Contact } from '@/domain/MasterData';
import { broadcastCatalogInvalidated, onCatalogInvalidatedElsewhere } from '@/lib/crossTabSync';
import { defineStore } from 'pinia';

type ContactsResponse = Contact[];

interface ContactsState {
    items: Contact[];
    loading: boolean;
    loaded: boolean;
}

const CATALOG_KEY = 'contacts';

// Token de la última llamada en curso: si dos fetch se solapan (uno normal + uno
// forzado casi al mismo tiempo), descarta la respuesta que ya no es la más reciente
// en vez de dejar que un dato viejo pise a uno más nuevo.
let requestToken = 0;

// Se registra una sola vez (no por instancia del store, que en Pinia es un singleton
// por pestaña de todos modos): si otra pestaña crea/edita un contacto, esta lo
// refresca solo en vez de esperar a que el usuario recargue la página.
let crossTabSubscribed = false;

export const useContactsStore = defineStore('contacts', {
    state: (): ContactsState => ({
        items: [],
        loading: false,
        loaded: false,
    }),
    actions: {
        async fetchContacts(force = false): Promise<void> {
            if (!crossTabSubscribed) {
                crossTabSubscribed = true;
                onCatalogInvalidatedElsewhere(CATALOG_KEY, () => {
                    this.loaded = false;
                    if (this.items.length > 0) void this.fetchContacts(true);
                });
            }

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
            broadcastCatalogInvalidated(CATALOG_KEY);
        },
    },
});
