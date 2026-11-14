import { create } from 'zustand';
import { persist } from 'zustand/middleware';
import type { User } from '@/types/api';

type AuthState = {
    token: string | null;
    user: User | null;
    setToken: (token: string) => void;
    setUser: (user: User) => void;
    clear: () => void;
};

export const useAuthStore = create<AuthState>()(
    persist(
        (set) => ({
            token: null,
            user: null,
            setToken: (token) => set({ token, user: null }),
            setUser: (user) => set({ user }),
            clear: () => set({ token: null, user: null }),
        }),
        { name: 'mes.auth' },
    ),
);
