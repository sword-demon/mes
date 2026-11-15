import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { App, Button, Empty, Select, Space, Table, Tag, Typography } from 'antd';
import dayjs from 'dayjs';
import { useState } from 'react';
import Can from '@/components/can';
import {
    createDefectiveProduct,
    DEFECT_STATUSES,
    disposeDefectiveProduct,
    getDefectOptions,
    listDefectiveProducts,
    startDefectiveProduct,
} from '@/api/quality';
import type { DefectPayload, DefectStatus, DefectiveProduct } from '@/api/quality';
import type { DefectDisposition } from '@/api/quality';
import { useAuthStore } from '@/stores/auth';
import { ApiRequestError } from '@/utils/api-error';
import { can } from '@/utils/permission';
import CreateDefectModal from './create-modal';
import DispositionModal from './disposition-modal';
import type { DispositionMode } from './disposition-modal';

const statusColor = (status: DefectStatus) =>
    DEFECT_STATUSES.find((item) => item.value === status)?.color;

export default function DefectsPage() {
    const { message } = App.useApp();
    const queryClient = useQueryClient();
    const user = useAuthStore((state) => state.user);
    const canRead = can(user, 'quality.defects.index');
    const canProcess = can(user, 'button.quality.defects.process');
    const canRework = can(user, 'button.quality.defects.rework');
    const canScrap = can(user, 'button.quality.defects.scrap');
    const canRecheck = can(user, 'button.quality.defects.recheck');
    const [page, setPage] = useState(1);
    const [status, setStatus] = useState<DefectStatus>();
    const [orderId, setOrderId] = useState<number>();
    const [creating, setCreating] = useState(false);
    const [action, setAction] = useState<{ defect: DefectiveProduct; mode: DispositionMode } | null>(null);

    const options = useQuery({
        queryKey: ['quality.defects', 'order-options', ''],
        queryFn: () => getDefectOptions(),
        enabled: canRead,
    });
    const defects = useQuery({
        queryKey: ['quality.defects', 'list', page, status, orderId],
        queryFn: () => listDefectiveProducts({
            page,
            status,
            production_order_id: orderId,
        }),
        enabled: canRead,
    });

    const invalidate = () => queryClient.invalidateQueries({ queryKey: ['quality.defects'] });
    const showError = (error: unknown) =>
        message.error(error instanceof ApiRequestError ? error.message : '操作失败');

    const create = useMutation({
        mutationFn: createDefectiveProduct,
        onSuccess: async () => {
            await invalidate();
            message.success('不良品已登记');
            setCreating(false);
        },
        onError: showError,
    });
    const start = useMutation({
        mutationFn: (id: number) => startDefectiveProduct(id),
        onSuccess: async (defect) => {
            await invalidate();
            message.success(`已进入「${defect.status_label}」`);
        },
        onError: showError,
    });
    const dispose = useMutation({
        mutationFn: ({ defect, disposition }: {
            defect: DefectiveProduct;
            disposition: DefectDisposition;
        }) => disposeDefectiveProduct(defect.id, disposition),
        onSuccess: async (defect) => {
            await invalidate();
            message.success(`当前状态：${defect.status_label}`);
            setAction(null);
        },
        onError: showError,
    });

    if (!canRead) {
        return <Empty description="暂无不良品查看权限" />;
    }

    return (
        <>
            <Space className="mb-4 w-full justify-between" wrap>
                <div>
                    <Typography.Title level={4} className="mb-0!">
                        不良品管理
                    </Typography.Title>
                    <Typography.Text type="secondary">
                        登记不良品，完成返工复检或报废闭环
                    </Typography.Text>
                </div>
                <Can code="button.quality.defects.create">
                    <Button type="primary" onClick={() => setCreating(true)}>登记不良品</Button>
                </Can>
            </Space>

            <Space className="mb-4" wrap>
                <Select
                    allowClear
                    showSearch
                    optionFilterProp="label"
                    placeholder="生产订单"
                    style={{ width: 240 }}
                    loading={options.isLoading}
                    value={orderId}
                    onChange={(value) => { setOrderId(value); setPage(1); }}
                    options={(options.data?.orders ?? []).map((order) => ({
                        value: order.id,
                        label: `${order.code} ${order.product.name}`,
                    }))}
                />
                <Select
                    allowClear
                    placeholder="处理状态"
                    style={{ width: 150 }}
                    value={status}
                    onChange={(value) => { setStatus(value); setPage(1); }}
                    options={DEFECT_STATUSES.map(({ value, label }) => ({ value, label }))}
                />
            </Space>

            <Table<DefectiveProduct>
                rowKey="id"
                loading={defects.isLoading}
                dataSource={defects.data?.items ?? []}
                pagination={{
                    current: page,
                    pageSize: defects.data?.meta.per_page ?? 15,
                    total: defects.data?.meta.total ?? 0,
                    onChange: setPage,
                    showSizeChanger: false,
                }}
                expandable={{
                    expandedRowRender: (defect) => (
                        <Space direction="vertical" className="w-full">
                            {defect.remark && (
                                <Typography.Text type="secondary">登记备注：{defect.remark}</Typography.Text>
                            )}
                            {defect.scrap_reason && (
                                <Typography.Text>
                                    报废：{defect.scrap_reason}，数量 {defect.scrap_quantity}
                                    {defect.scrapped_at && `（${dayjs(defect.scrapped_at).format('YYYY-MM-DD HH:mm')}）`}
                                </Typography.Text>
                            )}
                            <Table
                                size="small"
                                rowKey="id"
                                pagination={false}
                                dataSource={defect.rework_orders}
                                locale={{ emptyText: '暂无返工记录' }}
                                columns={[
                                    { title: '返工单号', dataIndex: 'code' },
                                    { title: '返工原因', dataIndex: 'reason' },
                                    { title: '返工人', render: (_, rework) => rework.created_by?.name ?? '—' },
                                    {
                                        title: '复检结果',
                                        render: (_, rework) => rework.recheck_result === null
                                            ? '待复检'
                                            : rework.recheck_result === 'passed' ? '合格' : '不合格',
                                    },
                                    {
                                        title: '复检备注',
                                        render: (_, rework) => rework.recheck_remark ?? '—',
                                    },
                                ]}
                            />
                        </Space>
                    ),
                }}
                columns={[
                    {
                        title: '生产订单',
                        render: (_, defect) => (
                            <Space direction="vertical" size={0}>
                                <Typography.Text>{defect.production_order.code}</Typography.Text>
                                <Typography.Text type="secondary">{defect.production_order.product.name}</Typography.Text>
                            </Space>
                        ),
                    },
                    {
                        title: '工序',
                        render: (_, defect) => `${defect.route_operation.sequence}. ${defect.route_operation.process.name}`,
                    },
                    { title: '批次', dataIndex: ['production_batch', 'code'] },
                    {
                        title: '不良原因',
                        render: (_, defect) => `${defect.defect_code.name}（${defect.defect_code.category}）`,
                    },
                    { title: '数量', dataIndex: 'quantity', width: 90 },
                    {
                        title: '状态',
                        dataIndex: 'status',
                        width: 100,
                        render: (value: DefectStatus, defect) => (
                            <Tag color={statusColor(value)}>{defect.status_label}</Tag>
                        ),
                    },
                    { title: '处理人', render: (_, defect) => defect.handled_by?.name ?? '—' },
                    {
                        title: '登记时间',
                        render: (_, defect) => dayjs(defect.created_at).format('YYYY-MM-DD HH:mm'),
                    },
                    {
                        title: '操作',
                        width: 190,
                        render: (_, defect) => (
                            <Space>
                                {defect.status === 'pending' && canProcess && (
                                    <Button size="small" type="link" onClick={() => start.mutate(defect.id)}>
                                        开始处理
                                    </Button>
                                )}
                                {defect.status === 'processing' && (
                                    <>
                                        {canRework && (
                                            <Button size="small" type="link" onClick={() => setAction({ defect, mode: 'rework' })}>
                                                返工
                                            </Button>
                                        )}
                                        {canScrap && (
                                            <Button size="small" type="link" danger onClick={() => setAction({ defect, mode: 'scrap' })}>
                                                报废
                                            </Button>
                                        )}
                                    </>
                                )}
                                {defect.status === 'pending_recheck' && canRecheck && (
                                    <Button size="small" type="link" onClick={() => setAction({ defect, mode: 'recheck' })}>
                                        复检
                                    </Button>
                                )}
                            </Space>
                        ),
                    },
                ]}
            />

            {creating && (
                <CreateDefectModal
                    saving={create.isPending}
                    onSave={(payload: DefectPayload) => create.mutate(payload)}
                    onClose={() => setCreating(false)}
                />
            )}
            {action && (
                <DispositionModal
                    defect={action.defect}
                    mode={action.mode}
                    saving={dispose.isPending}
                    onSave={(disposition) => dispose.mutate({ defect: action.defect, disposition })}
                    onClose={() => setAction(null)}
                />
            )}
        </>
    );
}
