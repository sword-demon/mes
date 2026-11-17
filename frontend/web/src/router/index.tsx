import type { ReactNode } from 'react';
import { createBrowserRouter, Navigate, useLocation } from 'react-router';
import Dashboard from '@/pages/dashboard';
import Forbidden from '@/pages/forbidden';
import AdminLayout from '@/layouts/admin-layout';
import Employees from '@/pages/basic-data/employees';
import Factories from '@/pages/basic-data/factories';
import Materials from '@/pages/basic-data/materials';
import MaterialInbound from '@/pages/material/inbound';
import MaterialInventory from '@/pages/material/inventory';
import MaterialInventoryAlerts from '@/pages/material/inventory-alerts';
import MaterialOutbound from '@/pages/material/outbound';
import MaterialRequisitions from '@/pages/material/requisitions';
import MaterialReturns from '@/pages/material/returns';
import EquipmentLedger from '@/pages/equipment/ledger';
import EquipmentMonitor from '@/pages/equipment/monitor';
import EquipmentHistory from '@/pages/equipment/history';
import EquipmentAlarms from '@/pages/equipment/alarms';
import ProductionLines from '@/pages/basic-data/production-lines';
import Products from '@/pages/basic-data/products';
import Teams from '@/pages/basic-data/teams';
import Workshops from '@/pages/basic-data/workshops';
import Login from '@/pages/login';
import Placeholder from '@/pages/placeholder';
import ProductionExceptions from '@/pages/execution/exceptions';
import ProcessFlows from '@/pages/execution/flows';
import ProductSns from '@/pages/execution/sn';
import ProductionOrders from '@/pages/production/orders';
import ProductionDispatches from '@/pages/production/dispatch';
import ProductionReports from '@/pages/production/reports';
import ProductionTasks from '@/pages/production/tasks';
import Users from '@/pages/system/users';
import OperationLogs from '@/pages/system/logs';
import Roles from '@/pages/system/roles';
import Menus from '@/pages/system/menus';
import Departments from '@/pages/system/departments';
import QualityStandards from '@/pages/quality/standards';
import QualityRecords from '@/pages/quality/records';
import QualityTasks from '@/pages/quality/tasks';
import QualityDefects from '@/pages/quality/defects';
import BackwardTrace from '@/pages/traceability/backward';
import ForwardTrace from '@/pages/traceability/forward';
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
    '/dashboard/trend': () => <Navigate to="/dashboard/overview" replace />,
    '/dashboard/quality': () => <Navigate to="/dashboard/overview" replace />,
    '/dashboard/equipment': () => <Navigate to="/dashboard/overview" replace />,
    '/basic-data/factories': Factories,
    '/basic-data/workshops': Workshops,
    '/basic-data/lines': ProductionLines,
    '/basic-data/teams': Teams,
    '/basic-data/employees': Employees,
    '/basic-data/materials': Materials,
    '/material/inventory': MaterialInventory,
    '/material/inventory/alerts': MaterialInventoryAlerts,
    '/material/inbound': MaterialInbound,
    '/material/outbound': MaterialOutbound,
    '/material/requisitions': MaterialRequisitions,
    '/material/returns': MaterialReturns,
    '/equipment/ledger': EquipmentLedger,
    '/equipment/monitor': EquipmentMonitor,
    '/equipment/history': EquipmentHistory,
    '/equipment/alarms': EquipmentAlarms,
    '/basic-data/products': Products,
    '/production/orders': ProductionOrders,
    '/production/tasks': ProductionTasks,
    '/production/dispatch': ProductionDispatches,
    '/production/reports': ProductionReports,
    '/execution/exceptions': ProductionExceptions,
    '/execution/flows': ProcessFlows,
    '/execution/sn': ProductSns,
    '/system/users': Users,
    '/system/logs': OperationLogs,
    '/system/roles': Roles,
    '/system/menus': Menus,
    '/system/departments': Departments,
    '/quality/standards': QualityStandards,
    '/quality/tasks': QualityTasks,
    '/quality/records': QualityRecords,
    '/quality/defects': QualityDefects,
    '/traceability/backward': BackwardTrace,
    '/traceability/forward': ForwardTrace,
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
