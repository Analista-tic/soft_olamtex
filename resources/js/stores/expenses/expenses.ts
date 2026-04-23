import axios from 'axios';
import { defineStore } from 'pinia';

import type { PurchaseOrder, PurchaseOrderDto } from '@/domain/Expenses';
import { mapPurchaseOrderDto } from '@/domain/Expenses';

interface FetchOrdersParams {
    page?: number;
    per_page?: number;
    search?: string;
    status?: string;
    exclude_fabric?: boolean;
}

interface ExpensesState {
    purchaseOrders: PurchaseOrder[];
    pendingOrders: PurchaseOrder[];
    loading: boolean;
    pendingLoading: boolean;
    total: number;
    currentPage: number;
}

export const useExpensesStore = defineStore('expenses', {
    state: (): ExpensesState => ({
        purchaseOrders: [],
        pendingOrders: [],
        loading: false,
        pendingLoading: false,
        total: 0,
        currentPage: 1,
    }),
    actions: {
        async fetchPurchaseOrders(params: FetchOrdersParams = {}): Promise<void> {
            this.loading = true;
            try {
                const response = await axios.get('/gastos/ordenes-compra/data', { params });
                const data = response.data;
                const dtoList = (data.data ?? []) as PurchaseOrderDto[];
                this.purchaseOrders = dtoList.map(mapPurchaseOrderDto);
                this.total = data.total ?? this.purchaseOrders.length;
                this.currentPage = data.current_page ?? params.page ?? this.currentPage;
            } finally {
                this.loading = false;
            }
        },
        async fetchPendingPurchaseOrders(params: FetchOrdersParams = {}): Promise<void> {
            this.pendingLoading = true;
            try {
                const defaultParams: FetchOrdersParams = {
                    status: 'pending',
                    per_page: 1000,
                    exclude_fabric: true,
                };
                const response = await axios.get('/gastos/ordenes-compra/data', {
                    params: { ...defaultParams, ...params },
                });
                const data = response.data;
                const dtoList = (data.data ?? []) as PurchaseOrderDto[];
                this.pendingOrders = dtoList.map(mapPurchaseOrderDto);
            } finally {
                this.pendingLoading = false;
            }
        },
    },
});
