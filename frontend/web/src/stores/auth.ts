import { create } from 'zustand';
import { persist } from 'zustand/middleware';
import type { CurrentUser } from '@/types/api';

type AuthState = {
    token: string | null;
    user: CurrentUser | null;
    signIn: (token: string, user: CurrentUser | null) => void;
    setUser: (user: CurrentUser) => void;
    clearAuth: () => void;
};

export const useAuthStore = create<AuthState>()(
    persist(
        (set) => ({
            token: null,
            user: null,
            signIn: (token, user) => set({ token, user }),
            setUser: (user) => set({ user }),
            clearAuth: () => set({ token: null, user: null }),
        }),
        {
            name: 'mes.auth',
            // Only the token is durable: the user carries permissions and
            // menus, and a stale copy would flash revoked actions on reload
            // until `/me` answers.
            partialize: (state) => ({ token: state.token }),
            // Version 0 stored the whole user; drop it so existing sessions
            // stop rehydrating stale grants.
            version: 1,
            migrate: (persisted) => ({
                token: (persisted as { token?: string | null } | undefined)?.token ?? null,
            }),
        },
    ),
);
