import { api } from './client';
import type { Paginated } from '@/types/api';

export type RequisitionMaterial = {
    id: number;
    code: string;
    name: string;
    unit: string;
};

export type RequisitionItem = {
    id: number;
    material_id: number;
    material: RequisitionMaterial | null;
    requested_quantity: number;
    issued_quantity: number;
    over_requirement: boolean;
};

export type MaterialRequisition = {
    id: number;
    code: string;
    production_order_id: number;
    production_order?: { id: number; code: string };
    status: 'pending' | 'issued';
    remark: string | null;
    items: RequisitionItem[];
    created_at: string;
};

export type RequisitionSuggestion = {
    material: RequisitionMaterial;
    required_quantity: number;
    issued_quantity: number;
    suggested_quantity: number;
};

export type RequisitionParams = {
    page?: number;
    per_page?: number;
    production_order_id?: number;
    material_id?: number;
    date_from?: string;
    date_to?: string;
};

export type RequisitionPayload = {
    production_order_id: number;
    items: { material_id: number; requested_quantity: number }[];
    remark?: string | null;
};

export type IssuePayload = {
    items: {
        id: number;
        warehouse_id: number;
        location_id: number;
        batch_no?: string | null;
        quantity: number;
    }[];
};

export function listRequisitions(params: RequisitionParams = {}) {
    return api.get<Paginated<MaterialRequisition>>('/materials/requisitions', params);
}

export function requisitionSuggestions(productionOrderId: number) {
    return api.get<RequisitionSuggestion[]>('/materials/requisitions/suggestions', {
        production_order_id: productionOrderId,
    });
}

export function createRequisition(payload: RequisitionPayload) {
    return api.post<MaterialRequisition>('/materials/requisitions', payload);
}

export function issueRequisition(id: number, payload: IssuePayload) {
    return api.post<MaterialRequisition>(`/materials/requisitions/${id}/issue`, payload);
}
