import { api } from './client';
import type { BasicDataParams } from './basic-data';
import type { Paginated } from '@/types/api';

export type ProductParams = BasicDataParams & { category?: string };

export type Product = {
    id: number;
    code: string;
    name: string;
    model: string | null;
    specification: string | null;
    category: string;
    unit: string;
    status: boolean;
};

export type ProductVersion = {
    id: number;
    product_id: number;
    version: string;
    remark: string | null;
    status: boolean;
    created_at: string;
    updated_at: string;
};

export type ProductPayload = {
    code: string;
    name: string;
    model?: string | null;
    specification?: string | null;
    category: string;
    unit: string;
    status?: boolean;
};

export type ProductVersionPayload = {
    version: string;
    remark?: string | null;
    status?: boolean;
};

export function listProducts(params: ProductParams = {}) {
    return api.get<Paginated<Product>>('/basic-data/products', params);
}

export function createProduct(payload: ProductPayload) {
    return api.post<Product>('/basic-data/products', payload);
}

export function updateProduct(id: number, payload: Partial<ProductPayload>) {
    return api.put<Product>(`/basic-data/products/${id}`, payload);
}

export function deleteProduct(id: number) {
    return api.delete<void>(`/basic-data/products/${id}`);
}

export function listProductVersions(productId: number) {
    return api.get<Paginated<ProductVersion>>(`/basic-data/products/${productId}/versions`, { per_page: 100 });
}

export function createProductVersion(productId: number, payload: ProductVersionPayload) {
    return api.post<ProductVersion>(`/basic-data/products/${productId}/versions`, payload);
}

export function updateProductVersion(
    productId: number,
    versionId: number,
    payload: Partial<ProductVersionPayload>,
) {
    return api.put<ProductVersion>(`/basic-data/products/${productId}/versions/${versionId}`, payload);
}
