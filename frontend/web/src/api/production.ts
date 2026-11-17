import { api } from './client';
import type { Paginated } from '@/types/api';
import type { Equipment, EquipmentOption } from './equipment';

export const PRODUCTION_ORDER_STATUSES = [
    { value: 'draft', label: '草稿', color: 'default' },
    { value: 'pending', label: '待审核', color: 'gold' },
    { value: 'approved', label: '已审核', color: 'blue' },
    { value: 'released', label: '已下达', color: 'cyan' },
    { value: 'in_progress', label: '生产中', color: 'processing' },
    { value: 'paused', label: '已暂停', color: 'orange' },
    { value: 'completed', label: '已完成', color: 'green' },
    { value: 'closed', label: '已关闭', color: 'default' },
    { value: 'cancelled', label: '已取消', color: 'red' },
] as const;

export type ProductionOrderStatus = (typeof PRODUCTION_ORDER_STATUSES)[number]['value'];

export const PRODUCTION_TASK_STATUSES = [
    { value: 'pending', label: '待分配', color: 'default' },
    { value: 'in_progress', label: '生产中', color: 'processing' },
    { value: 'paused', label: '已暂停', color: 'orange' },
    { value: 'completed', label: '已完成', color: 'green' },
] as const;

export type ProductionTaskStatus = (typeof PRODUCTION_TASK_STATUSES)[number]['value'];

export type ProductionOrderAction =
    | 'submit'
    | 'approve'
    | 'release'
    | 'start'
    | 'pause'
    | 'resume'
    | 'complete'
    | 'close'
    | 'cancel';

export const PROCESS_RECORD_STATUSES = [
    { value: 'pending', label: '待加工', color: 'default' },
    { value: 'processing', label: '加工中', color: 'processing' },
    { value: 'inspecting', label: '待检验', color: 'gold' },
    { value: 'inspected', label: '检验通过', color: 'cyan' },
    { value: 'pending_transfer', label: '待流转', color: 'orange' },
    { value: 'transferred', label: '已流转', color: 'green' },
    { value: 'reworking', label: '返工中', color: 'red' },
] as const;

export type ProcessRecordStatus = (typeof PROCESS_RECORD_STATUSES)[number]['value'];

export type ProcessRecord = {
    id: number;
    production_task_id: number;
    route_operation_id: number;
    sequence: number;
    sn: string | null;
    employee_id: number | null;
    team_id: number | null;
    equipment_id: number | null;
    status: ProcessRecordStatus;
    status_label: string;
    started_at: string | null;
    completed_at: string | null;
    inspected_at: string | null;
    submitted_at: string | null;
    received_at: string | null;
    exception_reason: string | null;
    exception_at: string | null;
    rework_reason: string | null;
    rework_at: string | null;
    rework_count: number;
    production_task?: { id: number; code: string; status: string; status_label: string };
    production_order?: { id: number; code: string };
    route_operation?: {
        id: number;
        sequence: number;
        process: { id: number; code: string; name: string; requires_inspection: boolean };
    };
    employee?: { id: number; employee_no: string; name: string } | null;
    team?: { id: number; code: string; name: string } | null;
    equipment?: Equipment | null;
};

export type ProcessRecordParams = {
    page?: number;
    per_page?: number;
    production_order_id?: number;
    production_task_id?: number;
    status?: ProcessRecordStatus;
    sn?: string;
};

export type ProcessRecordStartPayload = {
    employee_id?: number | null;
    equipment_id?: number | null;
    sn?: string | null;
};

export type NamedRef = { id: number; code: string; name: string };
export type RouteOperationRef = { id: number; sequence: number; process: NamedRef };

export type ProductionOrder = {
    id: number;
    code: string;
    product_id: number;
    product_version_id: number;
    bom_id: number;
    route_id: number;
    production_line_id: number;
    bom_version: string;
    route_version: string;
    planned_quantity: string;
    produced_quantity: string;
    qualified_quantity: string;
    defective_quantity: string;
    planned_start_at: string;
    planned_end_at: string | null;
    status: ProductionOrderStatus;
    status_label: string;
    remark: string | null;
    product?: NamedRef;
    product_version?: { id: number; version: string };
    bom?: { id: number; code: string; version: string };
    route?: { id: number; code: string; version: string };
    production_line?: NamedRef;
};

