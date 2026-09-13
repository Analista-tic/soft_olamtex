import type { Contact } from '@/domain/MasterData';
import axios from 'axios';
import { ref } from 'vue';

type ContactsCatalogParams = Record<string, string | number | boolean | undefined>;

// Ver el comentario de useItemsCatalog.ts: este Map vive en un módulo .ts real, así
// que sí se comparte entre todos los componentes que lo importan (a diferencia de un
// `let` dentro de <script setup>, que el compilador reinicia en cada montaje).
const cache = new Map<string, Contact[]>();
const inFlight = new Map<string, Promise<Contact[]>>();

function keyFor(params: ContactsCatalogParams): string {
    return JSON.stringify(params ?? {});
}

/**
 * Catálogo de contactos (/contactos/data) compartido entre diálogos: los mismos
 * parámetros de consulta (ej. contact_type) reutilizan el mismo resultado durante la
 * sesión en vez de que cada diálogo vuelva a traer la lista completa desde cero.
 */
export function useContactsCatalog() {
    const contacts = ref<Contact[]>([]);
    const isLoading = ref(false);

    async function fetch(params: ContactsCatalogParams = {}, force = false): Promise<Contact[]> {
        const key = keyFor(params);

        if (!force && cache.has(key)) {
            contacts.value = cache.get(key) ?? [];
            return contacts.value;
        }

        if (!force && inFlight.has(key)) {
            contacts.value = await inFlight.get(key)!;
            return contacts.value;
        }

        isLoading.value = true;
        const promise = axios
            .get('/contactos/data', { params: { per_page: 1000, ...params } })
            .then(({ data }) => {
                const rows: Contact[] = Array.isArray(data?.data) ? data.data : [];
                cache.set(key, rows);
                return rows;
            })
            .finally(() => {
                inFlight.delete(key);
                isLoading.value = false;
            });

        inFlight.set(key, promise);
        contacts.value = await promise;
        return contacts.value;
    }

    function invalidate(params?: ContactsCatalogParams) {
        if (params) {
            cache.delete(keyFor(params));
        } else {
            cache.clear();
        }
    }

    return { contacts, isLoading, fetch, invalidate };
}
