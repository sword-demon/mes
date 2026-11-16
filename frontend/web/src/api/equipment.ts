import { api } from './client';
import type { Paginated } from '@/types/api';

export const EQUIPMENT_STATUSES = [
    { value: 'running', label: '运行', color: 'processing' },
    { value: 'idle', label: '待机', color: 'default' },
    { value: 'fault', label: '故障', color: 'red' },
    { value: 'repair', label: '维修', color: 'orange' },
    { value: 'offline', label: '离线', color: 'default' },
] as const;

export type EquipmentStatus = (typeof EQUIPMENT_STATUSES)[number]['value'];

export const EQUIPMENT_TYPES = [
    'SMT 贴片机',
    '回流焊设备',
    'CNC 加工中心',
    '自动组装设备',
    '功能测试设备',
] as const;

export type EquipmentType = (typeof EQUIPMENT_TYPES)[number];

export type Equipment = {
    id: number;
    code: string;
    name: string;
    type: EquipmentType;
    model: string;
    status: EquipmentStatus;
    status_label: string;
    enabled: boolean;
    workshop_id: number;
    workshop?: { id: number; code: string; name: string };
    production_line_id: number;
    production_line?: { id: number; code: string; name: string };
    owner_id: number | null;
    owner?: { id: number; employee_no: string; name: string } | null;
    created_at: string;
    updated_at: string;
};

export type EquipmentOption = Pick<
    Equipment,
    'id' | 'code' | 'name' | 'type' | 'model' | 'status' | 'status_label' | 'production_line_id'
>;

export type EquipmentParams = {
    page?: number;
    per_page?: number;
    keyword?: string;
    workshop_id?: number;
    production_line_id?: number;
    type?: EquipmentType;
    status?: EquipmentStatus;
};

export type EquipmentPayload = {
    code: string;
    name: string;
    type: EquipmentType;
    workshop_id: number;
    production_line_id: number;
    model: string;
    status?: EquipmentStatus;
    enabled?: boolean;
    owner_id: number;
};

export type EquipmentOptions = {
    workshops: { id: number; code: string; name: string }[];
    production_lines: { id: number; workshop_id: number; code: string; name: string }[];
    employees: { id: number; employee_no: string; name: string }[];
    equipments: Equipment[];
};

export type EquipmentMetric = {
    recorded_at: string;
    temperature: number;
    speed: number;
    pressure: number;
    output: number;
    runtime_seconds: number | null;
};

export type EquipmentHistorySample = {
    recorded_at: string;
    temperature: number;
    speed: number;
    pressure: number;
    output: number;
};

export type MonitorEquipment = Equipment & {
    latest_data?: EquipmentMetric | null;
};

export type EquipmentMonitorData = {
    items: MonitorEquipment[];
    status_counts?: Record<EquipmentStatus, number>;
    max_history_seconds: number;
};

export type EquipmentHistoryData = {
    equipment: Pick<Equipment, 'id' | 'code' | 'name' | 'status' | 'status_label'>;
    start_at: string;
    end_at: string;
    items: EquipmentHistorySample[];
};

export type EquipmentHistoryParams = {
    start_at: string;
    end_at: string;
};

export const EQUIPMENT_ALARM_LEVELS = [
    { value: 'info', label: '提示', color: 'blue' },
    { value: 'warning', label: '警告', color: 'orange' },
    { value: 'critical', label: '严重', color: 'red' },
] as const;

export type EquipmentAlarmLevel = (typeof EQUIPMENT_ALARM_LEVELS)[number]['value'];

export const EQUIPMENT_ALARM_STATUSES = [
    { value: 'pending', label: '待确认', color: 'red' },
    { value: 'confirmed', label: '已确认', color: 'orange' },
    { value: 'processing', label: '处理中', color: 'blue' },
    { value: 'closed', label: '已关闭', color: 'green' },
] as const;

export type EquipmentAlarmStatus = (typeof EQUIPMENT_ALARM_STATUSES)[number]['value'];
export type EquipmentAlarmMetric = 'temperature' | 'speed' | 'pressure';

export type EquipmentAlarm = {
    id: number;
    code: string;
    equipment_id: number;
    equipment_data_id: number | null;
    metric: EquipmentAlarmMetric | null;
    metric_label: string;
    metric_unit: string;
    alarm_code: string | null;
    message: string | null;
    level: EquipmentAlarmLevel;
    level_label: string;
    status: EquipmentAlarmStatus;
    status_label: string;
    threshold: number | null;
    measured_value: number | null;
    handling_note: string | null;
    confirmed_by: { id: number; name: string } | null;
    confirmed_at: string | null;
    handled_by: { id: number; name: string } | null;
    handled_at: string | null;
    closed_at: string | null;
    triggered_at: string;
    equipment: Pick<Equipment, 'id' | 'code' | 'name' | 'type' | 'status' | 'status_label'>;
    created_at: string;
    updated_at: string;
};

export type EquipmentAlarmParams = {
    page?: number;
    per_page?: number;
    equipment_id?: number;
    level?: EquipmentAlarmLevel;
    status?: EquipmentAlarmStatus;
    start_date?: string;
    end_date?: string;
};

export type EquipmentAlarmOptions = {
    equipments: { id: number; code: string; name: string; status: EquipmentStatus; status_label: string }[];
};

export function listEquipments(params: EquipmentParams = {}) {
    return api.get<Paginated<Equipment>>('/equipments', params);
}

export function getEquipmentOptions() {
    return api.get<EquipmentOptions>('/equipments/options');
}

export function createEquipment(payload: EquipmentPayload) {
    return api.post<Equipment>('/equipments', payload);
}

export function updateEquipment(id: number, payload: Partial<EquipmentPayload>) {
    return api.put<Equipment>(`/equipments/${id}`, payload);
}

export function getEquipmentMonitor() {
    return api.get<EquipmentMonitorData>('/equipments/monitor');
}

export function getEquipmentHistory(id: number, params: EquipmentHistoryParams) {
    return api.get<EquipmentHistoryData>(`/equipments/${id}/history`, params);
}

export function listEquipmentAlarms(params: EquipmentAlarmParams = {}) {
    return api.get<Paginated<EquipmentAlarm>>('/equipments/alarms', params);
}

export function getEquipmentAlarmOptions() {
    return api.get<EquipmentAlarmOptions>('/equipments/alarms/options');
}

export function confirmEquipmentAlarm(id: number) {
    return api.post<EquipmentAlarm>(`/equipments/alarms/${id}/confirm`);
}

export function processEquipmentAlarm(id: number, handlingNote: string) {
    return api.post<EquipmentAlarm>(`/equipments/alarms/${id}/process`, { handling_note: handlingNote });
}

export function closeEquipmentAlarm(id: number, handlingNote?: string) {
    return api.post<EquipmentAlarm>(`/equipments/alarms/${id}/close`, { handling_note: handlingNote });
}
