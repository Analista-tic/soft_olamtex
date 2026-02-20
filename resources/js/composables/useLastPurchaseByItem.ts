import axios from 'axios';
import { onBeforeUnmount } from 'vue';

type LastPurchaseDto = {
    provider: {
        id: number | null;
        name: string | null;
    } | null;
    price: number | null;
    currency: 'COP';
    date: string | null;
    source: 'invoice' | 'purchase_order';
};

type LastPurchaseResponse = {
    data: LastPurchaseDto | null;
};

function isAbortError(error: unknown): boolean {
    if (axios.isCancel(error)) return true;
    if (error && typeof error === 'object') {
        const maybeCode = 'code' in error ? String((error as { code?: unknown }).code) : '';
        const maybeName = 'name' in error ? String((error as { name?: unknown }).name) : '';
        return maybeCode === 'ERR_CANCELED' || maybeName === 'AbortError' || maybeName === 'CanceledError';
    }
    return false;
}

export function useLastPurchaseByItem() {
    const cache = new Map<number, LastPurchaseDto | null>();
    const controllersByKey = new Map<string, AbortController>();

    async function getLastPurchase(itemId: number, requestKey = 'default'): Promise<LastPurchaseDto | null> {
        if (!Number.isFinite(itemId) || itemId <= 0) {
            return null;
        }

        if (cache.has(itemId)) {
            return cache.get(itemId) ?? null;
        }

        cancelRequest(requestKey);

        const controller = new AbortController();
        controllersByKey.set(requestKey, controller);

        try {
            const response = await axios.get<LastPurchaseResponse>(
                `/produccion/insumos/items/${itemId}/last-purchase`,
                { signal: controller.signal },
            );
            const data = response.data?.data ?? null;
            cache.set(itemId, data);
            return data;
        } catch (error) {
            if (isAbortError(error)) {
                return null;
            }
            throw error;
        } finally {
            if (controllersByKey.get(requestKey) === controller) {
                controllersByKey.delete(requestKey);
            }
        }
    }

    function cancelRequest(requestKey: string): void {
        const controller = controllersByKey.get(requestKey);
        if (!controller) return;
        controller.abort();
        controllersByKey.delete(requestKey);
    }

    function cancelAll(): void {
        controllersByKey.forEach((controller) => controller.abort());
        controllersByKey.clear();
    }

    function clearCache(): void {
        cache.clear();
    }

    onBeforeUnmount(() => {
        cancelAll();
    });

    return {
        getLastPurchase,
        cancelRequest,
        cancelAll,
        clearCache,
    };
}

export type { LastPurchaseDto };

