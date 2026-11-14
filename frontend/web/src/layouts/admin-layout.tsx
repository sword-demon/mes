import { useQueryClient } from '@tanstack/react-query';
import {
    ApartmentOutlined,
    BuildOutlined,
    DashboardOutlined,
    DatabaseOutlined,
    DesktopOutlined,
    InboxOutlined,
    PartitionOutlined,
    PlayCircleOutlined,
    SafetyCertificateOutlined,
    SettingOutlined,
} from '@ant-design/icons';
import { Button, Layout, Menu, Result, Space, Spin, Typography } from 'antd';
import type { MenuProps } from 'antd';
import type { ComponentType } from 'react';
import { useMemo } from 'react';
import { Outlet, useLocation, useNavigate } from 'react-router';
import { logout } from '@/api/auth';
import { useCurrentUser } from '@/hooks/use-current-user';
import { useAuthStore } from '@/stores/auth';
import type { MenuNode } from '@/types/api';
import { menuTrail } from '@/utils/permission';

const { Header, Sider, Content } = Layout;

type MenuItem = Required<MenuProps>['items'][number];

/**
 * Icon names seeded on `sys_menus`. An unknown name renders without an icon,
 * so a new menu only needs an entry here when it wants one.
 */
const menuIcons: Record<string, ComponentType> = {
    ApartmentOutlined,
    BuildOutlined,
    DashboardOutlined,
    DatabaseOutlined,
    DesktopOutlined,
    InboxOutlined,
    PartitionOutlined,
    PlayCircleOutlined,
    SafetyCertificateOutlined,
    SettingOutlined,
};

/**
 * A group without a path keys on its id, so it can open and close without
 * colliding with a page path.
 */
const menuKey = (node: MenuNode) => node.path ?? `group-${node.id}`;

function menuItems(nodes: MenuNode[]): MenuItem[] {
    return nodes
        .filter((node) => node.children.length > 0 || node.path)
        .map((node) => {
            const Icon = node.icon ? menuIcons[node.icon] : undefined;

            return {
                key: menuKey(node),
                label: node.name,
                icon: Icon ? <Icon /> : undefined,
                children: node.children.length > 0 ? menuItems(node.children) : undefined,
            } as MenuItem;
        });
}

export default function AdminLayout() {
    const navigate = useNavigate();
    const { pathname } = useLocation();
    const queryClient = useQueryClient();
    const user = useAuthStore((state) => state.user);
    const clearAuth = useAuthStore((state) => state.clearAuth);

    const { isError, refetch } = useCurrentUser();

    const items = useMemo(() => menuItems(user?.menus ?? []), [user]);
    const trail = menuTrail(user?.menus ?? [], pathname);
    const openKeys = trail.slice(0, -1).map(menuKey);

    async function handleLogout() {
        await logout().catch(() => undefined);
        queryClient.removeQueries({ queryKey: ['me'] });
        clearAuth();
        navigate('/login', { replace: true });
    }

    // antd ships an unlayered `.ant-layout { min-height: 0 }`, which outranks
    // Tailwind's utilities layer, so the viewport height is set inline.
    return (
        <Layout style={{ minHeight: '100vh' }}>
            <Sider theme="dark" width={208}>
                <div className="px-4 py-5 text-lg font-semibold text-white">
                    MES 管理系统
                </div>
                <Menu
                    // `defaultOpenKeys` is read once on mount, so remount the
                    // menu when the route changes to expand the active branch.
                    key={pathname}
                    theme="dark"
                    mode="inline"
                    items={items}
                    selectedKeys={[pathname]}
                    defaultOpenKeys={openKeys}
                    onClick={({ key }) => navigate(key)}
                />
            </Sider>
            <Layout>
                <Header className="flex items-center justify-end bg-white! px-6">
                    <Space>
                        <Typography.Text>{user?.name ?? '加载中'}</Typography.Text>
                        <Button onClick={handleLogout}>退出登录</Button>
                    </Space>
                </Header>
                <Content className="m-6 rounded-lg bg-white p-6">
                    {user ? (
                        <Outlet />
                    ) : isError ? (
                        <Result
                            status="error"
                            title="加载用户信息失败"
                            extra={<Button onClick={() => refetch()}>重试</Button>}
                        />
                    ) : (
                        <Spin className="mt-24 block" />
                    )}
                </Content>
            </Layout>
        </Layout>
    );
}
