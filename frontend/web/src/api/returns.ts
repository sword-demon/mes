import { api } from './client';
import type { Paginated } from '@/types/api';

export type ReturnMaterial = {
    id: number;
    code: string;
    name: string;
    unit: string;
    batch_managed?: boolean;
};

export type MaterialReturnItem = {
    id: number;
    requisition_item_id: number;
    material_id: number | null;
    material: ReturnMaterial | null;
    quantity: number;
};

export type MaterialReturn = {
    id: number;
    code: string;
    requisition_id: number;
    requisition?: { id: number; code: string };
    production_order_id: number;
    production_order?: { id: number; code: string };
    status: 'pending' | 'approved' | 'cancelled';
    status_label: string;
    remark: string | null;
    created_by_id: number;
    items: MaterialReturnItem[];
    approved_by: { id: number; name: string } | null;
    approved_at: string | null;
    created_at: string;
};

export type ReturnSuggestion = {
    requisition_item_id: number;
    material: ReturnMaterial;
    issued_quantity: number;
    approved_returned_quantity: number;
    pending_quantity: number;
    returnable_quantity: number;
};

export type ReturnParams = {
    page?: number;
    per_page?: number;
    production_order_id?: number;
    material_id?: number;
    status?: 'pending' | 'approved' | 'cancelled';
    date_from?: string;
    date_to?: string;
};

export type ReturnPayload = {
    requisition_id: number;
    items: { requisition_item_id: number; quantity: number }[];
    remark?: string | null;
};

export type ApproveReturnPayload = {
    items: {
        id: number;
        warehouse_id: number;
        location_id: number;
        batch_no?: string | null;
    }[];
};

export function listReturns(params: ReturnParams = {}) {
    return api.get<Paginated<MaterialReturn>>('/materials/returns', params);
}

export function returnSuggestions(requisitionId: number) {
    return api.get<ReturnSuggestion[]>('/materials/returns/suggestions', {
        requisition_id: requisitionId,
    });
}

export function createReturn(payload: ReturnPayload) {
    return api.post<MaterialReturn>('/materials/returns', payload);
}

export function approveReturn(id: number, payload: ApproveReturnPayload) {
    return api.post<MaterialReturn>(`/materials/returns/${id}/approve`, payload);
}

export function cancelReturn(id: number) {
    return api.delete<MaterialReturn>(`/materials/returns/${id}`);
}
