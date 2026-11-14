import { useQuery } from '@tanstack/react-query';
import { Button, Layout, Space, Typography } from 'antd';
import { useEffect } from 'react';
import { Outlet, useNavigate } from 'react-router';
import { fetchMe, logout } from '@/api/auth';
import { useAuthStore } from '@/stores/auth';

const { Header, Sider, Content } = Layout;

export default function AdminLayout() {
    const navigate = useNavigate();
    const user = useAuthStore((state) => state.user);
    const setUser = useAuthStore((state) => state.setUser);
    const clearAuth = useAuthStore((state) => state.clearAuth);

    const { data } = useQuery({
        queryKey: ['me'],
        queryFn: fetchMe,
        // Login seeds this cache, so the request only re-runs on a cold start
        // where it doubles as the token validation that triggers 401 handling.
        staleTime: 30_000,
    });

    useEffect(() => {
        if (data) {
            setUser(data);
        }
    }, [data, setUser]);

    async function handleLogout() {
        await logout().catch(() => undefined);
        clearAuth();
        navigate('/login', { replace: true });
    }

    return (
        <Layout className="min-h-screen">
            <Sider theme="dark" width={208}>
                <div className="px-4 py-5 text-lg font-semibold text-white">
                    MES 管理系统
                </div>
            </Sider>
            <Layout>
                <Header className="flex items-center justify-end bg-white! px-6">
                    <Space>
                        <Typography.Text>{user?.name ?? '加载中'}</Typography.Text>
                        <Button onClick={handleLogout}>退出登录</Button>
                    </Space>
                </Header>
                <Content className="m-6 rounded-lg bg-white p-6">
                    <Outlet />
                </Content>
            </Layout>
        </Layout>
    );
}
