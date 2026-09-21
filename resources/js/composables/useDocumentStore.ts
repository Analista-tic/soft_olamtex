import axios from 'axios';
import type { Ref } from 'vue';

import type { PaginatedData } from '@/domain/shared';

type CollectionPayload<T> =
    | T[]
    | PaginatedData<T>
    | {
          data?: T[] | PaginatedData<T>;
          total?: number;
          current_page?: number;
          per_page?: number;
          last_page?: number;
      };

type DetailPayload<T> = { data?: T } | T;

export interface PaginatedMeta {
    total: number;
    currentPage: number;
    perPage: number;
    lastPage: number;
}

interface NormalizedCollection<T> {
    data: T[];
    total: number;
    currentPage: number;
    perPage: number;
    lastPage: number;
}

// eslint-disable-next-line @typescript-eslint/no-unused-vars -- genéricos usados por los llamadores
interface UseDocumentStoreOptions<TItem extends { id: number }, TDto, TFilters extends object, TCreate, TUpdate> {
    baseUrl: string;
    items: Ref<TItem[]>;
    currentItem: Ref<TItem | null>;
    loading: Ref<boolean>;
    meta: Ref<PaginatedMeta>;
    filters: Ref<TFilters>;
    mapItem: (dto: TDto) => TItem;
    defaultPerPage?: number;
}

function isPaginatedData<T>(value: unknown): value is PaginatedData<T> {
    return Boolean(
        value &&
            typeof value === 'object' &&
            Array.isArray((value as PaginatedData<T>).data) &&
            typeof (value as PaginatedData<T>).current_page === 'number',
    );
}

function isCollectionContainer<T>(
    value: CollectionPayload<T>,
): value is {
    data?: T[] | PaginatedData<T>;
    total?: number;
    current_page?: number;
    per_page?: number;
    last_page?: number;
} {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function unwrapCollectionPayload<T>(payload: CollectionPayload<T>): NormalizedCollection<T> {
    if (isPaginatedData(payload)) {
        return {
            data: payload.data,
            total: payload.total ?? payload.data.length,
            currentPage: payload.current_page ?? 1,
            perPage: payload.per_page ?? payload.data.length,
            lastPage: payload.last_page ?? 1,
        };
    }

    const candidate: T[] | PaginatedData<T> = isCollectionContainer(payload) ? (payload.data ?? []) : payload;

    if (isPaginatedData(candidate)) {
        return {
            data: candidate.data,
            total: candidate.total ?? candidate.data.length,
            currentPage: candidate.current_page ?? 1,
            perPage: candidate.per_page ?? candidate.data.length,
            lastPage: candidate.last_page ?? 1,
        };
    }

    const data: T[] = Array.isArray(candidate) ? candidate : [];
    const root = isCollectionContainer(payload) ? payload : {};

    return {
        data,
        total: typeof root.total === 'number' ? root.total : data.length,
        currentPage: typeof root.current_page === 'number' ? root.current_page : 1,
        perPage: typeof root.per_page === 'number' ? root.per_page : data.length,
        lastPage: typeof root.last_page === 'number' ? root.last_page : 1,
    };
}

function unwrapDetailPayload<T>(payload: DetailPayload<T>): T {
    if (typeof payload === 'object' && payload !== null && 'data' in payload) {
        return payload.data as T;
    }

    return payload as T;
}

function rethrowStoreError(error: unknown): never {
    throw error;
}

export function defaultPaginatedMeta(perPage = 10): PaginatedMeta {
    return {
        total: 0,
        currentPage: 1,
        perPage,
        lastPage: 1,
    };
}

export function useDocumentStore<TItem extends { id: number }, TDto, TFilters extends object, TCreate = unknown, TUpdate = TCreate>({
    baseUrl,
    items,
    currentItem,
    loading,
    meta,
    filters,
    mapItem,
    defaultPerPage = 10,
}: UseDocumentStoreOptions<TItem, TDto, TFilters, TCreate, TUpdate>) {
    const buildDetailUrl = (id: number) => `${baseUrl}/${id}`;

    const fetchAll = async (nextFilters: Partial<TFilters> = {}): Promise<void> => {
        loading.value = true;

        try {
            const mergedFilters = {
                ...filters.value,
                ...nextFilters,
            } as TFilters;

            filters.value = mergedFilters;

            const response = await axios.get<CollectionPayload<TDto>>(`${baseUrl}/data`, {
                params: mergedFilters,
            });

            const normalized = unwrapCollectionPayload(response.data);
            items.value = normalized.data.map(mapItem);
            meta.value = {
                total: normalized.total,
                currentPage: normalized.currentPage,
                perPage: normalized.perPage || Number((mergedFilters as { per_page?: number }).per_page ?? defaultPerPage),
                lastPage: normalized.lastPage,
            };
        } catch (error) {
            rethrowStoreError(error);
        } finally {
            loading.value = false;
        }
    };

    const fetchOne = async (id: number): Promise<TItem> => {
        loading.value = true;

        try {
            const response = await axios.get<DetailPayload<TDto>>(buildDetailUrl(id));
            const item = mapItem(unwrapDetailPayload(response.data));

            currentItem.value = item;

            return item;
        } catch (error) {
            rethrowStoreError(error);
        } finally {
            loading.value = false;
        }
    };

    const create = async (payload: TCreate): Promise<TItem> => {
        try {
            const response = await axios.post<DetailPayload<TDto>>(baseUrl, payload);
            const item = mapItem(unwrapDetailPayload(response.data));

            currentItem.value = item;

            return item;
        } catch (error) {
            rethrowStoreError(error);
        }
    };

    const update = async (id: number, payload: TUpdate): Promise<TItem> => {
        try {
            const response = await axios.put<DetailPayload<TDto>>(buildDetailUrl(id), payload);
            const item = mapItem(unwrapDetailPayload(response.data));

            currentItem.value = item;

            return item;
        } catch (error) {
            rethrowStoreError(error);
        }
    };

    const remove = async (id: number): Promise<void> => {
        try {
            await axios.delete(buildDetailUrl(id));

            if (currentItem.value?.id === id) {
                currentItem.value = null;
            }
        } catch (error) {
            rethrowStoreError(error);
        }
    };

    return {
        items,
        currentItem,
        loading,
        meta,
        filters,
        fetchAll,
        fetchOne,
        create,
        update,
        remove,
    };
}
