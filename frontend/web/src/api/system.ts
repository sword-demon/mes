import { api } from './client';
import type {
    DepartmentNode,
    LoginLog,
    MenuNode,
    OperationLog,
    OperationLogOptions,
    Paginated,
    Permission,
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

export type RolePayload = {
    code: string;
    name: string;
    description?: string | null;
    status?: boolean;
    permission_ids?: number[];
};

export type MenuPayload = {
    parent_id?: number | null;
    permission_id?: number | null;
    name: string;
    path?: string | null;
    icon?: string | null;
    sort?: number;
};

export type DepartmentPayload = {
    parent_id?: number | null;
    code: string;
    name: string;
    sort?: number;
};

export function listUsers(params: { page?: number; keyword?: string } = {}) {
    return api.get<Paginated<User>>('/system/users', params);
}

export function listRoles(params: { page?: number } = {}) {
    return api.get<Paginated<Role>>('/system/roles', params);
}

export function createRole(payload: RolePayload) {
    return api.post<Role>('/system/roles', payload);
}

export function updateRole(id: number, payload: Partial<RolePayload>) {
    return api.put<Role>(`/system/roles/${id}`, payload);
}

export function deleteRole(id: number) {
    return api.delete<void>(`/system/roles/${id}`);
}

export function listPermissions() {
    return api.get<Permission[]>('/system/permissions');
}

export function listMenus() {
    return api.get<MenuNode[]>('/system/menus');
}

export function createMenu(payload: MenuPayload) {
    return api.post<MenuNode>('/system/menus', payload);
}

export function updateMenu(id: number, payload: Partial<MenuPayload>) {
    return api.put<MenuNode>(`/system/menus/${id}`, payload);
}

export function deleteMenu(id: number) {
    return api.delete<void>(`/system/menus/${id}`);
}

export function createDepartment(payload: DepartmentPayload) {
    return api.post<DepartmentNode>('/system/departments', payload);
}

export function updateDepartment(id: number, payload: Partial<DepartmentPayload>) {
    return api.put<DepartmentNode>(`/system/departments/${id}`, payload);
}

export function deleteDepartment(id: number) {
    return api.delete<void>(`/system/departments/${id}`);
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

export function listLoginLogs(params: {
    page?: number;
    account?: string;
    success?: boolean;
    start_date?: string;
    end_date?: string;
} = {}) {
    return api.get<Paginated<LoginLog>>('/system/login-logs', params);
}
