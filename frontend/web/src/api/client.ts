import axios from 'axios';
import type { AxiosResponse } from 'axios';
import { useAuthStore } from '@/stores/auth';
import type { ApiEnvelope } from '@/types/api';
import { ApiRequestError } from '@/utils/api-error';

type ErrorPayload = {
    message?: string;
    data?: { errors?: Record<string, string[]> };
};

const http = axios.create({
    baseURL: import.meta.env.VITE_API_URL ?? '/api/v1',
    headers: { Accept: 'application/json' },
});

http.interceptors.request.use((config) => {
    const token = useAuthStore.getState().token;

    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }

    return config;
});

http.interceptors.response.use(undefined, (error: unknown) => {
    if (!axios.isAxiosError(error)) {
        return Promise.reject(error);
    }

    const status = error.response?.status ?? 0;
    const payload = error.response?.data as ErrorPayload | undefined;

    if (status === 401) {
        useAuthStore.getState().clear();

        if (window.location.pathname !== '/login') {
            window.location.assign('/login');
        }
    }

    const message =
        payload?.message ??
        (status === 0 ? '无法连接服务器，请检查网络后重试' : '请求失败');

    return Promise.reject(
        new ApiRequestError(message, status, payload?.data?.errors),
    );
});

async function unwrap<T>(
    request: Promise<AxiosResponse<ApiEnvelope<T>>>,
): Promise<T> {
    const response = await request;

    return response.data.data;
}

export const api = {
    get: <T>(url: string) => unwrap(http.get<ApiEnvelope<T>>(url)),
    post: <T>(url: string, body?: unknown) =>
        unwrap(http.post<ApiEnvelope<T>>(url, body)),
    delete: <T>(url: string) => unwrap(http.delete<ApiEnvelope<T>>(url)),
};
