import { beforeEach, describe, expect, it } from 'vitest';
import { useAuthStore } from './auth';
import type { User } from '@/types/api';

const user: User = {
    id: 1,
    name: '张工',
    email: 'zhang@example.com',
    email_verified_at: null,
    created_at: '2026-11-15T00:00:00Z',
    updated_at: '2026-11-15T00:00:00Z',
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
});
