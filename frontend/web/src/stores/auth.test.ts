import { beforeEach, describe, expect, it } from 'vitest';
import { useAuthStore } from './auth';
import type { CurrentUser } from '@/types/api';

const user: CurrentUser = {
    id: 1,
    username: 'zhang',
    name: '张工',
    email: 'zhang@example.com',
    phone: null,
    department_id: null,
    position: null,
    status: true,
    email_verified_at: null,
    created_at: '2026-11-15T00:00:00Z',
    updated_at: '2026-11-15T00:00:00Z',
    permissions: ['menu.dashboard'],
    data_scope: 'line',
    menus: [],
};

describe('auth store', () => {
    beforeEach(() => {
        useAuthStore.setState({ token: null, user: null });
    });

    it('keeps the user returned by the login response', () => {
        useAuthStore.getState().signIn('token-1', user);

        expect(useAuthStore.getState()).toMatchObject({ token: 'token-1', user });
    });

    it('drops the previous user when a new token arrives without one', () => {
        useAuthStore.setState({ token: 'old', user });

        useAuthStore.getState().signIn('new', null);

        expect(useAuthStore.getState()).toMatchObject({ token: 'new', user: null });
    });

    it('clears the whole auth state', () => {
        useAuthStore.setState({ token: 'token-1', user });

        useAuthStore.getState().clearAuth();

        expect(useAuthStore.getState()).toMatchObject({ token: null, user: null });
    });

    it('persists the token so a page reload stays signed in', () => {
        useAuthStore.getState().signIn('token-9', user);

        const stored = JSON.parse(localStorage.getItem('mes.auth') ?? '{}');

        expect(stored.state).toMatchObject({ token: 'token-9', user });
    });
});