export type ProductionOrderParams = {
    page?: number;
    per_page?: number;
    keyword?: string;
    status?: ProductionOrderStatus;
    product_id?: number;
    production_line_id?: number;
    start_date?: string;
    end_date?: string;
};

export type ProductionOrderPayload = {
    code: string;
    product_id: number;
    bom_id: number;
    route_id: number;
    production_line_id: number;
    planned_quantity: number;
    planned_start_at: string;
    planned_end_at: string | null;
    remark?: string | null;
};

export type ProductionTask = {
    id: number;
    code: string;
    production_order_id: number;
    product_id: number;
    route_id: number;
    production_line_id: number | null;
    team_id: number | null;
    owner_id: number | null;
    planned_quantity: string;
    produced_quantity: string;
    qualified_quantity: string;
    defective_quantity: string;
    progress_percent: number;
    planned_start_at: string;
    planned_end_at: string | null;
    status: ProductionTaskStatus;
    status_label: string;
    production_order?: { id: number; code: string; status: ProductionOrderStatus };
    product?: NamedRef;
    route?: { id: number; code: string; version: string };
    production_line?: NamedRef | null;
    team?: NamedRef | null;
    owner?: { id: number; employee_no: string; name: string } | null;
};

export type ProductionTaskParams = {
    page?: number;
    per_page?: number;
    keyword?: string;
    status?: ProductionTaskStatus;
    production_order_id?: number;
    production_line_id?: number;
    team_id?: number;
    start_date?: string;
    end_date?: string;
};

export type ProductionTaskPayload = {
    planned_quantity: number;
    planned_start_at?: string | null;
    planned_end_at?: string | null;
};

export type ProductionTaskAssignment = {
    production_line_id: number;
    team_id: number;
    owner_id: number;
};

export type ProductionDispatch = {
    id: number;
    code: string;
    production_task_id: number;
    route_operation_id: number;
    employee_id: number;
    team_id: number;
    dispatched_at: string;
    production_task?: {
        id: number;
        code: string;
        production_order_id: number;
        planned_quantity: string;
        produced_quantity: string;
        progress_percent: number;
        planned_start_at: string;
        planned_end_at: string | null;
        status: ProductionTaskStatus;
        status_label: string;
    };
    production_order?: { id: number; code: string };
    route_operation?: RouteOperationRef;
    employee?: { id: number; employee_no: string; name: string };
    team?: NamedRef;
};

export type ProductionDispatchParams = {
    page?: number;
    per_page?: number;
    keyword?: string;
    production_task_id?: number;
    production_order_id?: number;
    employee_id?: number;
    start_date?: string;
    end_date?: string;
};

export type ProductionDispatchPayload = {
    production_task_id: number;
    route_operation_id: number;
    employee_id: number;
    team_id: number;
    dispatched_at?: string;
};

export const PRODUCTION_REPORT_TYPES = [
    { value: 'normal', label: '正常', color: 'blue' },
    { value: 'batch', label: '批量', color: 'cyan' },
    { value: 'reversed', label: '已冲销', color: 'default' },
] as const;

export type ProductionReportType = (typeof PRODUCTION_REPORT_TYPES)[number]['value'];

export type ProductionReport = {
    id: number;
    code: string;
    production_task_id: number;
    route_operation_id: number;
    employee_id: number;
    team_id: number | null;
    equipment_id: number | null;
    quantity: string;
    qualified_quantity: string;
    defective_quantity: string;
    type: ProductionReportType;
    type_label: string;
    reported_at: string;
    reversed_at: string | null;
    production_task?: {
        id: number;
        code: string;
        production_order_id: number;
        planned_quantity: string;
        produced_quantity: string;
        progress_percent: number;
        status: ProductionTaskStatus;
        status_label: string;
    };
    production_order?: { id: number; code: string };
    product?: NamedRef;
    route_operation?: RouteOperationRef;
    employee?: { id: number; employee_no: string; name: string };
    team?: NamedRef | null;
    equipment?: Equipment | null;
};

