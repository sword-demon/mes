import { api } from './client';
import type { LoginPayload, LoginResult, User } from '@/types/api';
import { ApiRequestError } from '@/utils/api-error';

export function login(payload: LoginPayload): Promise<LoginResult> {
    return api.post<LoginResult>('/auth/tokens', payload);
}

export function fetchMe(): Promise<User> {
    return api.get<User>('/me');
}

export function logout(): Promise<void> {
    return api.delete<void>('/auth/tokens');
}

export function loginErrorMessage(error: unknown): string {
    if (!(error instanceof ApiRequestError)) {
        return error instanceof Error ? error.message : '登录失败';
    }

    const errors = error.errors ?? {};
    const firstFieldError = Object.values(errors).flat()[0];

    // The API reports bad credentials as a validation error on `email`, so a
    // non-empty email error means the password was wrong, not the input shape.
    if (errors.email?.length) {
        return '邮箱或密码错误';
    }

    return firstFieldError ?? error.message;
}
