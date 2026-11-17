import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
    App,
    Button,
    DatePicker,
    Empty,
    Form,
    Image,
    Input,
    Modal,
    Select,
    Space,
    Table,
    Tag,
    Typography,
} from 'antd';
import type { Dayjs } from 'dayjs';
import { businessTime } from '@/utils/datetime';
import { useState } from 'react';
import Can from '@/components/can';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import {
    closeProductionException,
    getProductionExceptionOptions,
    listProductionExceptions,
    processProductionException,
    PRODUCTION_EXCEPTION_STATUSES,
    PRODUCTION_EXCEPTION_TYPES,
    reportProductionException,
} from '@/api/production';
import type {
    ProductionException,
    ProductionExceptionPayload,
    ProductionExceptionStatus,
    ProductionExceptionType,
} from '@/api/production';
import { useAuthStore } from '@/stores/auth';
import { ApiRequestError } from '@/utils/api-error';
import { can } from '@/utils/permission';
import ReportExceptionModal from './report-modal';

const typeColor = Object.fromEntries(
    PRODUCTION_EXCEPTION_TYPES.map(({ value, color }) => [value, color]),
);
const statusColor = Object.fromEntries(
    PRODUCTION_EXCEPTION_STATUSES.map(({ value, color }) => [value, color]),
);

type HandlingAction = 'process' | 'close';

function HandlingModal({
    exception,
    action,
    onClose,
}: {
    exception: ProductionException;
    action: HandlingAction;
    onClose: () => void;
}) {
    const { message } = App.useApp();
    const client = useQueryClient();
    const [form] = Form.useForm<{ handling_result?: string }>();
    const save = useMutation({
        mutationFn: (result?: string) => action === 'process'
            ? processProductionException(exception.id, result ?? '')
            : closeProductionException(exception.id),
        onSuccess: (updated) => {
            void client.invalidateQueries({ queryKey: ['production', 'exceptions', 'list'] });
            message.success(`生产异常${updated.status_label}`);
            onClose();
        },
        onError: (error) => message.error(
            error instanceof ApiRequestError ? error.message : '操作失败',
        ),
    });

    return (
        <Modal
            open
            title={action === 'process' ? '处理生产异常' : '关闭生产异常'}
            okText={action === 'process' ? '提交处理' : '确认关闭'}
            confirmLoading={save.isPending}
            onCancel={onClose}
            onOk={() => action === 'process' ? form.submit() : save.mutate()}
        >
            {action === 'process' ? (
                <Form
                    form={form}
                    layout="vertical"
                    onFinish={(values) => save.mutate(values.handling_result ?? '')}
                >
                    <Form.Item
                        name="handling_result"
                        label="处理结果"
                        rules={[{ required: true, message: '请填写处理结果' }]}
                    >
                        <Input.TextArea rows={4} maxLength={2000} showCount />
                    </Form.Item>
                </Form>
            ) : (
                <Typography.Text>处理结果：{exception.handling_result ?? '—'}</Typography.Text>
            )}
        </Modal>
    );
}