export type ProductionReportParams = {
    page?: number;
    per_page?: number;
    keyword?: string;
    production_task_id?: number;
    production_order_id?: number;
    employee_id?: number;
    type?: ProductionReportType;
    start_date?: string;
    end_date?: string;
};

export type ProductionReportPayload = {
    production_task_id: number;
    route_operation_id: number;
    employee_id: number;
    equipment_id?: number | null;
    quantity: number;
    qualified_quantity: number;
    defective_quantity: number;
    type?: 'normal' | 'batch';
    reported_at?: string;
};

export type ProductionReportOptions = {
    tasks: {
        id: number;
        code: string;
        planned_quantity: string;
        produced_quantity: string;
        remaining_quantity: string;
        product: NamedRef | null;
        production_order: { id: number; code: string } | null;
        production_line_id: number | null;
    }[];
    employees: { id: number; employee_no: string; name: string }[];
    equipments: EquipmentOption[];
};

export const PRODUCT_SN_STATUSES = [
    { value: 'active', label: '在用', color: 'green' },
    { value: 'voided', label: '已作废', color: 'red' },
] as const;

export type ProductSnStatus = (typeof PRODUCT_SN_STATUSES)[number]['value'];

export type ProductSnProcessRecord = {
    id: number;
    sequence: number;
    status: string;
    status_label: string;
    equipment_id: number | null;
    started_at: string | null;
    completed_at: string | null;
    received_at: string | null;
    rework_count: number;
    route_operation: RouteOperationRef | null;
    employee: { id: number; employee_no: string; name: string } | null;
};

export type ProductSn = {
    id: number;
    sn: string;
    production_order_id: number;
    production_batch_id: number;
    material_batch_no: string | null;
    equipment_id: number | null;
    employee_id: number | null;
    status: ProductSnStatus;
    status_label: string;
    printed_at: string | null;
    voided_at: string | null;
    void_reason: string | null;
    production_order?: {
        id: number;
        code: string;
        planned_quantity: string;
        product: NamedRef | null;
    };
    production_batch?: { id: number; code: string; quantity: number };
    employee?: { id: number; employee_no: string; name: string } | null;
    process_records?: ProductSnProcessRecord[];
    created_at: string;
};

export type ProductSnParams = {
    page?: number;
    per_page?: number;
    keyword?: string;
    status?: ProductSnStatus;
    production_order_id?: number;
    production_batch_id?: number;
};

export type ProductSnGeneratePayload = {
    production_order_id: number;
    quantity: number;
    employee_id?: number;
};

export type ProductSnBindPayload = {
    material_batch_no?: string;
    equipment_id?: number;
    employee_id?: number;
};

export type ProductSnGenerateResult = {
    batch: { id: number; code: string; quantity: number; production_order_id: number };
    items: ProductSn[];
};

export type ProductSnOptions = {
    orders: {
        id: number;
        code: string;
        planned_quantity: string;
        product: NamedRef | null;
    }[];
    employees: { id: number; employee_no: string; name: string }[];
};

export type ProductSnLabel = {
    sn: string;
    product_code: string;
    product_name: string;
    production_order: string;
    production_batch: string;
    generated_at: string;
};

export type TaskRouteOperation = {
    id: number;
    route_id: number;
    sequence: number;
    process: { id: number; code: string; name: string };
};

export type ApprovedBom = {
    id: number;
    code: string;
    version: string;
    product_version: { id: number; version: string; product: NamedRef };
};

export type UsableRoute = {
    id: number;
    code: string;
    version: string;
    product_id: number;
};

export function listOrders(params: ProductionOrderParams = {}) {
    return api.get<Paginated<ProductionOrder>>('/production/orders', params);
}

export function createOrder(payload: ProductionOrderPayload) {
    return api.post<ProductionOrder>('/production/orders', payload);
}

export function updateOrder(id: number, payload: Partial<ProductionOrderPayload>) {
    return api.put<ProductionOrder>(`/production/orders/${id}`, payload);
}

export function transitionOrder(id: number, action: ProductionOrderAction) {
    return api.post<ProductionOrder>(`/production/orders/${id}/${action}`);
}

