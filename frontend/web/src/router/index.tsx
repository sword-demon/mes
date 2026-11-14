import type { ReactNode } from 'react';
import { createBrowserRouter, Navigate } from 'react-router';
import AdminLayout from '@/layouts/admin-layout';
import Dashboard from '@/pages/dashboard';
import Login from '@/pages/login';
import { useAuthStore } from '@/stores/auth';

function RequireAuth({ children }: { children: ReactNode }) {
    const token = useAuthStore((state) => state.token);

    return token ? children : <Navigate to="/login" replace />;
}

export const router = createBrowserRouter([
    { path: '/login', element: <Login /> },
    {
        path: '/',
        element: (
            <RequireAuth>
                <AdminLayout />
            </RequireAuth>
        ),
        children: [
            { index: true, element: <Dashboard /> },
            { path: '*', element: <Navigate to="/" replace /> },
        ],
    },
]);
