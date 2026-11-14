import { clearToken, getToken } from './auth';

const baseUrl = import.meta.env.VITE_API_URL ?? '/api/v1';

type ApiError = {
    message: string;
    data?: { errors?: Record<string, string[]> };
};

type ApiEnvelope<T> = {
    code: number;
    message: string;
    data: T;
    timestamp: number;
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

    let response: Response;

    try {
        response = await fetch(`${baseUrl}${path}`, {
            method,
            headers,
            body: body === undefined ? undefined : JSON.stringify(body),
        });
    } catch {
        throw new ApiRequestError('无法连接服务器，请检查网络后重试', 0);
    }

    if (response.status === 401) {
        clearToken();
    }

    if (!response.ok) {
        const payload = (await response.json().catch(() => null)) as ApiError | null;

        throw new ApiRequestError(payload?.message ?? '请求失败', response.status);
    }

    if (response.status === 204) {
        return undefined as T;
    }

    const payload = (await response.json()) as ApiEnvelope<T>;

    return payload.data;
}

export class ApiRequestError extends Error {
    constructor(
        message: string,
        public readonly status: number,
    ) {
        super(message);
    }
}

export const api = {
    get: <T>(path: string) => request<T>('GET', path),
    post: <T>(path: string, body?: unknown) => request<T>('POST', path, body),
    delete: <T>(path: string) => request<T>('DELETE', path),
};
