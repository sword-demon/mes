import { beforeEach, describe, expect, it, vi } from 'vitest';

type RequestOptions = {
    url: string;
    method?: string;
    data?: unknown;
    header?: Record<string, string>;
    success: (response: {
        statusCode: number;
        data: unknown;
        header: Record<string, string>;
        cookies: string[];
    }) => void;
    fail: (error: { errMsg: string }) => void;
};

const storage = new Map<string, unknown>();
const requestMock = vi.fn();
const reLaunchMock = vi.fn();

function installUniMock(): void {
    Object.defineProperty(globalThis, 'getCurrentPages', {
        configurable: true,
        value: () => [],
    });
    Object.defineProperty(globalThis, 'uni', {
        configurable: true,
        value: {
            getStorageSync: (key: string) => storage.get(key) ?? '',
            setStorageSync: (key: string, value: unknown) => storage.set(key, value),
            removeStorageSync: (key: string) => storage.delete(key),
            request: requestMock,
            reLaunch: reLaunchMock,
            getCurrentPages: () => [],
        },
    });
}

async function loadRequest(): Promise<typeof import('./request')> {
    vi.resetModules();
    return import('./request');
}

describe('request', () => {
    beforeEach(() => {
        storage.clear();
        requestMock.mockReset();
        reLaunchMock.mockReset();
        installUniMock();
    });

    it('unwraps the envelope and injects the stored token', async () => {
        storage.set('mes.mobile.token', 'token-1');
        requestMock.mockImplementation((options: RequestOptions) => {
            options.success({
                statusCode: 200,
                data: { code: 200, message: 'ok', data: { id: 7 }, timestamp: 1 },
                header: {},
                cookies: [],
            });
        });

        const { request } = await loadRequest();

        await expect(request<{ id: number }>({ url: '/me' })).resolves.toEqual({ id: 7 });
        expect(requestMock).toHaveBeenCalledWith(
            expect.objectContaining({
                url: '/api/v1/me',
                header: expect.objectContaining({ Authorization: 'Bearer token-1' }),
            }),
        );
    });

    it('clears the session and redirects to login on 401', async () => {
        storage.set('mes.mobile.token', 'expired');
        requestMock.mockImplementation((options: RequestOptions) => {
            options.success({
                statusCode: 401,
                data: { code: 401, message: 'Unauthenticated.', data: null, timestamp: 1 },
                header: {},
                cookies: [],
            });
        });

        const { request } = await loadRequest();

        await expect(request({ url: '/me' })).rejects.toMatchObject({
            status: 401,
            message: 'Unauthenticated.',
        });
        expect(storage.has('mes.mobile.token')).toBe(false);
        expect(reLaunchMock).toHaveBeenCalledWith({ url: '/pages/login/index' });
    });

    it('rejects business errors with field errors intact', async () => {
        requestMock.mockImplementation((options: RequestOptions) => {
            options.success({
                statusCode: 422,
                data: {
                    code: 422,
                    message: '邮箱或密码错误',
                    data: { errors: { email: ['邮箱或密码错误'] } },
                    timestamp: 1,
                },
                header: {},
                cookies: [],
            });
        });

        const { request, ApiRequestError } = await loadRequest();

        const failure = request({ url: '/auth/tokens', method: 'POST' });

        await expect(failure).rejects.toBeInstanceOf(ApiRequestError);
        await expect(failure).rejects.toMatchObject({
            status: 422,
            code: 422,
            message: '邮箱或密码错误',
            errors: { email: ['邮箱或密码错误'] },
        });
    });
});
