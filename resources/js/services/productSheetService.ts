import axios from 'axios';
import type { ProductSheet } from '@/domain/product-sheet';

export interface ProductSheetListParams {
    page?: number;
    per_page?: number;
    search?: string;
    status?: string | null;
    section_id?: number | null;
    world_id?: number | null;
}

export interface ProductSheetListResponse {
    products_sheets: {
        data: ProductSheet[];
        total: number;
        current_page: number;
    };
}

export interface ProductSheetApiResponse {
    success?: boolean;
    message?: string;
    data?: ProductSheet;
}

export interface ConvertSampleToProductResponse {
    success?: boolean;
    message?: string;
    data?: ProductSheet;
}

export const listProductSheets = (params: ProductSheetListParams = {}, signal?: AbortSignal) =>
    axios.get<ProductSheetListResponse>('/produccion/product-sheets', { params, signal });

export const showProductSheet = (id: number | string) =>
    axios.get<ProductSheetApiResponse>(`/produccion/sabana-productos/${id}`);

export const createProductSheet = (payload: FormData) =>
    axios.post<ProductSheetApiResponse>('/produccion/sabana-productos', payload);

export const updateProductSheet = (id: number | string, payload: FormData) =>
    axios.post<ProductSheetApiResponse>(`/produccion/sabana-productos/${id}`, payload);

export const deleteProductSheet = (id: number | string) =>
    axios.delete(`/produccion/sabana-productos/${id}`);

export const convertSampleToProduct = (id: number | string, product_reference: string) =>
    axios.post<ConvertSampleToProductResponse>(`/produccion/sabana-productos/${id}/convert`, { product_reference });

export default {
    listProductSheets,
    showProductSheet,
    createProductSheet,
    updateProductSheet,
    deleteProductSheet,
    convertSampleToProduct,
};
