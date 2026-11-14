import type { ReactNode } from 'react';
import { createBrowserRouter, Navigate, useLocation } from 'react-router';
import Dashboard from '@/pages/dashboard';
import Forbidden from '@/pages/forbidden';
import AdminLayout from '@/layouts/admin-layout';
import Login from '@/pages/login';
import Placeholder from '@/pages/placeholder';
import Users from '@/pages/system/users';
import { useAuthStore } from '@/stores/auth';
import { findMenu, firstMenuPath } from '@/utils/permission';

function RequireAuth({ children }: { children: ReactNode }) {
    const token = useAuthStore((state) => state.token);

    return token ? children : <Navigate to="/login" replace />;
}

/**
 * Pages already implemented; every other menu path renders a placeholder.
 */
const pages: Record<string, () => ReactNode> = {
    '/dashboard/overview': Dashboard,
    '/system/users': Users,
};

/**
 * The permission guard: a path is reachable only when `/me` returned a menu
 * node for it, which the backend only includes for granted permissions.
 * Anything else redirects to `/403` so the URL matches what is on screen.
 */
function MenuPage() {
    const { pathname } = useLocation();
    const user = useAuthStore((state) => state.user);

    // The layout already renders a spinner until `/me` lands.
    if (!user) {
        return null;
    }

    const menu = findMenu(user.menus, pathname);

    if (!menu) {
        return <Navigate to="/403" replace />;
    }

    const Page = pages[pathname];

    return Page ? <Page /> : <Placeholder title={menu.name} />;
}

function HomeRedirect() {
    const user = useAuthStore((state) => state.user);

    if (!user) {
        return null;
    }

    const home = firstMenuPath(user.menus);

    return home ? <Navigate to={home} replace /> : <Navigate to="/403" replace />;
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
            { index: true, element: <HomeRedirect /> },
            { path: '403', element: <Forbidden /> },
            { path: '*', element: <MenuPage /> },
        ],
    },
]);
