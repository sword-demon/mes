import { fetchMe, login as requestLogin, logout as requestLogout } from '@/api/auth';
import { LOGIN_PAGE, REPORT_PAGE, SCAN_PAGE } from '@/constants/routes';
import {
    clearSession,
    getToken,
    hydrateSession,
    session,
    setSession,
    updateSessionUser,
} from '@/stores/session';
import type { LoginPayload } from '@/types/api';

export { session };

export function restoreSession(): void {
    hydrateSession();

    if (!getToken()) {
        return;
    }

    void fetchMe()
        .then((user) => {
            updateSessionUser(user);
        })
        .catch(() => {
            // 请求层在 401 时会清理登录态；网络异常时保留本地 Token。
        });
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
    }
}

export function requireAuth(): boolean {
    if (getToken()) {
        return true;
    }

    uni.reLaunch({ url: LOGIN_PAGE });

    return false;
}

export function goToReport(): void {
    uni.reLaunch({ url: REPORT_PAGE });
}

export function goToScan(): void {
    uni.navigateTo({ url: SCAN_PAGE });
}
