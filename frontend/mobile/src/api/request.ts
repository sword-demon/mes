import { clearSession, getToken } from '@/stores/session';
import type { ApiEnvelope } from '@/types/api';
import { redirectToLogin } from '@/utils/navigation';

const API_BASE = import.meta.env.VITE_API_URL || '/api/v1';

type RequestMethod = 'GET' | 'POST' | 'PUT' | 'DELETE';

type RequestOptions = {
    url: string;
    method?: RequestMethod;
    data?: string | Record<string, unknown> | ArrayBuffer;
};

type ErrorEnvelope = {
    code?: number;
    message?: string;
    data?: {
        errors?: Record<string, string[]>;
    } | null;
};

// 移动端与 Web 独立构建，错误类型各自维护，避免跨工程运行依赖。
export class ApiRequestError extends Error {
    public constructor(
        message: string,
        public readonly status: number,
        public readonly code: number = status,
        public readonly errors?: Record<string, string[]>,
    ) {
        super(message);
        this.name = 'ApiRequestError';
    }
}

function resolveUrl(path: string): string {
    return `${API_BASE.replace(/\/$/, '')}${path.startsWith('/') ? path : `/${path}`}`;
}

function buildError(status: number, envelope: ErrorEnvelope): ApiRequestError {
    return new ApiRequestError(
        envelope.message ?? (status === 0 ? '无法连接服务器，请检查网络后重试' : '请求失败'),
        status,
        envelope.code ?? status,
        envelope.data?.errors,
    );
}

export function request<T>(options: RequestOptions): Promise<T> {
    return new Promise((resolve, reject) => {
        const token = getToken();
        const header: Record<string, string> = {
            Accept: 'application/json',
            'Content-Type': 'application/json',
        };

        if (token) {
            header.Authorization = `Bearer ${token}`;
        }

        uni.request({
            url: resolveUrl(options.url),
            method: options.method ?? 'GET',
            data: options.data,
            header,
            success: (response) => {
                const status = response.statusCode;
                const envelope = (
                    typeof response.data === 'object' && response.data !== null ? response.data : {}
                ) as ApiEnvelope<T> & ErrorEnvelope;

                if (status === 401) {
                    clearSession();
                    redirectToLogin();
                }

                if (status >= 200 && status < 300 && envelope.code === 200) {
                    resolve(envelope.data);

                    return;
                }

                reject(buildError(status, envelope));
            },
            fail: () => {
                reject(new ApiRequestError('无法连接服务器，请检查网络后重试', 0, 0));
            },
        });
    });
}