export default function ProductionExceptionsPage() {
    const { message } = App.useApp();
    const queryClient = useQueryClient();
    const user = useAuthStore((state) => state.user);
    const canRead = can(user, 'production.exceptions.index');
    const canCreate = can(user, 'button.production.exceptions.create');
    const [page, setPage] = useState(1);
    const [type, setType] = useState<ProductionExceptionType>();
    const [status, setStatus] = useState<ProductionExceptionStatus>();
    const [orderId, setOrderId] = useState<number>();
    const [orderKeyword, setOrderKeyword] = useState('');
    const [dates, setDates] = useState<[Dayjs, Dayjs] | null>(null);
    const [reporting, setReporting] = useState(false);
    const [handling, setHandling] = useState<{ exception: ProductionException; action: HandlingAction }>();
    const startDate = dates?.[0].format('YYYY-MM-DD');
    const endDate = dates?.[1].format('YYYY-MM-DD');
    const debouncedOrderKeyword = useDebouncedValue(orderKeyword);
    const options = useQuery({
        queryKey: ['production', 'exceptions', 'options', 'orders', debouncedOrderKeyword],
        queryFn: () => getProductionExceptionOptions(undefined, debouncedOrderKeyword, 'orders'),
        enabled: canRead,
        staleTime: 30_000,
    });
    const exceptions = useQuery({
        queryKey: ['production', 'exceptions', 'list', page, type, status, orderId, startDate, endDate],
        queryFn: () => listProductionExceptions({
            page,
            type,
            status,
            production_order_id: orderId,
            start_date: startDate,
            end_date: endDate,
        }),
        enabled: canRead,
    });
    const report = useMutation({
        mutationFn: ({ payload, files }: { payload: ProductionExceptionPayload; files: File[] }) =>
            reportProductionException(payload, files),
        onSuccess: () => {
            void queryClient.invalidateQueries({ queryKey: ['production', 'exceptions', 'list'] });
            message.success('生产异常已上报');
            setReporting(false);
        },
        onError: (error) => message.error(
            error instanceof ApiRequestError ? error.message : '操作失败',
        ),
    });

    if (!canRead && !canCreate) {
        return <Empty description="暂无生产异常查看权限" />;
    }

    return (
        <>
            <Space className="mb-4 w-full justify-between" wrap>
                <div>
                    <Typography.Title level={4} className="mb-0!">生产异常</Typography.Title>
                    <Typography.Text type="secondary">
                        上报设备、物料、工艺、质量与人员异常，跟踪处理直至关闭
                    </Typography.Text>
                </div>
                <Can code="button.production.exceptions.create">
                    <Button type="primary" onClick={() => setReporting(true)}>上报异常</Button>
                </Can>
            </Space>
            {canRead ? (
                <>
                    <Space className="mb-4" wrap>
                        <Select
                            allowClear
                            placeholder="异常类型"
                            className="w-32"
                            options={PRODUCTION_EXCEPTION_TYPES.map(({ value, label }) => ({ value, label }))}
                            onChange={(value?: ProductionExceptionType) => {
                                setType(value);
                                setPage(1);
                            }}
                        />
                        <Select
                            allowClear
                            placeholder="异常状态"
                            className="w-32"
                            options={PRODUCTION_EXCEPTION_STATUSES.map(({ value, label }) => ({ value, label }))}
                            onChange={(value?: ProductionExceptionStatus) => {
                                setStatus(value);
                                setPage(1);
                            }}
                        />
                        <Select
                            allowClear
                            showSearch
                            filterOption={false}
                            onSearch={setOrderKeyword}
                            placeholder="筛选生产订单"
                            className="w-60"
                            loading={options.isLoading}
                            value={orderId}
                            options={(options.data?.orders ?? []).map((order) => ({
                                value: order.id,
                                label: `${order.code} ${order.product.name}`,
                            }))}
                            onChange={(value?: number) => {
                                setOrderId(value);
                                setPage(1);
                            }}
                        />
                        <DatePicker.RangePicker
                            onChange={(value) => {
                                setDates(value?.[0] && value[1] ? [value[0], value[1]] : null);
                                setPage(1);
                            }}
                        />
                    </Space>
                    <Table<ProductionException>
                rowKey="id"
                loading={exceptions.isLoading}
                dataSource={exceptions.data?.items ?? []}
                scroll={{ x: 1400 }}
                locale={{ emptyText: '暂无生产异常' }}
                pagination={{
                    current: page,
                    pageSize: exceptions.data?.meta.per_page ?? 15,
                    total: exceptions.data?.meta.total ?? 0,
                    onChange: setPage,
                    showSizeChanger: false,
                }}
                expandable={{
                    expandedRowRender: (exception) => (
                        <Space orientation="vertical" className="w-full">
                            <Typography.Paragraph className="mb-0!">
                                {exception.description}
                            </Typography.Paragraph>
                            {exception.handling_result && (
                                <Typography.Text type="secondary">
                                    处理结果：{exception.handling_result}
                                </Typography.Text>
                            )}
                            {exception.images.length > 0 && (
                                <Image.PreviewGroup>
                                    <Space wrap>
                                        {exception.images.map((image) => (
                                            <Image
                                                key={image.id}
                                                src={image.url}
                                                width={96}
                                                height={96}
                                                style={{ objectFit: 'cover' }}
                                            />
                                        ))}
                                    </Space>
                                </Image.PreviewGroup>
                            )}
                        </Space>
                    ),
                }}
                columns={[
                    { title: '异常编号', dataIndex: 'code', width: 170 },
                    {
                        title: '类型',
                        width: 100,
                        render: (_, exception) => (
                            <Tag color={typeColor[exception.type]}>{exception.type_label}</Tag>
                        ),
                    },
                    {
                        title: '订单 / 工序',
                        width: 260,
                        render: (_, exception) => (
                            <Space orientation="vertical" size={0}>
                                <Typography.Text>{exception.production_order?.code ?? '—'}</Typography.Text>
                                <Typography.Text type="secondary">
                                    {exception.process_record
                                        ? `工序 ${exception.process_record.route_operation.sequence} ${exception.process_record.route_operation.process.name}${exception.process_record.sn ? `（${exception.process_record.sn}）` : ''}`
                                        : '未关联工序'}
                                </Typography.Text>
                            </Space>
                        ),
                    },
                    {
                        title: '异常描述',
                        width: 220,
                        render: (_, exception) => (
                            <Typography.Text
                                ellipsis={{ tooltip: exception.description }}
                                style={{ maxWidth: 220 }}
                            >
                                {exception.description}
                            </Typography.Text>
                        ),
                    },
                    {
                        title: '状态',
                        width: 100,
                        render: (_, exception) => (
                            <Tag color={statusColor[exception.status]}>{exception.status_label}</Tag>
                        ),
                    },
                    {
                        title: '上报人 / 时间',
                        width: 180,
                        render: (_, exception) => (
                            <Space orientation="vertical" size={0}>
                                <Typography.Text>{exception.reporter?.name ?? '—'}</Typography.Text>
                                <Typography.Text type="secondary">
                                    {businessTime(exception.reported_at).format('YYYY-MM-DD HH:mm')}
                                </Typography.Text>
                            </Space>
                        ),
                    },
                    {
                        title: '处理人 / 结果',
                        width: 220,
                        render: (_, exception) => exception.handler ? (
                            <Space orientation="vertical" size={0}>
                                <Typography.Text>
                                    {exception.handler.name} / {businessTime(exception.handled_at).format('YYYY-MM-DD HH:mm')}
                                </Typography.Text>
                                <Typography.Text
                                    type="secondary"
                                    ellipsis={{ tooltip: exception.handling_result ?? '' }}
                                    style={{ maxWidth: 220 }}
                                >
                                    {exception.handling_result ?? '—'}
                                </Typography.Text>
                            </Space>
                        ) : '—',
                    },
                    {
                        title: '操作',
                        width: 110,
                        fixed: 'right',
                        render: (_, exception) => (
                            <Space>
                                {exception.status === 'reported' && (
                                    <Can code="button.production.exceptions.process">
                                        <Button
                                            size="small"
                                            type="link"
                                            onClick={() => setHandling({ exception, action: 'process' })}
                                        >
                                            处理
                                        </Button>
                                    </Can>
                                )}
                                {exception.status === 'processing' && (
                                    <Can code="button.production.exceptions.close">
                                        <Button
                                            size="small"
                                            type="link"
                                            danger
                                            onClick={() => setHandling({ exception, action: 'close' })}
                                        >
                                            关闭
                                        </Button>
                                    </Can>
                                )}
                            </Space>
                        ),
                    },
                ]}
                    />
                </>
            ) : (
                <Empty description="暂无生产异常查看权限" />
            )}
            {reporting && (
                <ReportExceptionModal
                    saving={report.isPending}
                    onSave={(payload, files) => report.mutate({ payload, files })}
                    onClose={() => setReporting(false)}
                />
            )}
            {handling && (
                <HandlingModal
                    exception={handling.exception}
                    action={handling.action}
                    onClose={() => setHandling(undefined)}
                />
            )}
        </>
    );
}
