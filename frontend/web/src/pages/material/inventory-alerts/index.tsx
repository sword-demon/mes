import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { App, Button, Empty, Select, Space, Table, Tag, Typography } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import { businessTime } from '@/utils/datetime';
import { useState } from 'react';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import {
    closeInventoryAlert,
    confirmInventoryAlert,
    listInventoryAlerts,
    searchInventoryAlertMaterials,
} from '@/api/inventory';
import type { InventoryAlert } from '@/api/inventory';
import Can from '@/components/can';
import { useAuthStore } from '@/stores/auth';
import { ApiRequestError } from '@/utils/api-error';
import { can } from '@/utils/permission';

const STATUS_COLORS: Record<InventoryAlert['status'], string> = {
    pending: 'red',
    confirmed: 'orange',
    closed: 'green',
};

export default function InventoryAlertsPage() {
    const { message } = App.useApp();
    const queryClient = useQueryClient();
    const user = useAuthStore((state) => state.user);
    const canRead = can(user, 'inventory.alerts.index');
    const [page, setPage] = useState(1);
    const [materialId, setMaterialId] = useState<number>();
    const [materialKeyword, setMaterialKeyword] = useState('');
    const debouncedMaterialKeyword = useDebouncedValue(materialKeyword);
    const materialOptions = useQuery({
        queryKey: ['inventory', 'alerts', 'material-options', debouncedMaterialKeyword],
        queryFn: () => searchInventoryAlertMaterials(debouncedMaterialKeyword),
        enabled: canRead && debouncedMaterialKeyword.length > 0,
    });
    const alerts = useQuery({
        queryKey: ['inventory', 'alerts', page, materialId],
        queryFn: () => listInventoryAlerts({ page, material_id: materialId }),
        enabled: canRead,
    });
    const showError = (error: unknown) =>
        message.error(error instanceof ApiRequestError ? error.message : '操作失败');
    const handleAlert = useMutation({
        mutationFn: ({ id, action }: { id: number; action: 'confirm' | 'close' }) =>
            action === 'confirm' ? confirmInventoryAlert(id) : closeInventoryAlert(id),
        onSuccess: async (alert) => {
            await queryClient.invalidateQueries({ queryKey: ['inventory', 'alerts'] });
            message.success(`库存预警${alert.status_label}`);
        },
        onError: showError,
    });

    if (!canRead) {
        return <Empty description="暂无库存预警查看权限" />;
    }

    const columns: ColumnsType<InventoryAlert> = [
        {
            title: '物料',
            render: (_, alert) => (
                <Space orientation="vertical" size={0}>
                    <Typography.Text>{alert.material.code} {alert.material.name}</Typography.Text>
                    <Typography.Text type="secondary">{alert.material.unit}</Typography.Text>
                </Space>
            ),
        },
        {
            title: '库存 / 最低库存',
            width: 180,
            render: (_, alert) => `${alert.current_quantity} / ${alert.min_stock}`,
        },
        {
            title: '状态',
            dataIndex: 'status',
            width: 110,
            render: (_, alert) => <Tag color={STATUS_COLORS[alert.status]}>{alert.status_label}</Tag>,
        },
        {
            title: '处理人 / 时间',
            width: 190,
            render: (_, alert) => alert.handled_by
                ? `${alert.handled_by.name} / ${businessTime(alert.handled_at).format('YYYY-MM-DD HH:mm')}`
                : '—',
        },
        {
            title: '预警时间',
            width: 170,
            render: (_, alert) => businessTime(alert.created_at).format('YYYY-MM-DD HH:mm'),
        },
        {
            title: '操作',
            width: 140,
            render: (_, alert) => (
                <Space>
                    {alert.status === 'pending' && (
                        <Can code="button.inventory.alerts.confirm">
                            <Button
                                size="small"
                                type="link"
                                loading={handleAlert.isPending}
                                onClick={() => handleAlert.mutate({ id: alert.id, action: 'confirm' })}
                            >
                                确认
                            </Button>
                        </Can>
                    )}
                    <Can code="button.inventory.alerts.close">
                        <Button
                            size="small"
                            type="link"
                            danger
                            loading={handleAlert.isPending}
                            onClick={() => handleAlert.mutate({ id: alert.id, action: 'close' })}
                        >
                            关闭
                        </Button>
                    </Can>
                </Space>
            ),
        },
    ];

    return (
        <>
            <Space className="mb-4 w-full justify-between" wrap>
                <div>
                    <Typography.Title level={4} className="mb-0!">库存预警</Typography.Title>
                    <Typography.Text type="secondary">按物料汇总当前库存，查看并处理低库存预警</Typography.Text>
                </div>
                <Select
                    allowClear
                    showSearch
                    optionFilterProp="label"
                    filterOption={false}
                    placeholder="筛选物料"
                    style={{ width: 260 }}
                    loading={materialOptions.isLoading}
                    value={materialId}
                    onSearch={setMaterialKeyword}
                    onChange={(value) => { setMaterialId(value); setPage(1); }}
                    options={(materialOptions.data?.materials ?? []).map((material) => ({
                        value: material.id,
                        label: material.code + ' ' + material.name,
                    }))}
                />
            </Space>
            <Table<InventoryAlert>
                rowKey="id"
                loading={alerts.isLoading}
                dataSource={alerts.data?.items ?? []}
                columns={columns}
                locale={{ emptyText: '暂无待处理库存预警' }}
                pagination={{
                    current: page,
                    pageSize: alerts.data?.meta.per_page ?? 15,
                    total: alerts.data?.meta.total ?? 0,
                    onChange: setPage,
                    showSizeChanger: false,
                }}
            />
        </>
    );
}
