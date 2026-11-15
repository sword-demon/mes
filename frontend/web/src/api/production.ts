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

export function listApprovedBoms() {
    // shortcut: one page covers a demo factory's approved BOMs; add paging when it does not.
    return api.get<Paginated<ApprovedBom>>('/basic-data/boms', {
        status: true,
        audit_status: 'approved',
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
