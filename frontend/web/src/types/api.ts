export type ApiEnvelope<T> = {
    code: number;
    message: string;
    data: T;
    timestamp: number;
};

export type User = {
    id: number;
    username: string;
    name: string;
    email: string;
    phone: string | null;
    department_id: number | null;
    employee_id: number | null;
    position: string | null;
    status: boolean;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    roles?: Role[];
    employee?: { id: number; employee_no: string; name: string } | null;
};

export type Role = {
    id: number;
    code: string;
    name: string;
    description: string | null;
    status: boolean;
    permissions?: Permission[];
};

export type Permission = {
    id: number;
    code: string;
    name: string;
    type: 'menu' | 'page' | 'button' | 'api' | 'data';
};

export type Paginated<T> = {
    items: T[];
    meta: {
        current_page: number;
        per_page: number;
        total: number;
        last_page: number;
    };
};

export type MenuNode = {
    id: number;
    parent_id: number | null;
    permission_id: number | null;
    name: string;
    path: string | null;
    icon: string | null;
    sort: number;
    children: MenuNode[];
};

export type DepartmentNode = {
    id: number;
    parent_id: number | null;
    code: string;
    name: string;
    sort: number;
    children: DepartmentNode[];
};

export type ProcessRoute = {
    id: number;
    code: string;
    product_id: number;
    version: string;
    audit_status: 'draft' | 'pending' | 'approved';
    audited_at: string | null;
    remark: string | null;
    status: boolean;
    product?: { id: number; code: string; name: string };
};

export type RouteOperation = {
    id: number;
    route_id: number;
    process_id: number;
    sequence: number;
    standard_hours: number;
    parameters: string | null;
    process?: {
        id: number;
        code: string;
        name: string;
        type: string;
        requires_inspection: boolean;
    };
};

export type Process = {
    id: number;
    code: string;
    name: string;
    type: string;
    standard_hours: number;
    requires_inspection: boolean;
    description: string | null;
    status: boolean;
};

export type Bom = {
    id: number; code: string; product_version_id: number; version: string;
    audit_status: 'draft' | 'pending' | 'approved'; audited_at: string | null;
    remark: string | null; status: boolean;
    product_version?: { id: number; version: string; product: { id: number; code: string; name: string } };
};

export type BomItem = {
    id: number; bom_id: number; material_id: number; quantity: string; loss_rate: string;
    material?: { id: number; code: string; name: string; unit: string };
};

export type CurrentUser = User & {
    permissions: string[];
    data_scope: 'all' | 'factory' | 'line' | 'none';
    menus: MenuNode[];
};

export type LoginPayload = {
    email: string;
    password: string;
    device_name?: string;
};

export type LoginResult = {
    token: string;
    user: User;
};

export type OperationLogChange = {
    before: unknown;
    after: unknown;
};

export type OperationLog = {
    id: number;
    user_id: number | null;
    user_name: string;
    action: string;
    action_name: string;
    object_type: string;
    object_id: number | null;
    object_name: string;
    summary: string;
    changes: Record<string, OperationLogChange> | null;
    ip: string;
    created_at: string;
};

export type OperationLogOptions = {
    users: { id: number; name: string }[];
    actions: { action: string; action_name: string }[];
};

export type LoginLog = {
    id: number;
    user_id: number | null;
    account: string;
    ip: string;
    success: boolean;
    message: string;
    created_at: string;
};
