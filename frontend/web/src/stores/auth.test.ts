import { beforeEach, describe, expect, it } from 'vitest';
import { useAuthStore } from './auth';
import { currentUserFixture } from '@/test/fixtures';

const user = currentUserFixture({
    username: 'zhang',
    name: '张工',
    email: 'zhang@example.com',
    permissions: ['menu.dashboard'],
    data_scope: 'line',
});

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

        expect(stored.state).toMatchObject({ token: 'token-9' });
    });

    it('does not persist the user, so a reload never renders stale grants', () => {
        useAuthStore.getState().signIn('token-9', user);

        const stored = JSON.parse(localStorage.getItem('mes.auth') ?? '{}');

        expect(stored.state.user ?? null).toBeNull();
    });

    it('drops a user stored by the previous persist version', async () => {
        localStorage.setItem(
            'mes.auth',
            JSON.stringify({
                state: { token: 'legacy-token', user },
                version: 0,
            }),
        );

        await useAuthStore.persist.rehydrate();

        expect(useAuthStore.getState().token).toBe('legacy-token');
        expect(useAuthStore.getState().user).toBeNull();
    });
});
