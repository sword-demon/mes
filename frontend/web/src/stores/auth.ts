import { create } from 'zustand';
import { persist } from 'zustand/middleware';
import type { User } from '@/types/api';

type AuthState = {
    token: string | null;
    user: User | null;
    signIn: (token: string, user: User | null) => void;
    setUser: (user: User) => void;
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
