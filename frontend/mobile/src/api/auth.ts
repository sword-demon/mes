import { request } from './request';
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
