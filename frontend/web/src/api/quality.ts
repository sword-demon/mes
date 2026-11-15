import { api } from './client';
import type { BasicDataParams } from './basic-data';
import type { Paginated } from '@/types/api';

export type InspectionItem = {
    id: number;
    code: string;
    name: string;
    unit: string;
    standard_value: string;
    min_value: string;
    max_value: string;
    status: boolean;
};

export type InspectionItemPayload = Omit<InspectionItem, 'id' | 'standard_value' | 'min_value' | 'max_value'> & {
    standard_value: number;
    min_value: number;
    max_value: number;
};

export type DefectCode = { id: number; code: string; name: string; category: string; status: boolean };
export type DefectCodePayload = Omit<DefectCode, 'id'>;

export function listInspectionItems(params: BasicDataParams = {}) {
    return api.get<Paginated<InspectionItem>>('/quality/inspection-items', params);
}

export function createInspectionItem(payload: InspectionItemPayload) {
    return api.post<InspectionItem>('/quality/inspection-items', payload);
}

export function updateInspectionItem(id: number, payload: Partial<InspectionItemPayload>) {
    return api.put<InspectionItem>(`/quality/inspection-items/${id}`, payload);
}

export function listDefectCodes(params: BasicDataParams = {}) {
    return api.get<Paginated<DefectCode>>('/quality/defect-codes', params);
}

export function createDefectCode(payload: DefectCodePayload) {
    return api.post<DefectCode>('/quality/defect-codes', payload);
}

export function updateDefectCode(id: number, payload: Partial<DefectCodePayload>) {
    return api.put<DefectCode>(`/quality/defect-codes/${id}`, payload);
}
