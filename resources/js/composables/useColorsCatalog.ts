import { broadcastCatalogInvalidated, onCatalogInvalidatedElsewhere } from '@/lib/crossTabSync';
import axios from 'axios';
import { ref, watch } from 'vue';

export type CatalogColor = {
    id: number;
    name: string;
    rgb: string | null;
    hexadecimal: string | null;
    description: string | null;
};

const CATALOG_KEY = 'colors';

// Ver el comentario de useItemsCatalog.ts: este Map vive en un módulo .ts real, así
// que sí se comparte entre todos los componentes que lo importan (a diferencia de un
// `let` dentro de <script setup>, que el compilador reinicia en cada montaje).
let cache: CatalogColor[] | null = null;
let inFlight: Promise<CatalogColor[]> | null = null;

// Se incrementa cuando OTRA pestaña del navegador invalida este catálogo: las
// instancias que ya cargaron datos lo escuchan (ver abajo) para refrescarse solas.
const remoteVersion = ref(0);
onCatalogInvalidatedElsewhere(CATALOG_KEY, () => {
    cache = null;
    remoteVersion.value++;
});

/**
 * Catálogo de colores (/colors) compartido entre todas las instancias de
 * ColorPickerSelect: crear un color en una lo refleja en las demás sin recargar
 * la página.
 */
export function useColorsCatalog() {
    const colors = ref<CatalogColor[]>([]);
    const isLoading = ref(false);
    let hasFetched = false;

    async function fetch(force = false): Promise<CatalogColor[]> {
        hasFetched = true;
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
        broadcastCatalogInvalidated(CATALOG_KEY);
    }

    // Otra pestaña creó/editó un color: si esta instancia ya había cargado datos, se
    // refresca sola en vez de esperar a que el usuario recargue la página.
    watch(remoteVersion, () => {
        if (hasFetched) void fetch(true);
    });

    return { colors, isLoading, fetch, invalidate };
}
