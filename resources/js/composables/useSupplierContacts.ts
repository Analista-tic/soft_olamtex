import axios from 'axios';
import { ref } from 'vue';

const SUPPLIER_TYPES = ['proveedor', 'cliente_proveedor'] as const;

interface ContactLike {
    id?: number | string;
}

/**
 * Proveedores para los selectores de factura de compra, cargados de a `perPage` (30 por defecto) para no
 * traer todo el catálogo de una vez. Incluye contactos de tipo proveedor y cliente-proveedor.
 * Compartido por las facturas de compra normal y de tela.
 */
export function useSupplierContacts<T extends ContactLike>(perPage = 30) {
    const contacts = ref<T[]>([]);
    const isLoadingContacts = ref(false);
    const isLoadingMoreContacts = ref(false);
    const contactsHasMore = ref(false);
    const contactsPage = ref(1);

    /** Carga la página indicada; la 1 reemplaza la lista y las siguientes se agregan sin duplicar. */
    async function fetchContacts(page = 1): Promise<void> {
        if (page === 1) {
            isLoadingContacts.value = true;
            contacts.value = [];
            contactsPage.value = 1;
        } else {
            isLoadingMoreContacts.value = true;
        }
        try {
            const responses = await Promise.all(
                SUPPLIER_TYPES.map((contactType) =>
                    axios.get<{ data: T[]; total: number }>('/contactos/data', { params: { contact_type: contactType, per_page: perPage, page } }),
                ),
            );
            const received = responses.flatMap((response) => response.data?.data ?? []);
            if (page === 1) {
                contacts.value = received;
            } else {
                const seen = new Set(contacts.value.map((contact) => Number(contact.id)));
                contacts.value = [...contacts.value, ...received.filter((contact) => !seen.has(Number(contact.id)))];
            }
            contactsHasMore.value = responses.some((response) => page * perPage < (response.data?.total ?? 0));
        } catch {
            if (page === 1) contacts.value = [];
            contactsHasMore.value = false;
        } finally {
            isLoadingContacts.value = false;
            isLoadingMoreContacts.value = false;
        }
    }

    async function loadMoreContacts(): Promise<void> {
        if (!contactsHasMore.value || isLoadingMoreContacts.value) return;
        contactsPage.value++;
        await fetchContacts(contactsPage.value);
    }

    /** Agrega un contacto puntual (p. ej. el de una orden o factura guardada) si aún no está en la lista cargada. */
    async function fetchContactById(contactId: number): Promise<void> {
        if (!Number.isFinite(contactId) || contactId <= 0) return;
        try {
            const { data } = await axios.get(`/contactos/${contactId}`);
            const contact = (data?.data ?? data) as T | undefined;
            if (!contact?.id) return;
            if (!contacts.value.some((existing) => Number(existing.id) === Number(contact.id))) {
                contacts.value = [...contacts.value, contact];
            }
        } catch {
            // Si el contacto no existe, el selector simplemente no lo muestra.
        }
    }

    return { contacts, isLoadingContacts, isLoadingMoreContacts, contactsHasMore, fetchContacts, loadMoreContacts, fetchContactById };
}
