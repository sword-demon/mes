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

export const INSPECTION_TYPES = [
    { value: 'ipqc', label: 'IPQC 过程检验', color: 'blue' },
    { value: 'oqc', label: 'OQC 出货检验', color: 'purple' },
] as const;

export type InspectionType = (typeof INSPECTION_TYPES)[number]['value'];

export const INSPECTION_RESULTS = [
    { value: 'pending', label: '待检验', color: 'default' },
    { value: 'passed', label: '合格', color: 'green' },
    { value: 'failed', label: '不合格', color: 'red' },
] as const;

export type InspectionStatus = (typeof INSPECTION_RESULTS)[number]['value'];

export type InspectionOrderRef = {
    id: number;
    code: string;
    product: { id: number; code: string; name: string };
};

export type InspectionProcessRecordOption = {
    id: number;
    sequence: number;
    sn: string | null;
    route_operation: {
        id: number;
        sequence: number;
        process: { id: number; code?: string; name: string };
    };
};

export type InspectionProcessRecordRef = { id: number; sequence: number; sn: string | null };

export type InspectionTaskItem = {
    inspection_item_id: number;
    code: string;
    name: string;
    unit: string;
    measured_value: string;
    standard_value: string;
    min_value: string;
    max_value: string;
    passed: boolean;
};

export type InspectionImage = { id: number; original_name: string | null; url: string };

export type InspectionTask = {
    id: number;
    inspection_type: InspectionType;
    inspection_type_label: string;
    result: 'passed' | 'failed' | null;
    status: InspectionStatus;
    result_label: string;
    inspector: { id: number; name: string } | null;
    inspected_at: string | null;
    remark: string | null;
    production_order: InspectionOrderRef;
    route_operation: {
        id: number;
        sequence: number;
        process: { id: number; code: string; name: string };
    } | null;
    process_record: InspectionProcessRecordRef | null;
    items: InspectionTaskItem[];
    images: InspectionImage[];
    created_at: string;
    updated_at: string;
};

export type InspectionTaskParams = {
    page?: number;
    per_page?: number;
    keyword?: string;
    production_order_id?: number;
    route_operation_id?: number;
    inspection_type?: InspectionType;
    result?: InspectionStatus;
    judged?: 1;
    start_date?: string;
    end_date?: string;
};

export type InspectionTaskPayload = {
    production_order_id: number;
    inspection_type: InspectionType;
    process_record_id?: number | null;
    route_operation_id?: number | null;
    remark?: string | null;
};

export type InspectionRecordPayload = {
    items: { inspection_item_id: number; measured_value: number }[];
    remark?: string | null;
};

export type InspectionOptions = {
    orders: InspectionOrderRef[];
    operations: { id: number; sequence: number; process: { id: number; name: string } }[];
    process_records: InspectionProcessRecordOption[];
};

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

export function listInspectionTasks(params: InspectionTaskParams = {}) {
    return api.get<Paginated<InspectionTask>>('/quality/inspection-tasks', params);
}

export function getInspectionOptions(productionOrderId?: number) {
    return api.get<InspectionOptions>('/quality/inspection-tasks/options', {
        production_order_id: productionOrderId,
    });
}

export function createInspectionTask(payload: InspectionTaskPayload) {
    return api.post<InspectionTask>('/quality/inspection-tasks', payload);
}

export function recordInspectionTask(id: number, payload: InspectionRecordPayload) {
    return api.post<InspectionTask>(`/quality/inspection-tasks/${id}/record`, payload);
}

export function uploadInspectionImages(id: number, files: File[]) {
    const form = new FormData();
    files.forEach((file) => form.append('images[]', file));

    return api.post<InspectionTask>(`/quality/inspection-tasks/${id}/images`, form);
}