export function listTasks(params: ProductionTaskParams = {}) {
    return api.get<Paginated<ProductionTask>>('/production/tasks', params);
}

export function splitTask(orderId: number, payload: ProductionTaskPayload) {
    return api.post<ProductionTask>(`/production/orders/${orderId}/tasks`, payload);
}

export function assignTask(id: number, payload: ProductionTaskAssignment) {
    return api.put<ProductionTask>(`/production/tasks/${id}`, payload);
}

export function transitionTask(id: number, action: 'pause' | 'resume' | 'complete') {
    return api.post<ProductionTask>(`/production/tasks/${id}/${action}`);
}

export function listDispatches(params: ProductionDispatchParams = {}) {
    return api.get<Paginated<ProductionDispatch>>('/production/dispatches', params);
}

export function listMyDispatches(params: { page?: number; per_page?: number } = {}) {
    return api.get<Paginated<ProductionDispatch>>('/production/dispatches/mine', params);
}

export function createDispatch(payload: ProductionDispatchPayload) {
    return api.post<ProductionDispatch>('/production/dispatches', payload);
}

export function listTaskOperations(taskId: number) {
    return api.get<TaskRouteOperation[]>(`/production/tasks/${taskId}/operations`);
}

export function listReports(params: ProductionReportParams = {}) {
    return api.get<Paginated<ProductionReport>>('/production/reports', params);
}

export function createReport(payload: ProductionReportPayload) {
    return api.post<ProductionReport>('/production/reports', payload);
}

export function reverseReport(id: number) {
    return api.post<ProductionReport>(`/production/reports/${id}/reverse`);
}

export function getReportOptions() {
    return api.get<ProductionReportOptions>('/production/reports/options');
}

export function listReportOperations(taskId: number) {
    return api.get<TaskRouteOperation[]>(`/production/reports/tasks/${taskId}/operations`);
}

export function listProductSns(params: ProductSnParams = {}) {
    return api.get<Paginated<ProductSn>>('/production/sns', params);
}

export function getProductSnOptions() {
    return api.get<ProductSnOptions>('/production/sns/options');
}

export function generateProductSns(payload: ProductSnGeneratePayload) {
    return api.post<ProductSnGenerateResult>('/production/sns', payload);
}

export function getProductSn(sn: string) {
    return api.get<ProductSn>(`/production/sns/${sn}`);
}

export function bindProductSn(sn: string, payload: ProductSnBindPayload) {
    return api.put<ProductSn>(`/production/sns/${sn}/bind`, payload);
}

export function getProductSnLabel(sn: string) {
    return api.get<{ sn: ProductSn; label: ProductSnLabel }>(`/production/sns/${sn}/label`);
}

export function printProductSn(sn: string) {
    return api.post<ProductSn>(`/production/sns/${sn}/print`);
}

export function voidProductSn(sn: string, reason: string) {
    return api.post<ProductSn>(`/production/sns/${sn}/void`, { reason });
}

export function listApprovedBoms() {
    // shortcut: one page covers a demo factory's approved BOMs; add paging when it does not.
    return api.get<Paginated<ApprovedBom>>('/basic-data/boms', {
        usable: 1,
        per_page: 100,
    });
}

export function listUsableRoutes(productId: number) {
    return api.get<Paginated<UsableRoute>>('/process/routes', {
        usable: 1,
        product_id: productId,
        per_page: 100,
    });
}

export const PRODUCTION_EXCEPTION_TYPES = [
    { value: 'equipment', label: '设备异常', color: 'red' },
    { value: 'material', label: '物料异常', color: 'gold' },
    { value: 'process', label: '工艺异常', color: 'blue' },
    { value: 'quality', label: '质量异常', color: 'purple' },
    { value: 'personnel', label: '人员异常', color: 'orange' },
] as const;

export type ProductionExceptionType = (typeof PRODUCTION_EXCEPTION_TYPES)[number]['value'];

export const PRODUCTION_EXCEPTION_STATUSES = [
    { value: 'reported', label: '已上报', color: 'red' },
    { value: 'processing', label: '处理中', color: 'blue' },
    { value: 'closed', label: '已关闭', color: 'green' },
] as const;

