import type { Item } from '@/domain/MasterData';
import { broadcastCatalogInvalidated, onCatalogInvalidatedElsewhere } from '@/lib/crossTabSync';
import axios from 'axios';
import { ref, watch } from 'vue';

type ItemsCatalogParams = Record<string, string | number | boolean | undefined>;

const CATALOG_KEY = 'items';

// Caché real a nivel de módulo ES (a diferencia de un `let` declarado dentro de
// <script setup>, que el compilador de Vue mete dentro de setup() y por lo tanto se
// reinicia en cada montaje — este archivo .ts sí es un módulo único compartido por
// todos los componentes que lo importan). Clave = parámetros de consulta exactos, así
// que distintos filtros (item_type, status, etc.) no colisionan entre sí.
const cache = new Map<string, Item[]>();
const inFlight = new Map<string, Promise<Item[]>>();

// Se incrementa cuando OTRA pestaña del navegador invalida este catálogo: las
// instancias que ya cargaron datos lo escuchan (ver abajo) para refrescarse solas.
const remoteVersion = ref(0);
onCatalogInvalidatedElsewhere(CATALOG_KEY, () => {
    cache.clear();
    remoteVersion.value++;
});

function keyFor(params: ItemsCatalogParams): string {
    return JSON.stringify(params ?? {});
}

/**
 * Catálogo de ítems (/item/data) compartido entre diálogos: los mismos parámetros de
 * consulta reutilizan el mismo resultado durante la sesión en vez de que cada
 * componente que se abre vuelva a traer la lista completa desde cero.
 */
export function useItemsCatalog() {
    const items = ref<Item[]>([]);
    const isLoading = ref(false);
    let lastParams: ItemsCatalogParams | null = null;

    async function fetch(params: ItemsCatalogParams = {}, force = false): Promise<Item[]> {
        lastParams = params;
        const key = keyFor(params);

        if (!force && cache.has(key)) {
            items.value = cache.get(key) ?? [];
            return items.value;
        }

        if (!force && inFlight.has(key)) {
            items.value = await inFlight.get(key)!;
            return items.value;
        }

        isLoading.value = true;
        const promise = axios
            .get('/item/data', { params: { per_page: 1000, ...params } })
            .then(({ data }) => {
                const rows: Item[] = Array.isArray(data?.data) ? data.data : [];
                cache.set(key, rows);
                return rows;
            })
            .finally(() => {
                inFlight.delete(key);
                isLoading.value = false;
            });

        inFlight.set(key, promise);
        items.value = await promise;
        return items.value;
    }

    function invalidate(params?: ItemsCatalogParams) {
        if (params) {
            cache.delete(keyFor(params));
        } else {
            cache.clear();
        }
        broadcastCatalogInvalidated(CATALOG_KEY);
    }

    // Otra pestaña creó/editó un ítem: si esta instancia ya había cargado datos, se
    // refresca sola en vez de esperar a que el usuario recargue la página.
    watch(remoteVersion, () => {
        if (lastParams !== null) void fetch(lastParams, true);
    });

    return { items, isLoading, fetch, invalidate };
}
