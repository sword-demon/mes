import { api } from './client';
import type { Paginated } from '@/types/api';

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

export type NamedRef = { id: number; code: string; name: string };

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
    route_operation?: {
        id: number;
        sequence: number;
        process: { id: number; code: string; name: string };
    };
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
