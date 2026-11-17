import { api } from './client';
import type { Bom, BomItem, Paginated, Process } from '@/types/api';

export type BasicDataParams = {
    page?: number;
    keyword?: string;
    status?: boolean;
    per_page?: number;
    factory_id?: number;
    workshop_id?: number;
    team_id?: number;
    department_id?: number;
};

export type NamedRef = { id: number; code: string; name: string };

export type Factory = {
    id: number;
    code: string;
    name: string;
    address: string | null;
    contact: string | null;
    phone: string | null;
    status: boolean;
};

export type Workshop = {
    id: number;
    factory_id: number;
    code: string;
    name: string;
    manager: string | null;
    status: boolean;
    factory?: NamedRef;
};

export type ProductionLine = {
    id: number;
    workshop_id: number;
    code: string;
    name: string;
    capacity: number;
    manager: string | null;
    status: boolean;
    workshop?: NamedRef;
};

export type Team = {
    id: number;
    workshop_id: number;
    code: string;
    name: string;
    leader: string | null;
    status: boolean;
    workshop?: NamedRef;
};

export type Employee = {
    id: number;
    employee_no: string;
    name: string;
    department_id: number | null;
    team_id: number | null;
    position: string | null;
    skill_level: string | null;
    phone: string | null;
    status: boolean;
    department?: NamedRef;
    team?: NamedRef;
};

export type FactoryPayload = {
    code: string;
    name: string;
    address?: string | null;
    contact?: string | null;
    phone?: string | null;
    status?: boolean;
};

export type WorkshopPayload = {
    factory_id: number;
    code: string;
    name: string;
    manager?: string | null;
    status?: boolean;
};

export type ProductionLinePayload = {
    workshop_id: number;
    code: string;
    name: string;
    capacity?: number;
    manager?: string | null;
    status?: boolean;
};

export type TeamPayload = {
    workshop_id: number;
    code: string;
    name: string;
    leader?: string | null;
    status?: boolean;
};

export type EmployeePayload = {
    employee_no: string;
    name: string;
    department_id?: number | null;
    team_id?: number | null;
    position?: string | null;
    skill_level?: string | null;
    phone?: string | null;
    status?: boolean;
};

export function listFactories(params: BasicDataParams = {}) {
    return api.get<Paginated<Factory>>('/basic-data/factories', params);
}

export function createFactory(payload: FactoryPayload) {
    return api.post<Factory>('/basic-data/factories', payload);
}

export function updateFactory(id: number, payload: Partial<FactoryPayload>) {
    return api.put<Factory>(`/basic-data/factories/${id}`, payload);
}

export function deleteFactory(id: number) {
    return api.delete<void>(`/basic-data/factories/${id}`);
}

export function listWorkshops(params: BasicDataParams = {}) {
    return api.get<Paginated<Workshop>>('/basic-data/workshops', params);
}

export function createWorkshop(payload: WorkshopPayload) {
    return api.post<Workshop>('/basic-data/workshops', payload);
}

export function updateWorkshop(id: number, payload: Partial<WorkshopPayload>) {
    return api.put<Workshop>(`/basic-data/workshops/${id}`, payload);
}

export function deleteWorkshop(id: number) {
    return api.delete<void>(`/basic-data/workshops/${id}`);
}

export function listProductionLines(params: BasicDataParams = {}) {
    return api.get<Paginated<ProductionLine>>('/basic-data/production-lines', params);
}

export function createProductionLine(payload: ProductionLinePayload) {
    return api.post<ProductionLine>('/basic-data/production-lines', payload);
}

export function updateProductionLine(id: number, payload: Partial<ProductionLinePayload>) {
    return api.put<ProductionLine>(`/basic-data/production-lines/${id}`, payload);
}

export function deleteProductionLine(id: number) {
    return api.delete<void>(`/basic-data/production-lines/${id}`);
}

export function listTeams(params: BasicDataParams = {}) {
    return api.get<Paginated<Team>>('/basic-data/teams', params);
}

export function createTeam(payload: TeamPayload) {
    return api.post<Team>('/basic-data/teams', payload);
}

export function updateTeam(id: number, payload: Partial<TeamPayload>) {
    return api.put<Team>(`/basic-data/teams/${id}`, payload);
}

export function deleteTeam(id: number) {
    return api.delete<void>(`/basic-data/teams/${id}`);
}

export function listEmployees(params: BasicDataParams = {}) {
    return api.get<Paginated<Employee>>('/basic-data/employees', params);
}

export function listProcesses(params: BasicDataParams = {}) {
    return api.get<Paginated<Process>>('/basic-data/processes', params);
}

export type ProcessPayload = {
    code: string;
    name: string;
    type: string;
    standard_hours?: number;
    requires_inspection?: boolean;
    description?: string | null;
    status?: boolean;
};

export function createProcess(payload: ProcessPayload) {
    return api.post<Process>('/basic-data/processes', payload);
}

export function updateProcess(id: number, payload: Partial<ProcessPayload>) {
    return api.put<Process>(`/basic-data/processes/${id}`, payload);
}

export type BomPayload = { code: string; product_version_id: number; version: string; remark?: string | null; status?: boolean };
export type BomItemPayload = { material_id: number; quantity: number; loss_rate?: number };

export function listBoms(params: BasicDataParams & { product_version_id?: number; audit_status?: string } = {}) { return api.get<Paginated<Bom>>('/basic-data/boms', params); }
export function createBom(payload: BomPayload) { return api.post<Bom>('/basic-data/boms', payload); }
export function updateBom(id: number, payload: Partial<BomPayload>) { return api.put<Bom>(`/basic-data/boms/${id}`, payload); }
export function submitBom(id: number) { return api.post<Bom>(`/basic-data/boms/${id}/submit`); }
export function approveBom(id: number) { return api.post<Bom>(`/basic-data/boms/${id}/approve`); }
export function listBomItems(id: number, page = 1) { return api.get<Paginated<BomItem>>(`/basic-data/boms/${id}/items`, { page, per_page: 20 }); }
export function createBomItem(id: number, payload: BomItemPayload) { return api.post<BomItem>(`/basic-data/boms/${id}/items`, payload); }
export function updateBomItem(bomId: number, id: number, payload: Partial<BomItemPayload>) { return api.put<BomItem>(`/basic-data/boms/${bomId}/items/${id}`, payload); }
export function deleteBomItem(bomId: number, id: number) { return api.delete<void>(`/basic-data/boms/${bomId}/items/${id}`); }

export function createEmployee(payload: EmployeePayload) {
    return api.post<Employee>('/basic-data/employees', payload);
}

export function updateEmployee(id: number, payload: Partial<EmployeePayload>) {
    return api.put<Employee>(`/basic-data/employees/${id}`, payload);
}

export function deleteEmployee(id: number) {
    return api.delete<void>(`/basic-data/employees/${id}`);
}
