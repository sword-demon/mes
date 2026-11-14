import { api } from './client';
import type { BasicDataParams } from './basic-data';
import type { Paginated } from '@/types/api';

export const MATERIAL_TYPES = ['原材料', '半成品', '成品', '辅助材料', '包装材料'] as const;

export type MaterialType = (typeof MATERIAL_TYPES)[number];

export type MaterialParams = BasicDataParams & { type?: MaterialType };

export type Material = {
    id: number;
    code: string;
    name: string;
    type: MaterialType;
    specification: string | null;
    unit: string;
    min_stock: number;
    safety_stock: number;
    batch_managed: boolean;
    status: boolean;
};

export type MaterialPayload = {
    code: string;
    name: string;
    type: MaterialType;
    specification?: string | null;
    unit: string;
    min_stock?: number;
    safety_stock?: number;
    batch_managed?: boolean;
    status?: boolean;
};

export function listMaterials(params: MaterialParams = {}) {
    return api.get<Paginated<Material>>('/basic-data/materials', params);
}

export function createMaterial(payload: MaterialPayload) {
    return api.post<Material>('/basic-data/materials', payload);
}

export function updateMaterial(id: number, payload: Partial<MaterialPayload>) {
    return api.put<Material>(`/basic-data/materials/${id}`, payload);
}
