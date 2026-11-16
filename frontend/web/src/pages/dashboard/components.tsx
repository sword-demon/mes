import type { UseQueryResult } from '@tanstack/react-query';
import { Alert, Button, Card, Empty, Spin, Statistic, Table } from 'antd';
import type { TableProps } from 'antd';
import type { EChartsOption } from 'echarts';
import type { ReactNode } from 'react';
import { useNavigate } from 'react-router';
import type { DashboardList } from '@/api/dashboard';
import EChart from '@/components/echart';
import { useAuthStore } from '@/stores/auth';
import { findMenu } from '@/utils/permission';

const errorMessage = (error: unknown): string =>
    error instanceof Error ? error.message : '加载失败';

export function ChartCard({
    title,
    loading,
    error,
    empty,
    emptyText,
    option,
}: {
    title: string;
    loading: boolean;
    error: unknown;
    empty: boolean;
    emptyText: string;
    option: EChartsOption;
}) {
    return (
        <Card title={title} className="h-full">
            {loading ? (
                <div className="flex h-64 items-center justify-center">
                    <Spin />
                </div>
            ) : error ? (
                <Alert type="error" showIcon title={errorMessage(error)} />
            ) : empty ? (
                <Empty
                    className="my-16"
                    image={Empty.PRESENTED_IMAGE_SIMPLE}
                    description={emptyText}
                />
            ) : (
                <EChart className="h-64 w-full" option={option} />
            )}
        </Card>
    );
}

export function LatestList<T extends { id: number }>({
    title,
    path,
    query,
    columns,
    emptyText,
}: {
    title: string;
    path: string;
    query: UseQueryResult<DashboardList<T>>;
    columns: TableProps<T>['columns'];
    emptyText: string;
}) {
    const navigate = useNavigate();
    const user = useAuthStore((state) => state.user);
    const accessible = user !== null && findMenu(user.menus, path) !== null;

    return (
        <Card
            title={title}
            className="h-full"
            extra={accessible ? (
                <Button type="link" className="px-0" onClick={() => navigate(path)}>
                    查看全部
                </Button>
            ) : undefined}
        >
            {query.isError ? (
                <Alert type="error" showIcon title={errorMessage(query.error)} />
            ) : (
                <Table<T>
                    rowKey="id"
                    size="small"
                    loading={query.isLoading}
                    columns={columns}
                    dataSource={query.data?.items ?? []}
                    pagination={false}
                    scroll={{ x: 640 }}
                    locale={{ emptyText }}
                    onRow={accessible ? () => ({
                        onClick: () => navigate(path),
                        onKeyDown: (event) => {
                            if (event.key === 'Enter' || event.key === ' ') {
                                event.preventDefault();
                                navigate(path);
                            }
                        },
                        tabIndex: 0,
                        'aria-label': `打开${title}`,
                        style: { cursor: 'pointer' },
                    }) : undefined}
                />
            )}
        </Card>
    );
}

export function StatisticsCard({
    title,
    value,
    icon,
    color,
}: {
    title: string;
    value: number | string;
    icon: ReactNode;
    color: string;
}) {
    return (
        <Card>
            <Statistic
                title={title}
                value={value}
                prefix={<span style={{ color }}>{icon}</span>}
            />
        </Card>
    );
}
