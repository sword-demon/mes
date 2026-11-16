import { api } from './client';
import type { DefectiveProduct, InspectionTask } from './quality';

export type TraceEmployee = { id: number; employee_no: string; name: string };

export type TraceEquipment = {
    id: number;
    code: string;
    name: string;
    type: string;
    status: string;
    status_label: string;
};

export type TraceMaterial = {
    id: number;
    code: string;
    name: string;
    unit: string;
    batch_managed: boolean;
};

export type TraceProcess = {
    id: number;
    code: string;
    name: string;
    requires_inspection: boolean;
};

export type TraceRouteOperation = {
    id: number;
    sequence: number;
    standard_hours: number;
    parameters: string | null;
    process: TraceProcess;
};

export type TraceProcessRecord = {
    id: number;
    sequence: number;
    sn: string | null;
    status: string;
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
    route_operation: {
        id: number;
        sequence: number;
        process: TraceProcess;
    } | null;
    employee: TraceEmployee | null;
    team: { id: number; code: string; name: string } | null;
    equipment: TraceEquipment | null;
};

export type TraceMaterialUsage = {
    material: TraceMaterial;
    quantity: number;
    loss_rate: number;
    issued_quantity: string;
};

export type TraceInspection = InspectionTask & { scope: 'sn' | 'order' };

export type TraceDefect = DefectiveProduct & { scope: 'batch' };

export type ForwardTrace = {
    has_data: boolean;
    message: string | null;
    sn: {
        id: number;
        sn: string;
        status: string;
        status_label: string;
        material_batch_no: string | null;
        printed_at: string | null;
        voided_at: string | null;
        void_reason: string | null;
        employee: TraceEmployee | null;
        equipment: TraceEquipment | null;
        created_at: string;
    };
    product: {
        id: number;
        code: string;
        name: string;
        model: string | null;
        specification: string | null;
        category: string;
        unit: string;
    };
    production_order: {
        id: number;
        code: string;
        status: string;
        status_label: string;
        planned_quantity: string;
        produced_quantity: string;
        qualified_quantity: string;
        defective_quantity: string;
        planned_start_at: string;
        planned_end_at: string | null;
        bom_version: string;
        route_version: string;
        production_line: { id: number; code: string; name: string };
        product_version: { id: number; version: string };
    };
    production_batch: { id: number; code: string; quantity: number };
    bom: {
        id: number;
        code: string;
        version: string;
        audit_status: string;
    };
    materials: TraceMaterialUsage[];
    route: {
        id: number;
        code: string;
        version: string;
        audit_status: string;
        operations: TraceRouteOperation[];
    };
    process_records: TraceProcessRecord[];
    inspections: TraceInspection[];
    defects: TraceDefect[];
};

export function getForwardTrace(sn: string) {
    return api.get<ForwardTrace>(`/traceability/sn/${encodeURIComponent(sn)}`);
}
