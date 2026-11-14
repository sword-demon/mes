import type { AxiosRequestConfig } from 'axios';
import { beforeEach, describe, expect, it } from 'vitest';
import { api, http } from './client';
import { useAuthStore } from '@/stores/auth';
import { ApiRequestError } from '@/utils/api-error';

type Reply = { status: number; body?: unknown };

function replyWith(reply: Reply, seen?: AxiosRequestConfig[]) {
    http.defaults.adapter = async (config) => {
        seen?.push(config);

        const response = {
            data: reply.body,
            status: reply.status,
            statusText: '',
            headers: {},
            config,
        };

        if (reply.status >= 400) {
            throw Object.assign(new Error('Request failed'), {
                isAxiosError: true,
                config,
                response,
            });
        }

        return response;
    };
}

const envelope = (data: unknown, code = 200, message = 'success') => ({
    code,
    message,
    data,
    timestamp: 0,
});

describe('api client', () => {
    beforeEach(() => {
        useAuthStore.setState({ token: null, user: null });
        window.history.replaceState({}, '', '/');
    });

    it('sends the stored token as a bearer header', async () => {
        useAuthStore.setState({ token: 'token-123' });
        const seen: AxiosRequestConfig[] = [];
        replyWith({ status: 200, body: envelope({ id: 1 }) }, seen);

        await api.get('/me');

        expect(seen[0].headers?.Authorization).toBe('Bearer token-123');
    });

    it('omits the bearer header when signed out', async () => {
        const seen: AxiosRequestConfig[] = [];
        replyWith({ status: 200, body: envelope({ id: 1 }) }, seen);

        await api.get('/me');

        expect(seen[0].headers?.Authorization).toBeUndefined();
    });

    it('unwraps the envelope data on success', async () => {
        replyWith({ status: 200, body: envelope({ id: 7, name: 'MES' }) });

        await expect(api.get('/me')).resolves.toEqual({ id: 7, name: 'MES' });
    });

    it('throws with the business code when a 2xx envelope is not successful', async () => {
        replyWith({ status: 200, body: envelope(null, 4003, '业务失败') });

        const error = await api.get('/me').catch((caught: unknown) => caught);

        expect(error).toBeInstanceOf(ApiRequestError);
        expect((error as ApiRequestError).code).toBe(4003);
        expect((error as ApiRequestError).status).toBe(200);
        expect((error as ApiRequestError).message).toBe('业务失败');
    });

    it('keeps the http status and field errors for failed responses', async () => {
        replyWith({
            status: 422,
            body: {
                code: 422,
                message: 'The given data was invalid.',
                data: { errors: { email: ['auth.failed'] } },
            },
        });

        const error = await api
            .post('/auth/tokens', {})
            .catch((caught: unknown) => caught);

        expect(error).toBeInstanceOf(ApiRequestError);
        expect((error as ApiRequestError).status).toBe(422);
        expect((error as ApiRequestError).errors?.email).toEqual(['auth.failed']);
    });

    it('clears the auth state on 401 so the router can redirect', async () => {
        useAuthStore.setState({ token: 'stale', user: null });
        replyWith({ status: 401, body: envelope(null, 401, 'Unauthenticated.') });

        await api.get('/me').catch(() => undefined);

        expect(useAuthStore.getState().token).toBeNull();
        expect(window.location.pathname).toBe('/');
    });

    it('reports a network failure when no response arrives', async () => {
        http.defaults.adapter = async () => {
            throw Object.assign(new Error('Network Error'), { isAxiosError: true });
        };

        const error = await api.get('/me').catch((caught: unknown) => caught);

        expect((error as ApiRequestError).status).toBe(0);
        expect((error as ApiRequestError).message).toBe(
            '无法连接服务器，请检查网络后重试',
        );
    });
});
