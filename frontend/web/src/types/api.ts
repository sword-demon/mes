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
