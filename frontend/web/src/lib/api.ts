import { clearToken, getToken } from './auth';

const baseUrl = import.meta.env.VITE_API_URL ?? '/api/v1';

type ApiError = {
    message: string;
    errors?: Record<string, string[]>;
};

async function request<T>(
    method: string,
    path: string,
    body?: unknown,
): Promise<T> {
    const headers: Record<string, string> = {
        Accept: 'application/json',
    };

    if (body !== undefined) {
        headers['Content-Type'] = 'application/json';
    }

    const token = getToken();

    if (token) {
        headers.Authorization = `Bearer ${token}`;
    }

    const response = await fetch(`${baseUrl}${path}`, {
        method,
        headers,
        body: body === undefined ? undefined : JSON.stringify(body),
    });

    if (response.status === 401) {
        clearToken();
    }

    if (!response.ok) {
        const payload = (await response.json().catch(() => null)) as ApiError | null;

        throw new Error(payload?.message ?? '请求失败');
    }

    if (response.status === 204) {
        return undefined as T;
    }

    return (await response.json()) as T;
}

export const api = {
    get: <T>(path: string) => request<T>('GET', path),
    post: <T>(path: string, body?: unknown) => request<T>('POST', path, body),
    delete: <T>(path: string) => request<T>('DELETE', path),
};
