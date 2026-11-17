import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { CurrentUser } from '@/types/api';
import { installUniMock } from '@/test/uni';

const api = vi.hoisted(() => ({
    login: vi.fn(),
    fetchMe: vi.fn(),
    logout: vi.fn(),
}));

vi.mock('@/api/auth', () => api);

const user: CurrentUser = {
    id: 1,
    username: 'operator1',
    name: '生产操作员1',
    email: 'operator1@mes.test',
    permissions: [],
    data_scope: 'all',
    menus: [],
};

async function loadAuth() {
    vi.resetModules();
    const requestModule = await import('@/api/request');
    const authModule = await import('./auth');

    return {
        ...authModule,
        ApiRequestError: requestModule.ApiRequestError,
    };
}

describe('auth store', () => {
    let uniMock: ReturnType<typeof installUniMock>;

    beforeEach(() => {
        uniMock = installUniMock();
        api.login.mockReset();
        api.fetchMe.mockReset();
        api.logout.mockReset();
    });

    it('restores the user and keeps the guard waiting until /me resolves', async () => {
        uniMock.storage.set('mes.mobile.token', 'token-1');
        let resolveMe!: (value: CurrentUser) => void;
        api.fetchMe.mockReturnValue(
            new Promise<CurrentUser>((resolve) => {
                resolveMe = resolve;
            }),
        );

        const { requireAuth, restoreSession, session } = await loadAuth();
        const restore = restoreSession();

        expect(session.status).toBe('restoring');

        const guard = requireAuth();
        await Promise.resolve();
        expect(uniMock.reLaunch).not.toHaveBeenCalled();

        resolveMe(user);

        await expect(guard).resolves.toBe(true);
        await restore;
        expect(session.status).toBe('ready');
        expect(session.user).toEqual(user);
        expect(api.fetchMe).toHaveBeenCalledTimes(1);
    });

    it('clears an expired token on 401 during restore', async () => {
        uniMock.storage.set('mes.mobile.token', 'expired');

        const { ApiRequestError, restoreSession, session } = await loadAuth();
        api.fetchMe.mockRejectedValue(new ApiRequestError('Unauthenticated.', 401, 401));

        await restoreSession();

        expect(session.token).toBeNull();
        expect(session.status).toBe('anonymous');
        expect(uniMock.storage.has('mes.mobile.token')).toBe(false);
    });

    it('keeps the local token when /me fails for a network reason', async () => {
        uniMock.storage.set('mes.mobile.token', 'token-1');

        const { ApiRequestError, restoreSession, session } = await loadAuth();
        api.fetchMe.mockRejectedValue(new ApiRequestError('网络错误', 0, 0));

        await restoreSession();

        expect(session.token).toBe('token-1');
        expect(session.status).toBe('ready');
    });

    it('persists the token and user on sign in', async () => {
        api.login.mockResolvedValue({ token: 'new-token', user });

        const { session, signIn } = await loadAuth();

        await signIn({ email: 'operator1@mes.test', password: 'password' });

        expect(api.login).toHaveBeenCalledWith({
            email: 'operator1@mes.test',
            password: 'password',
        });
        expect(session.token).toBe('new-token');
        expect(session.user).toEqual(user);
        expect(uniMock.storage.get('mes.mobile.token')).toBe('new-token');
    });

    it('clears local state even when the logout request fails', async () => {
        uniMock.storage.set('mes.mobile.token', 'token-1');
        api.logout.mockRejectedValue(new Error('network down'));

        const { session, signOut } = await loadAuth();

        await signOut();

        expect(session.token).toBeNull();
        expect(session.status).toBe('anonymous');
        expect(uniMock.storage.has('mes.mobile.token')).toBe(false);
    });

    it('redirects to login when there is no token', async () => {
        const { requireAuth } = await loadAuth();

        await expect(requireAuth()).resolves.toBe(false);
        expect(uniMock.reLaunch).toHaveBeenCalledWith({ url: '/pages/login/index' });
    });

    it('does not fetch /me twice after a successful restore', async () => {
        uniMock.storage.set('mes.mobile.token', 'token-1');
        api.fetchMe.mockResolvedValue(user);

        const { restoreSession } = await loadAuth();

        await restoreSession();
        await restoreSession();

        expect(api.fetchMe).toHaveBeenCalledTimes(1);
    });
});
