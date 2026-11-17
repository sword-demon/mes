import { beforeEach, describe, expect, it, vi } from 'vitest';
import { installUniMock } from '@/test/uni';

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

async function loadRequest(): Promise<typeof import('./request')> {
    vi.resetModules();
    return import('./request');
}

describe('request', () => {
    let uniMock: ReturnType<typeof installUniMock>;

    beforeEach(() => {
        uniMock = installUniMock();
    });

    it('unwraps the envelope and injects the stored token', async () => {
        uniMock.storage.set('mes.mobile.token', 'token-1');
        uniMock.request.mockImplementation((options: RequestOptions) => {
            options.success({
                statusCode: 200,
                data: { code: 200, message: 'ok', data: { id: 7 }, timestamp: 1 },
                header: {},
                cookies: [],
            });
        });

        const { request } = await loadRequest();

        await expect(request<{ id: number }>({ url: '/me' })).resolves.toEqual({ id: 7 });
        expect(uniMock.request).toHaveBeenCalledWith(
            expect.objectContaining({
                url: '/api/v1/me',
                header: expect.objectContaining({
                    Authorization: 'Bearer token-1',
                    'Content-Type': 'application/json',
                }),
            }),
        );
    });

    it('clears the session and redirects to login on 401', async () => {
        uniMock.storage.set('mes.mobile.token', 'expired');
        uniMock.request.mockImplementation((options: RequestOptions) => {
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
        expect(uniMock.storage.has('mes.mobile.token')).toBe(false);
        expect(uniMock.reLaunch).toHaveBeenCalledWith({ url: '/pages/login/index' });
    });

    it('rejects business errors with field errors intact', async () => {
        uniMock.request.mockImplementation((options: RequestOptions) => {
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
