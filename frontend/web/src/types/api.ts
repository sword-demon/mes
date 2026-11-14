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
    position: string | null;
    status: boolean;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    roles?: Role[];
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
