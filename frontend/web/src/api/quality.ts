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
    { value: 'expired', label: '已过期', color: 'default' },
] as const;

export type InspectionStatus = (typeof INSPECTION_RESULTS)[number]['value'];
export type InspectionResultFilter = Exclude<InspectionStatus, 'expired'>;

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

export type InspectionRequiredItem = {
    id: number;
    code: string;
    name: string;
    unit: string;
    standard_value: string;
    min_value: string;
    max_value: string;
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
    required_items: InspectionRequiredItem[];
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
    result?: InspectionResultFilter;
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

export function getInspectionOptions(productionOrderId?: number, keyword?: string) {
    return api.get<InspectionOptions>('/quality/inspection-tasks/options', {
        production_order_id: productionOrderId,
        keyword,
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

export const DEFECT_STATUSES = [
    { value: 'pending', label: '待处理', color: 'gold' },
    { value: 'processing', label: '处理中', color: 'blue' },
    { value: 'pending_recheck', label: '待复检', color: 'orange' },
    { value: 'closed', label: '已关闭', color: 'green' },
] as const;

export type DefectStatus = (typeof DEFECT_STATUSES)[number]['value'];

export type DefectReworkOrder = {
    id: number;
    code: string;
    reason: string;
    created_by: { id: number; name: string } | null;
    recheck_result: 'passed' | 'failed' | null;
    recheck_remark: string | null;
    rechecked_by: { id: number; name: string } | null;
    rechecked_at: string | null;
    created_at: string;
};

export type DefectiveProduct = {
    id: number;
    status: DefectStatus;
    status_label: string;
    quantity: string;
    remark: string | null;
    production_order: InspectionOrderRef;
    route_operation: { id: number; sequence: number; process: { id: number; code: string; name: string } };
    production_batch: { id: number; code: string; quantity: number };
    defect_code: { id: number; code: string; name: string; category: string };
    handled_by: { id: number; name: string } | null;
    rework_orders: DefectReworkOrder[];
    recheck_result: 'passed' | 'failed' | null;
    recheck_remark: string | null;
    rechecked_at: string | null;
    scrap_reason: string | null;
    scrap_quantity: string | null;
    scrapped_at: string | null;
    created_at: string;
    updated_at: string;
};

export type DefectOptions = {
    orders: InspectionOrderRef[];
    operations: { id: number; sequence: number; process: { id: number; name: string } }[];
    batches: { id: number; code: string; quantity: number }[];
    defect_codes: { id: number; code: string; name: string; category: string }[];
};

export type DefectParams = {
    page?: number;
    per_page?: number;
    status?: DefectStatus;
    production_order_id?: number;
};

export type DefectPayload = {
    production_order_id: number;
    route_operation_id: number;
    production_batch_id: number;
    defect_code_id: number;
    quantity: number;
    remark?: string | null;
};

export function listDefectiveProducts(params: DefectParams = {}) {
    return api.get<Paginated<DefectiveProduct>>('/quality/defective-products', params);
}

export function getDefectOptions(productionOrderId?: number, keyword?: string) {
    return api.get<DefectOptions>('/quality/defective-products/options', {
        production_order_id: productionOrderId,
        keyword,
    });
}

export function createDefectiveProduct(payload: DefectPayload) {
    return api.post<DefectiveProduct>('/quality/defective-products', payload);
}

export function startDefectiveProduct(id: number) {
    return api.post<DefectiveProduct>(`/quality/defective-products/${id}/start`);
}

export function reworkDefectiveProduct(id: number, payload: { reason: string }) {
    return api.post<DefectiveProduct>(`/quality/defective-products/${id}/rework`, payload);
}

export function scrapDefectiveProduct(id: number, payload: { reason: string; quantity: number }) {
    return api.post<DefectiveProduct>(`/quality/defective-products/${id}/scrap`, payload);
}

export function recheckDefectiveProduct(id: number, payload: { passed: boolean; remark?: string | null }) {
    return api.post<DefectiveProduct>(`/quality/defective-products/${id}/recheck`, payload);
}

export type DefectDisposition =
    | { mode: 'rework'; reason: string }
    | { mode: 'scrap'; reason: string; quantity: number }
    | { mode: 'recheck'; passed: boolean; remark?: string | null };

export function disposeDefectiveProduct(id: number, disposition: DefectDisposition) {
    if (disposition.mode === 'rework') {
        return reworkDefectiveProduct(id, { reason: disposition.reason });
    }

    if (disposition.mode === 'scrap') {
        return scrapDefectiveProduct(id, { reason: disposition.reason, quantity: disposition.quantity });
    }

    return recheckDefectiveProduct(id, { passed: disposition.passed, remark: disposition.remark });
}
