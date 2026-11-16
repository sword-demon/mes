import { api } from './client';
import type { EquipmentAlarm, EquipmentStatus } from './equipment';
import type {
    ProductionException,
    ProductionOrder,
    ProductionReport,
} from './production';

export type DashboardToday = {
    date: string;
    order_count: number;
    planned_quantity: string;
    produced_quantity: string;
    qualified_quantity: string;
    defective_quantity: string;
    running_equipment_count: number;
};

export type DashboardTrendItem = {
    date: string;
    quantity: string;
};

export type DashboardTrend = {
    days: number;
    items: DashboardTrendItem[];
};

export type DashboardLineItem = {
    production_line_id: number;
    code: string;
    name: string;
    quantity: string;
};

export type DashboardLineComparison = {
    items: DashboardLineItem[];
};

export type DashboardDefectReason = {
    defect_code_id: number;
    code: string;
    name: string;
    category: string | null;
    quantity: string;
};

export type DashboardQuality = {
    qualified_quantity: string;
    defective_quantity: string;
    total_quantity: string;
    qualified_rate: string;
    defect_reasons: DashboardDefectReason[];
};

export type DashboardEquipmentStatusItem = {
    status: EquipmentStatus;
    label: string;
    count: number;
};

export type DashboardEquipmentStatus = {
    items: DashboardEquipmentStatusItem[];
};

export type DashboardList<T> = {
    items: T[];
};

export function getDashboardToday() {
    return api.get<DashboardToday>('/dashboard/today');
}

export function getDashboardTrend() {
    return api.get<DashboardTrend>('/dashboard/production-trend');
}

export function getDashboardLineComparison() {
    return api.get<DashboardLineComparison>('/dashboard/production-lines');
}

export function getDashboardQuality() {
    return api.get<DashboardQuality>('/dashboard/quality');
}

export function getDashboardEquipmentStatus() {
    return api.get<DashboardEquipmentStatus>('/dashboard/equipment-status');
}

export function getLatestOrders() {
    return api.get<DashboardList<ProductionOrder>>('/dashboard/latest-orders');
}

export function getLatestReports() {
    return api.get<DashboardList<ProductionReport>>('/dashboard/latest-reports');
}

export function getLatestExceptions() {
    return api.get<DashboardList<ProductionException>>('/dashboard/latest-exceptions');
}

export function getLatestAlarms() {
    return api.get<DashboardList<EquipmentAlarm>>('/dashboard/latest-alarms');
}
