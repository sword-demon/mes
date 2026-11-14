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
        { name: 'mes.auth' },
    ),
);
