import axios from 'axios';
import type { Option } from '@/domain/shared';
import { watch, type WatchSource, type WatchStopHandle } from 'vue';
import { QUOTE_STATUSES, SALES_INVOICE_STATUSES, SALES_ORDER_STATUSES, SALE_NOTE_STATUSES } from '@/domain/Income';

export type IncomeDocumentType = 'quote' | 'sales_order' | 'sales_invoice' | 'credit_note' | 'debit_note';

type StatusMeta = {
    label: string;
    variant: string;
};

export const INCOME_TABLE_BADGE_CLASS = 'h-6 justify-center px-2.5 py-0 text-[11px] font-medium leading-none';
const DEFAULT_BADGE_VARIANT = 'border-slate-300 bg-slate-50/80 text-slate-700 dark:border-slate-400/40 dark:bg-slate-500/10 dark:text-slate-300';

const QUOTE_STATUS_META = Object.fromEntries(Object.values(QUOTE_STATUSES).map((status) => [status.value, status])) as Record<string, StatusMeta>;
const SALES_ORDER_STATUS_META = Object.fromEntries(Object.values(SALES_ORDER_STATUSES).map((status) => [status.value, status])) as Record<string, StatusMeta>;
const SALES_INVOICE_STATUS_META = Object.fromEntries(Object.values(SALES_INVOICE_STATUSES).map((status) => [status.value, status])) as Record<string, StatusMeta>;
const SALE_NOTE_STATUS_META = Object.fromEntries(Object.values(SALE_NOTE_STATUSES).map((status) => [status.value, status])) as Record<string, StatusMeta>;

const STATUS_META_BY_DOCUMENT: Record<IncomeDocumentType, Record<string, StatusMeta>> = {
    quote: QUOTE_STATUS_META,
    sales_order: SALES_ORDER_STATUS_META,
    sales_invoice: SALES_INVOICE_STATUS_META,
    credit_note: SALE_NOTE_STATUS_META,
    debit_note: SALE_NOTE_STATUS_META,
};

function resolveStatusMeta(status?: string, documentType?: IncomeDocumentType): StatusMeta {
    const statusKey = status ?? '';
    const documentMeta = documentType ? STATUS_META_BY_DOCUMENT[documentType]?.[statusKey] : undefined;

    return {
        label: documentMeta?.label ?? (status || '-'),
        variant: documentMeta?.variant ?? DEFAULT_BADGE_VARIANT,
    };
}

export function normalizeErrorMessage(error: unknown, fallback: string): string {
    const axiosError = error as {
        response?: {
            data?: {
                message?: string;
                errors?: Record<string, string[] | string>;
            };
        };
        message?: string;
    };

    const errors = axiosError?.response?.data?.errors;
    if (errors) {
        const firstEntry = Object.values(errors)[0];
        if (Array.isArray(firstEntry)) {
            return firstEntry[0] ?? fallback;
        }

        if (typeof firstEntry === 'string') {
            return firstEntry;
        }
    }

    return axiosError?.response?.data?.message ?? axiosError?.message ?? fallback;
}

export function formatCurrency(value: number): string {
    return Number(value || 0).toLocaleString('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    });
}

export function getStatusLabel(status?: string, documentType?: IncomeDocumentType): string {
    return resolveStatusMeta(status, documentType).label;
}

export function getStatusVariant(status?: string, documentType?: IncomeDocumentType): string {
    return resolveStatusMeta(status, documentType).variant;
}

export async function fetchIncomeCustomerOptions(searchTerm = '', limit = 30): Promise<Option[]> {
    const response = await axios.get<{ data?: Array<{ id: number; full_name?: string; name?: string }> }>('/finanzas/recibos-caja/customers', {
        params: {
            search: searchTerm,
            limit,
        },
    });

    return (response.data.data ?? []).map((customer) => ({
        id: customer.id,
        name: customer.full_name ?? customer.name ?? `Cliente ${customer.id}`,
    }));
}

export function resolveIncomePageChange(page: number, currentPage: number, totalPages: number): number | null {
    if (page < 1 || page > totalPages || page === currentPage) {
        return null;
    }

    return page;
}

export function watchIncomeDocumentFilters(
    sources: ReadonlyArray<WatchSource<unknown>>,
    callback: () => void | Promise<void>,
    debounceMs = 250,
): WatchStopHandle {
    return watch(sources, (_, __, onCleanup) => {
        if (debounceMs <= 0) {
            void callback();
            return;
        }

        const timeoutId = setTimeout(() => {
            void callback();
        }, debounceMs);

        onCleanup(() => clearTimeout(timeoutId));
    });
}
