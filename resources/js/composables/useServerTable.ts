import axios from 'axios';
import { ref, type Ref } from 'vue';

export type SortDir = 'asc' | 'desc';

export interface ServerTableParams {
    page: number;
    per_page: number;
    sort_by: string;
    sort_dir: SortDir;
}

export interface ServerTableResult<T> {
    data: T[];
    total: number;
    currentPage: number;
}

export interface UseServerTableOptions<T> {
    /**
     * Cada página define cómo pide sus datos: arma su propia llamada axios con
     * `params` (page/per_page/sort_by/sort_dir) más los filtros que le
     * correspondan a esa pantalla, y adapta la respuesta de su propio backend
     * al shape {data, total, currentPage}.
     */
    fetcher: (params: ServerTableParams, signal: AbortSignal) => Promise<ServerTableResult<T>>;
    defaultSortBy?: string;
    defaultSortDir?: SortDir;
    defaultPageSize?: number;
}

/**
 * Orquesta sort/paginación/loading para una tabla server-side, siguiendo el
 * patrón ya usado en MasterData/Item/Index.vue: ordenar por la misma columna
 * invierte la dirección, ordenar por otra la fija en 'desc' y resetea a la
 * página 1; cada fetch cancela el anterior con AbortController y descarta la
 * respuesta si ya no es la petición activa.
 *
 * Los filtros NO son un concepto del composable — cada página sigue
 * declarando los suyos y los captura dentro de su propio `fetcher`.
 */
export function useServerTable<T>(options: UseServerTableOptions<T>) {
    const items = ref<T[]>([]) as Ref<T[]>;
    const totalItems = ref(0);
    const currentPage = ref(1);
    const pageSize = ref(options.defaultPageSize ?? 10);
    const isLoading = ref(false);
    const sortBy = ref(options.defaultSortBy ?? 'id');
    const sortDir = ref<SortDir>(options.defaultSortDir ?? 'desc');

    let activeController: AbortController | null = null;

    async function fetchPage(page: number = currentPage.value) {
        if (activeController) {
            activeController.abort();
        }

        const controller = new AbortController();
        activeController = controller;
        isLoading.value = true;

        try {
            const result = await options.fetcher(
                {
                    page,
                    per_page: pageSize.value,
                    sort_by: sortBy.value,
                    sort_dir: sortDir.value,
                },
                controller.signal,
            );

            if (activeController !== controller) return;

            items.value = result.data;
            totalItems.value = result.total;
            currentPage.value = result.currentPage;
        } catch (err) {
            if (axios.isCancel(err)) return;
            throw err;
        } finally {
            if (activeController === controller) {
                activeController = null;
                isLoading.value = false;
            }
        }
    }

    function toggleSort(column: string) {
        if (sortBy.value === column) {
            sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
        } else {
            sortBy.value = column;
            sortDir.value = 'desc';
        }

        currentPage.value = 1;
        void fetchPage(1);
    }

    function reload() {
        void fetchPage(currentPage.value);
    }

    return {
        items,
        totalItems,
        currentPage,
        pageSize,
        isLoading,
        sortBy,
        sortDir,
        toggleSort,
        fetchPage,
        reload,
    };
}
