import { reactive } from 'vue';
import type { User } from '@/types/api';

export const TOKEN_STORAGE_KEY = 'mes.mobile.token';

export type SessionStatus = 'idle' | 'restoring' | 'ready' | 'anonymous';

type SessionState = {
    token: string | null;
    user: User | null;
    status: SessionStatus;
};

export const session = reactive<SessionState>({
    token: null,
    user: null,
    status: 'idle',
});

let hydrated = false;

export function hydrateSession(): void {
    if (hydrated) {
        return;
    }

    hydrated = true;
    session.token = (uni.getStorageSync(TOKEN_STORAGE_KEY) as string) || null;
    session.status = session.token ? 'restoring' : 'anonymous';
}

export function getToken(): string | null {
    hydrateSession();

    return session.token;
}

export function setSession(token: string, user: User | null): void {
    hydrateSession();
    session.token = token;
    session.user = user;
    session.status = 'ready';
    uni.setStorageSync(TOKEN_STORAGE_KEY, token);
}

export function updateSessionUser(user: User | null): void {
    session.user = user;
}

export function setSessionStatus(status: SessionStatus): void {
    session.status = status;
}

export function clearSession(): void {
    hydrateSession();
    session.token = null;
    session.user = null;
    session.status = 'anonymous';
    uni.removeStorageSync(TOKEN_STORAGE_KEY);
}
