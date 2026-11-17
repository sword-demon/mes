import { ApiRequestError } from '@/api/request';
import { fetchMe, login as requestLogin, logout as requestLogout } from '@/api/auth';
import {
    clearSession,
    getToken,
    hydrateSession,
    session,
    setSessionStatus,
    setSession,
    updateSessionUser,
} from '@/stores/session';
import type { LoginPayload } from '@/types/api';
import { redirectToLogin } from '@/utils/navigation';

export { session };

let restorePromise: Promise<void> | null = null;

export function restoreSession(): Promise<void> {
    hydrateSession();

    if (restorePromise) {
        return restorePromise;
    }

    if (!getToken()) {
        setSessionStatus('anonymous');

        return Promise.resolve();
    }

    if (session.status === 'ready' && session.user) {
        return Promise.resolve();
    }

    const tokenAtStart = session.token;
    setSessionStatus('restoring');

    restorePromise = fetchMe()
        .then((user) => {
            if (getToken() !== tokenAtStart) {
                return;
            }

            updateSessionUser(user);
            setSessionStatus('ready');
        })
        .catch((error: unknown) => {
            if (getToken() !== tokenAtStart) {
                return;
            }

            if (error instanceof ApiRequestError && error.status === 401) {
                clearSession();

                return;
            }

            // 网络异常时保留本地 Token，页面仍可展示本地登录态。
            setSessionStatus('ready');
        })
        .finally(() => {
            restorePromise = null;
        });

    return restorePromise;
}

export async function signIn(payload: LoginPayload): Promise<void> {
    const result = await requestLogin(payload);

    setSession(result.token, result.user);
}

export async function signOut(): Promise<void> {
    try {
        if (getToken()) {
            await requestLogout();
        }
    } catch {
        // 本地退出不依赖网络是否可达。
    } finally {
        clearSession();
        restorePromise = null;
    }
}

export async function requireAuth(): Promise<boolean> {
    await restoreSession();

    if (session.token) {
        return true;
    }

    redirectToLogin();

    return false;
}
