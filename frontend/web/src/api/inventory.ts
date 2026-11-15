import { api } from './client';
import type { Paginated } from '@/types/api';

export type InventoryBusinessType = 'inbound' | 'outbound' | 'adjustment';

export type InventoryNamedRef = { id: number; code: string; name: string };

export type InventoryLocationRef = InventoryNamedRef & {
    warehouse: InventoryNamedRef | null;
};

export type InventoryMaterialRef = {
    id: number;
    code: string;
    name: string;
    unit: string;
    batch_managed?: boolean;
};

export type Inventory = {
    id: number;
    material_id: number;
    location_id: number;
    batch_no: string;
    current_quantity: number;
    frozen_quantity: number;
    available_quantity: number;
    material?: InventoryMaterialRef;
    location?: InventoryLocationRef;
    created_at: string;
    updated_at: string;
};

export type InventoryTransaction = {
    id: number;
    inventory_id: number;
    material_id: number;
    business_type: InventoryBusinessType;
    business_type_label: string;
    quantity: number;
    before_quantity: number;
    after_quantity: number;
    reference_no: string | null;
    remark: string | null;
    material?: InventoryMaterialRef;
    inventory?: {
        id: number;
        batch_no: string;
        location: InventoryLocationRef | null;
    };
    created_at: string;
};

export type InventoryParams = {
    page?: number;
    per_page?: number;
    warehouse_id?: number;
    location_id?: number;
    material_id?: number;
    batch_no?: string;
    keyword?: string;
};

export type InventoryTransactionParams = InventoryParams & {
    business_type?: InventoryBusinessType;
    reference_no?: string;
};

export type InventoryMovementPayload = {
    material_id: number;
    warehouse_id: number;
    location_id: number;
    batch_no?: string | null;
    quantity: number;
    reference_no?: string | null;
    remark?: string | null;
};

export type InventoryAdjustPayload = Omit<InventoryMovementPayload, 'quantity'> & {
    current_quantity: number;
};

export type InventoryOptions = {
    materials: InventoryMaterialRef[];
    warehouses: {
        id: number;
        code: string;
        name: string;
        locations: InventoryNamedRef[];
    }[];
};

export function listInventory(params: InventoryParams = {}) {
    return api.get<Paginated<Inventory>>('/inventory', params);
}

export function listInventoryTransactions(params: InventoryTransactionParams = {}) {
    return api.get<Paginated<InventoryTransaction>>('/inventory/transactions', params);
}

export function getInventoryOptions() {
    return api.get<InventoryOptions>('/inventory/options');
}

export function inboundInventory(payload: InventoryMovementPayload) {
    return api.post<Inventory>('/inventory/inbound', payload);
}

export function outboundInventory(payload: InventoryMovementPayload) {
    return api.post<Inventory>('/inventory/outbound', payload);
}

export function adjustInventory(payload: InventoryAdjustPayload) {
    return api.post<Inventory>('/inventory/adjust', payload);
}