export type ProductionExceptionStatus = (typeof PRODUCTION_EXCEPTION_STATUSES)[number]['value'];

export type ProductionExceptionOrderRef = {
    id: number;
    code: string;
    product: NamedRef;
};

export type ProductionExceptionProcessRecordRef = {
    id: number;
    sequence: number;
    sn: string | null;
    production_order?: { id: number; code: string };
    route_operation: RouteOperationRef;
};

export type ProductionExceptionImage = { id: number; original_name: string | null; url: string };

export type ProductionException = {
    id: number;
    code: string;
    type: ProductionExceptionType;
    type_label: string;
    status: ProductionExceptionStatus;
    status_label: string;
    description: string;
    handling_result: string | null;
    production_order: ProductionExceptionOrderRef | null;
    process_record: ProductionExceptionProcessRecordRef | null;
    reporter: { id: number; name: string } | null;
    handler: { id: number; name: string } | null;
    images: ProductionExceptionImage[];
    reported_at: string;
    handled_at: string | null;
    closed_at: string | null;
    created_at: string;
    updated_at: string;
};

export type ProductionExceptionParams = {
    page?: number;
    per_page?: number;
    type?: ProductionExceptionType;
    status?: ProductionExceptionStatus;
    production_order_id?: number;
    start_date?: string;
    end_date?: string;
};

export type ProductionExceptionPayload = {
    type: ProductionExceptionType;
    production_order_id?: number | null;
    process_record_id?: number | null;
    description: string;
};

export type ProductionExceptionOptions = {
    orders: ProductionExceptionOrderRef[];
    process_records: ProductionExceptionProcessRecordRef[];
};

export type ProductionExceptionOptionInclude = 'all' | 'orders' | 'process_records';

export function listProductionExceptions(params: ProductionExceptionParams = {}) {
    return api.get<Paginated<ProductionException>>('/production/exceptions', params);
}

export function getProductionExceptionOptions(
    productionOrderId?: number,
    keyword?: string,
    include: ProductionExceptionOptionInclude = 'all',
) {
    return api.get<ProductionExceptionOptions>('/production/exceptions/options', {
        ...(productionOrderId === undefined ? {} : { production_order_id: productionOrderId }),
        // An empty keyword is no filter, so it is left out of the request.
        ...(keyword ? { keyword } : {}),
        include,
    });
}

export function reportProductionException(payload: ProductionExceptionPayload, files: File[]) {
    const form = new FormData();
    form.append('type', payload.type);
    form.append('description', payload.description);

    if (payload.production_order_id !== undefined && payload.production_order_id !== null) {
        form.append('production_order_id', String(payload.production_order_id));
    }

    if (payload.process_record_id !== undefined && payload.process_record_id !== null) {
        form.append('process_record_id', String(payload.process_record_id));
    }

    files.forEach((file) => form.append('images[]', file));

    return api.post<ProductionException>('/production/exceptions', form);
}

export function processProductionException(id: number, handlingResult: string) {
    return api.post<ProductionException>(`/production/exceptions/${id}/process`, {
        handling_result: handlingResult,
    });
}

export function closeProductionException(id: number) {
    return api.post<ProductionException>(`/production/exceptions/${id}/close`);
}

export function listProcessRecords(params: ProcessRecordParams = {}) {
    return api.get<Paginated<ProcessRecord>>('/production/process-records', params);
}

export function startProcessRecord(id: number, payload: ProcessRecordStartPayload) {
    return api.post<ProcessRecord>(`/production/process-records/${id}/start`, payload);
}

export function completeProcessRecord(id: number) {
    return api.post<ProcessRecord>(`/production/process-records/${id}/complete`);
}

export function submitProcessRecord(id: number) {
    return api.post<ProcessRecord>(`/production/process-records/${id}/submit`);
}

export function receiveProcessRecord(id: number) {
    return api.post<ProcessRecord>(`/production/process-records/${id}/receive`);
}

export function reportProcessRecordException(id: number, reason: string) {
    return api.post<ProcessRecord>(`/production/process-records/${id}/exception`, { reason });
}

export function reworkProcessRecord(id: number, reason: string) {
    return api.post<ProcessRecord>(`/production/process-records/${id}/rework`, { reason });
}
