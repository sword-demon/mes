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
