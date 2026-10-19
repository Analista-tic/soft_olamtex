import axios from 'axios';
import { ref } from 'vue';

const SUPPLIER_TYPES = ['proveedor', 'cliente_proveedor'] as const;

interface ContactLike {
    id?: number | string;
}

/**
 * Proveedores para los selectores de compras (facturas), cargados de a `perPage` (30 por defecto) para no
 * traer todo el catálogo de una vez. Incluye contactos de tipo proveedor y cliente-proveedor.
 * La búsqueda (`search`) se resuelve en el servidor (nombre, RUC, teléfono, email), no sobre lo ya cargado.
 * `getSelectedId` devuelve el contacto elegido en el formulario: se conserva en la lista al recargar/buscar
 * para que el selector nunca pierda su etiqueta ni sus datos (RUC, teléfono).
 * Compartido por las facturas de compra normal y de tela.
 */
export function useSupplierContacts<T extends ContactLike>(perPage = 30, getSelectedId: () => number | string | null | undefined = () => null) {
    const contacts = ref<T[]>([]);
    const isLoadingContacts = ref(false);
    const isLoadingMoreContacts = ref(false);
    const contactsHasMore = ref(false);
    const contactsPage = ref(1);
    let searchTerm = '';
    let requestId = 0;

    /** Carga la página indicada; la 1 reemplaza la lista (conservando el seleccionado) y las siguientes se agregan sin duplicar. */
    async function fetchContacts(page = 1, search = searchTerm): Promise<void> {
        const currentRequest = ++requestId;
        if (page === 1) {
            searchTerm = search;
            isLoadingContacts.value = true;
            contactsPage.value = 1;
        } else {
            isLoadingMoreContacts.value = true;
        }
        const selectedId = Number(getSelectedId() ?? 0);
        const preserved = page === 1 && selectedId > 0 ? (contacts.value.find((contact) => Number(contact.id) === selectedId) ?? null) : null;
        try {
            const responses = await Promise.all(
                SUPPLIER_TYPES.map((contactType) =>
                    axios.get<{ data: T[]; total: number }>('/contactos/data', {
                        params: { contact_type: contactType, per_page: perPage, page, ...(searchTerm ? { search: searchTerm } : {}) },
                    }),
                ),
            );
            if (currentRequest !== requestId) return; // respuesta obsoleta: hay una búsqueda más reciente en curso
            const received = responses.flatMap((response) => response.data?.data ?? []);
            if (page === 1) {
                contacts.value = preserved && !received.some((contact) => Number(contact.id) === selectedId) ? [preserved, ...received] : received;
            } else {
                const seen = new Set(contacts.value.map((contact) => Number(contact.id)));
                contacts.value = [...contacts.value, ...received.filter((contact) => !seen.has(Number(contact.id)))];
            }
            contactsHasMore.value = responses.some((response) => page * perPage < (response.data?.total ?? 0));
            if (page === 1 && selectedId > 0 && !contacts.value.some((contact) => Number(contact.id) === selectedId)) {
                void fetchContactById(selectedId);
            }
        } catch {
            if (currentRequest !== requestId) return;
            if (page === 1) contacts.value = preserved ? [preserved] : [];
            contactsHasMore.value = false;
        } finally {
            if (currentRequest === requestId) {
                isLoadingContacts.value = false;
                isLoadingMoreContacts.value = false;
            }
        }
    }

    /** Búsqueda en servidor (el debounce ya lo aplica SelectField). Vacío vuelve a la primera página completa. */
    function searchContacts(term: string): Promise<void> {
        return fetchContacts(1, term.trim());
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

    return { contacts, isLoadingContacts, isLoadingMoreContacts, contactsHasMore, fetchContacts, searchContacts, loadMoreContacts, fetchContactById };
}
