import { Empty, Tabs, Typography } from 'antd';
import { LoginLogsPanel } from './login-logs';
import { OperationLogsPanel } from './operation-logs';
import { useAuthStore } from '@/stores/auth';
import { can } from '@/utils/permission';

export { formatChangeValue } from './operation-logs';

export default function AuditLogs() {
    const user = useAuthStore((state) => state.user);

    if (!can(user, 'system.logs.index')) {
        return <Empty description="暂无日志查看权限" />;
    }

    return (
        <>
            <Typography.Title level={4} className="mb-4">
                操作与登录日志
            </Typography.Title>
            <Tabs
                items={[
                    { key: 'operation', label: '操作日志', children: <OperationLogsPanel /> },
                    { key: 'login', label: '登录日志', children: <LoginLogsPanel /> },
                ]}
            />
        </>
    );
}
