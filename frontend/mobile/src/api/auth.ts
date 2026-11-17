import { ApiRequestError, request } from './request';
import type { CurrentUser, LoginPayload, LoginResult } from '@/types/api';

export function login(payload: LoginPayload): Promise<LoginResult> {
    return request<LoginResult>({
        url: '/auth/tokens',
        method: 'POST',
        data: {
            ...payload,
            device_name: 'mobile',
        },
    });
}

export function fetchMe(): Promise<CurrentUser> {
    return request<CurrentUser>({ url: '/me' });
}

export function logout(): Promise<void> {
    return request<void>({ url: '/auth/tokens', method: 'DELETE' });
}

export function loginErrorMessage(error: unknown): string {
    if (!(error instanceof ApiRequestError)) {
        return error instanceof Error ? error.message : '登录失败';
    }

    const errors = error.errors ?? {};
    const firstFieldError = Object.values(errors).flat()[0];

    if (errors.email?.length) {
        return '邮箱或密码错误';
    }

    return firstFieldError ?? error.message;
}
