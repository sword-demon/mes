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

export type CurrentUser = User & {
    permissions: string[];
    data_scope: string;
    menus: unknown[];
};

export type LoginPayload = {
    email: string;
    password: string;
};

export type LoginResult = {
    token: string;
    user: User;
};
