import axios from 'axios';
import type { Contact } from '@/domain/MasterData';
import { defineStore } from 'pinia';

type ContactsResponse = Contact[];

interface ContactsState {
    items: Contact[];
    loading: boolean;
    loaded: boolean;
}

export const useContactsStore = defineStore('contacts', {
    state: (): ContactsState => ({
        items: [],
        loading: false,
        loaded: false,
    }),
    actions: {
        async fetchContacts(): Promise<void> {
            if (this.loaded || this.loading) return;
            this.loading = true;
            try {
                const response = await axios.get<ContactsResponse>('/contactos/list');
                this.items = response.data;
                this.loaded = true;
            } catch (error) {
                console.error('Error cargando contactos:', error);
            } finally {
                this.loading = false;
            }
        },
        invalidate(): void {
            this.loaded = false;
        },
    },
});
