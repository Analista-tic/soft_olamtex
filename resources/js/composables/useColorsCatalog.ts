import axios from 'axios';
import { ref } from 'vue';

export type CatalogColor = {
    id: number;
    name: string;
    rgb: string | null;
    hexadecimal: string | null;
    description: string | null;
};

// Ver el comentario de useItemsCatalog.ts: este Map vive en un módulo .ts real, así
// que sí se comparte entre todos los componentes que lo importan (a diferencia de un
// `let` dentro de <script setup>, que el compilador reinicia en cada montaje).
let cache: CatalogColor[] | null = null;
let inFlight: Promise<CatalogColor[]> | null = null;

/**
 * Catálogo de colores (/colors) compartido entre todas las instancias de
 * ColorPickerSelect: crear un color en una lo refleja en las demás sin recargar
 * la página.
 */
export function useColorsCatalog() {
    const colors = ref<CatalogColor[]>([]);
    const isLoading = ref(false);

    async function fetch(force = false): Promise<CatalogColor[]> {
        if (!force && cache) {
            colors.value = cache;
            return colors.value;
        }

        if (!force && inFlight) {
            colors.value = await inFlight;
            return colors.value;
        }

        isLoading.value = true;
        const promise = axios
            .get('/colors')
            .then(({ data }) => {
                const rows: CatalogColor[] = Array.isArray(data) ? data : [];
                cache = rows;
                return rows;
            })
            .finally(() => {
                inFlight = null;
                isLoading.value = false;
            });

        inFlight = promise;
        colors.value = await promise;
        return colors.value;
    }

    function invalidate() {
        cache = null;
    }

    return { colors, isLoading, fetch, invalidate };
}
