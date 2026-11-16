import { api } from './client';
import type {
    DepartmentNode,
    OperationLog,
    OperationLogOptions,
    Paginated,
    Role,
    User,
} from '@/types/api';

export type UserPayload = {
    username?: string | null;
    name: string;
    email: string;
    password: string;
    phone?: string | null;
    employee_id?: number | null;
    status?: boolean;
    role_ids?: number[];
};

export type UserUpdatePayload = Partial<Omit<UserPayload, 'password'>>;

export function listUsers(params: { page?: number; keyword?: string } = {}) {
    return api.get<Paginated<User>>('/system/users', params);
}

export function listRoles() {
    return api.get<Paginated<Role>>('/system/roles');
}

export function createUser(payload: UserPayload) {
    return api.post<User>('/system/users', payload);
}

export function updateUser(id: number, payload: UserUpdatePayload) {
    return api.put<User>(`/system/users/${id}`, payload);
}

export function resetUserPassword(id: number, password: string) {
    return api.put<void>(`/system/users/${id}/password`, { password });
}

export function assignUserRoles(id: number, roleIds: number[]) {
    return api.put<User>(`/system/users/${id}/roles`, { role_ids: roleIds });
}

export function listDepartments() {
    return api.get<DepartmentNode[]>('/system/departments');
}

export function listOperationLogs(params: {
    page?: number;
    user_id?: number;
    action?: string;
    start_date?: string;
    end_date?: string;
} = {}) {
    return api.get<Paginated<OperationLog>>('/system/operation-logs', params);
}

export function getOperationLogOptions() {
    return api.get<OperationLogOptions>('/system/operation-logs/options');
}
