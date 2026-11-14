export type ApiEnvelope<T> = {
    code: number;
    message: string;
    data: T;
    timestamp: number;
};

export type User = {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
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
