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
};

export type LoginResult = {
    token: string;
    user: User;
};
