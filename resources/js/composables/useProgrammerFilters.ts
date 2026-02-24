import type { Programmer } from '@/domain/product-sheet';
import axios from 'axios';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

export interface ProgrammerFilterState {
    season: string;
    priority: string;
    status: string;
    destination_id: string;
    estimated_delivery_date: string;
}

const DEFAULT_SELECT_VALUE = 'all';

export function useProgrammerFilters() {
    const programmers = ref<Programmer[]>([]);
    const currentPage = ref(1);
    const itemsPerPage = ref(10);
    const totalItems = ref(0);
    const searchQuery = ref('');
    const fetchDebounceMs = 350;
    let fetchDebounceTimer: ReturnType<typeof setTimeout> | null = null;
    let activeFetchController: AbortController | null = null;

    const filters = ref<ProgrammerFilterState>({
        season: DEFAULT_SELECT_VALUE,
        priority: DEFAULT_SELECT_VALUE,
        status: DEFAULT_SELECT_VALUE,
        destination_id: DEFAULT_SELECT_VALUE,
        estimated_delivery_date: '',
    });

    const seasonIsActive = computed(() => Boolean(filters.value.season) && filters.value.season !== DEFAULT_SELECT_VALUE);
    const priorityIsActive = computed(() => Boolean(filters.value.priority) && filters.value.priority !== DEFAULT_SELECT_VALUE);
    const statusIsActive = computed(() => Boolean(filters.value.status) && filters.value.status !== DEFAULT_SELECT_VALUE);
    const destinationIsActive = computed(() => Boolean(filters.value.destination_id) && filters.value.destination_id !== DEFAULT_SELECT_VALUE);
    const deliveryDateIsActive = computed(() => filters.value.estimated_delivery_date.trim() !== '');

    function clearFetchDebounce() {
        if (fetchDebounceTimer) {
            clearTimeout(fetchDebounceTimer);
            fetchDebounceTimer = null;
        }
    }

    async function fetchProgrammers(page = 1) {
        if (activeFetchController) {
            activeFetchController.abort();
        }

        const controller = new AbortController();
        activeFetchController = controller;
        const normalizedSearch = searchQuery.value.trim();

        try {
            const response = await axios.get('/comercial/programmers', {
                params: {
                    page,
                    per_page: itemsPerPage.value,
                    search: normalizedSearch === '' ? undefined : normalizedSearch,
                    season: filters.value.season === DEFAULT_SELECT_VALUE ? undefined : filters.value.season,
                    priority: filters.value.priority === DEFAULT_SELECT_VALUE ? undefined : filters.value.priority,
                    status: filters.value.status === DEFAULT_SELECT_VALUE ? undefined : filters.value.status,
                    destination_id: filters.value.destination_id === DEFAULT_SELECT_VALUE ? undefined : filters.value.destination_id,
                    estimated_delivery_date: filters.value.estimated_delivery_date.trim() === '' ? undefined : filters.value.estimated_delivery_date,
                },
                signal: controller.signal,
            });

            if (response?.data) {
                programmers.value = response.data.data;
                totalItems.value = response.data.total;
                currentPage.value = response.data.current_page;
            }
        } catch (error) {
            if (axios.isCancel(error)) return;
            console.error('Error al obtener los programadores:', error);
        } finally {
            if (activeFetchController === controller) {
                activeFetchController = null;
            }
        }
    }

    function scheduleFetch(page = 1) {
        clearFetchDebounce();
        fetchDebounceTimer = setTimeout(() => {
            fetchProgrammers(page);
        }, fetchDebounceMs);
    }

    function reloadCurrentPage() {
        clearFetchDebounce();
        fetchProgrammers(currentPage.value);
    }

    function handlePageChange(page: number) {
        if (page < 1 || page > Math.ceil(totalItems.value / itemsPerPage.value)) return;
        currentPage.value = page;
        clearFetchDebounce();
        fetchProgrammers(page);
    }

    function clearDeliveryDateFilter() {
        filters.value.estimated_delivery_date = '';
    }

    watch(
        [
            searchQuery,
            () => filters.value.season,
            () => filters.value.priority,
            () => filters.value.status,
            () => filters.value.destination_id,
            () => filters.value.estimated_delivery_date,
        ],
        () => scheduleFetch(1),
    );

    onMounted(() => {
        fetchProgrammers();
    });

    onUnmounted(() => {
        clearFetchDebounce();
        if (activeFetchController) {
            activeFetchController.abort();
            activeFetchController = null;
        }
    });

    return {
        programmers,
        currentPage,
        itemsPerPage,
        totalItems,
        searchQuery,
        filters,
        seasonIsActive,
        priorityIsActive,
        statusIsActive,
        destinationIsActive,
        deliveryDateIsActive,
        fetchProgrammers,
        reloadCurrentPage,
        handlePageChange,
        clearDeliveryDateFilter,
        DEFAULT_SELECT_VALUE,
    };
}
