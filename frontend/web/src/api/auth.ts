import { api } from './client';
import type { LoginPayload, LoginResult, User } from '@/types/api';

export function login(payload: LoginPayload): Promise<LoginResult> {
    return api.post<LoginResult>('/auth/tokens', payload);
}

export function fetchMe(): Promise<User> {
    return api.get<User>('/me');
}

export function logout(): Promise<void> {
    return api.delete<void>('/auth/tokens');
}
