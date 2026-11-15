import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
    App,
    Button,
    DatePicker,
    Form,
    Input,
    InputNumber,
    Modal,
    Popconfirm,
    Progress,
    Select,
    Space,
    Table,
    Tag,
    Typography,
} from 'antd';
import dayjs from 'dayjs';
import type { Dayjs } from 'dayjs';
import { useState } from 'react';
import Can from '@/components/can';
import { listEmployees, listProductionLines, listTeams } from '@/api/basic-data';
import {
    assignTask,
    listOrders,
    listTasks,
    PRODUCTION_TASK_STATUSES,
    splitTask,
    transitionTask,
} from '@/api/production';
import type {
    ProductionTask,
    ProductionTaskAssignment,
    ProductionTaskPayload,
    ProductionTaskStatus,
} from '@/api/production';
import { ApiRequestError } from '@/utils/api-error';

const SPLITTABLE_ORDER_STATUSES = ['released', 'in_progress', 'paused'];
const statusOptions = PRODUCTION_TASK_STATUSES.map(({ value, label }) => ({ value, label }));
const statusColor = Object.fromEntries(
    PRODUCTION_TASK_STATUSES.map(({ value, color }) => [value, color]),
);

function SplitTaskModal({
    onSave,
    saving,
    onClose,
}: {
    onSave: (orderId: number, payload: ProductionTaskPayload) => void;
    saving: boolean;
    onClose: () => void;
}) {
    const [form] = Form.useForm();
    const orderId = Form.useWatch<number | undefined>('production_order_id', form);
    const orders = useQuery({
        queryKey: ['production.orders', 'options'],
        queryFn: () => listOrders({ per_page: 100 }),
    });
    const splittableOrders = (orders.data?.items ?? []).filter((order) =>
        SPLITTABLE_ORDER_STATUSES.includes(order.status),
    );

    return (
        <Modal
            open
            title="拆分生产任务"
            confirmLoading={saving}
            width={560}
            onCancel={onClose}
            onOk={() => form.submit()}
        >
            <Form
                form={form}
                layout="vertical"
                initialValues={{ planned_quantity: 1 }}
                onValuesChange={(changed) => {
                    if (!('production_order_id' in changed)) {
                        return;
                    }

                    const order = splittableOrders.find((item) => item.id === changed.production_order_id);

                    if (order) {
                        form.setFieldsValue({
                            planned_start_at: dayjs(order.planned_start_at),
                            planned_end_at: order.planned_end_at ? dayjs(order.planned_end_at) : null,
                        });
                    }
                }}
                onFinish={(values) =>
                    onSave(values.production_order_id, {
                        planned_quantity: values.planned_quantity,
                        planned_start_at: values.planned_start_at
                            ? (values.planned_start_at as Dayjs).toISOString()
                            : null,
                        planned_end_at: values.planned_end_at
                            ? (values.planned_end_at as Dayjs).toISOString()
                            : null,
                    })
                }
            >
                <Form.Item
                    name="production_order_id"
                    label="生产订单"
                    rules={[{ required: true, message: '请选择生产订单' }]}
                >
                    <Select
                        showSearch
                        optionFilterProp="label"
                        loading={orders.isLoading}
                        placeholder="仅列出已下达的订单"
                        options={splittableOrders.map((order) => ({
                            value: order.id,
                            label: `${order.code}（计划 ${Number(order.planned_quantity)}）`,
                        }))}
                    />
                </Form.Item>
                <Form.Item
                    name="planned_quantity"
                    label="本次拆分数量"
                    rules={[{ required: true, message: '请填写计划数量' }]}
                >
                    <InputNumber min={0.001} precision={3} className="w-full" />
                </Form.Item>
                <Form.Item name="planned_start_at" label="计划开始时间">
                    <DatePicker showTime format="YYYY-MM-DD HH:mm" className="w-full" />
                </Form.Item>
                <Form.Item name="planned_end_at" label="计划结束时间">
                    <DatePicker showTime format="YYYY-MM-DD HH:mm" className="w-full" />
                </Form.Item>
                {orderId !== undefined && (
                    <Typography.Text type="secondary">
                        任务产品与工艺路线自动继承订单，任务计划数量合计不能超过订单计划数量。
                    </Typography.Text>
                )}
            </Form>
        </Modal>
    );
}

function AssignTaskModal({
    record,
    onSave,
    saving,
    onClose,
}: {
    record: ProductionTask;
    onSave: (payload: ProductionTaskAssignment) => void;
    saving: boolean;
    onClose: () => void;
}) {
    const [form] = Form.useForm();
    const teamId = Form.useWatch<number | undefined>('team_id', form);
    const lines = useQuery({
        queryKey: ['basic.lines', 'options'],
        queryFn: () => listProductionLines({ status: true, per_page: 100 }),
    });
    const teams = useQuery({
        queryKey: ['basic.teams', 'options'],
        queryFn: () => listTeams({ status: true, per_page: 100 }),
    });
    const employees = useQuery({
        queryKey: ['basic.employees', 'options'],
        queryFn: () => listEmployees({ status: true, per_page: 100 }),
    });
    const owners = (employees.data?.items ?? []).filter(
        (employee) => teamId === undefined || employee.team_id === teamId,
    );

    return (
        <Modal
            open
            title={`分配任务 ${record.code}`}
            confirmLoading={saving}
            width={520}
            onCancel={onClose}
            onOk={() => form.submit()}
        >
            <Form
                form={form}
                layout="vertical"
                initialValues={{
                    production_line_id: record.production_line_id ?? undefined,
                    team_id: record.team_id ?? undefined,
                    owner_id: record.owner_id ?? undefined,
                }}
                onValuesChange={(changed) => {
                    if ('team_id' in changed) {
                        form.setFieldsValue({ owner_id: undefined });
                    }
                }}
                onFinish={(values) =>
                    onSave({
                        production_line_id: values.production_line_id,
                        team_id: values.team_id,
                        owner_id: values.owner_id,
                    })
                }
            >
                <Form.Item
                    name="production_line_id"
                    label="生产产线"
                    rules={[{ required: true, message: '请选择产线' }]}
                >
                    <Select
                        showSearch
                        optionFilterProp="label"
                        loading={lines.isLoading}
                        options={(lines.data?.items ?? []).map((line) => ({
                            value: line.id,
                            label: `${line.code} ${line.name}`,
                        }))}
                    />
                </Form.Item>
                <Form.Item
                    name="team_id"
                    label="生产班组"
                    rules={[{ required: true, message: '请选择班组' }]}
                >
                    <Select
                        showSearch
                        optionFilterProp="label"
                        loading={teams.isLoading}
                        options={(teams.data?.items ?? []).map((team) => ({
                            value: team.id,
                            label: `${team.code} ${team.name}`,
                        }))}
                    />
                </Form.Item>
                <Form.Item
                    name="owner_id"
                    label="负责人"
                    rules={[{ required: true, message: '请选择负责人' }]}
                >
                    <Select
                        showSearch
                        optionFilterProp="label"
                        loading={employees.isLoading}
                        placeholder={teamId ? '仅列出所选班组人员' : '请先选择班组'}
                        disabled={!teamId}
                        options={owners.map((employee) => ({
                            value: employee.id,
                            label: `${employee.employee_no} ${employee.name}`,
                        }))}
                    />
                </Form.Item>
            </Form>
        </Modal>
    );
}

export default function ProductionTasks() {
    const { message } = App.useApp();
    const queryClient = useQueryClient();
    const [page, setPage] = useState(1);
    const [keyword, setKeyword] = useState('');
    const [status, setStatus] = useState<ProductionTaskStatus>();
    const [orderId, setOrderId] = useState<number>();
    const [lineId, setLineId] = useState<number>();
    const [splitting, setSplitting] = useState(false);
    const [assigning, setAssigning] = useState<ProductionTask | null>(null);

    // shortcut: one page covers the demo factory's orders and lines; add paging when it does not.
    const orders = useQuery({
        queryKey: ['production.orders', 'options'],
        queryFn: () => listOrders({ per_page: 100 }),
    });
    const lines = useQuery({
        queryKey: ['basic.lines', 'options'],
        queryFn: () => listProductionLines({ status: true, per_page: 100 }),
    });
    const query = useQuery({
        queryKey: ['production.tasks', page, keyword, status, orderId, lineId],
        queryFn: () =>
            listTasks({
                page,
                keyword,
                status,
                production_order_id: orderId,
                production_line_id: lineId,
            }),
    });

    const showError = (error: unknown) =>
        message.error(error instanceof ApiRequestError ? error.message : '操作失败');
    const refresh = () => queryClient.invalidateQueries({ queryKey: ['production.tasks'] });
    const split = useMutation({
        mutationFn: ({ id, payload }: { id: number; payload: ProductionTaskPayload }) =>
            splitTask(id, payload),
        onSuccess: async () => {
            await refresh();
            message.success('任务已拆分');
            setSplitting(false);
        },
        onError: showError,
    });
    const assign = useMutation({
        mutationFn: ({ id, payload }: { id: number; payload: ProductionTaskAssignment }) =>
            assignTask(id, payload),
        onSuccess: async () => {
            await refresh();
            message.success('任务已分配');
            setAssigning(null);
        },
        onError: showError,
    });
    const act = useMutation({
        mutationFn: ({ id, action }: { id: number; action: 'pause' | 'resume' | 'complete' }) =>
            transitionTask(id, action),
        onSuccess: async () => {
            await refresh();
            message.success('状态已更新');
        },
        onError: showError,
    });

    return (
        <>
            <Space className="mb-4 w-full justify-between" wrap>
                <Typography.Title level={4} className="mb-0!">
                    生产任务
                </Typography.Title>
                <Space wrap>
                    <Select
                        allowClear
                        placeholder="状态"
                        className="w-32"
                        options={statusOptions}
                        onChange={(value?: ProductionTaskStatus) => {
                            setStatus(value);
                            setPage(1);
                        }}
                    />
                    <Select
                        allowClear
                        showSearch
                        optionFilterProp="label"
                        placeholder="生产订单"
                        className="w-48"
                        loading={orders.isLoading}
                        options={(orders.data?.items ?? []).map((order) => ({
                            value: order.id,
                            label: order.code,
                        }))}
                        onChange={(value?: number) => {
                            setOrderId(value);
                            setPage(1);
                        }}
                    />
                    <Select
                        allowClear
                        showSearch
                        optionFilterProp="label"
                        placeholder="产线"
                        className="w-40"
                        loading={lines.isLoading}
                        options={(lines.data?.items ?? []).map((line) => ({
                            value: line.id,
                            label: `${line.code} ${line.name}`,
                        }))}
                        onChange={(value?: number) => {
                            setLineId(value);
                            setPage(1);
                        }}
                    />
                    <Input.Search
                        allowClear
                        placeholder="任务编号"
                        className="w-48"
                        onSearch={(value) => {
                            setKeyword(value);
                            setPage(1);
                        }}
                    />
                    <Can code="button.production.tasks.create">
                        <Button type="primary" onClick={() => setSplitting(true)}>
                            拆分任务
                        </Button>
                    </Can>
                </Space>
            </Space>
            <Table<ProductionTask>
                rowKey="id"
                loading={query.isLoading}
                dataSource={query.data?.items}
                scroll={{ x: 1280 }}
                pagination={{
                    current: page,
                    pageSize: query.data?.meta.per_page,
                    total: query.data?.meta.total,
                    onChange: setPage,
                }}
                columns={[
                    { title: '任务编号', dataIndex: 'code', width: 150 },
                    {
                        title: '生产订单',
                        width: 140,
                        render: (_, record) => record.production_order?.code ?? '-',
                    },
                    {
                        title: '产品',
                        width: 150,
                        render: (_, record) =>
                            record.product ? `${record.product.code} ${record.product.name}` : '-',
                    },
                    {
                        title: '路线',
                        width: 130,
                        render: (_, record) =>
                            record.route ? `${record.route.code} ${record.route.version}` : '-',
                    },
                    {
                        title: '产线 / 班组',
                        width: 170,
                        render: (_, record) =>
                            [record.production_line?.name, record.team?.name].filter(Boolean).join(' / ') || '-',
                    },
                    {
                        title: '负责人',
                        width: 100,
                        render: (_, record) => record.owner?.name ?? '-',
                    },
                    {
                        title: '已生产 / 计划',
                        width: 120,
                        render: (_, record) =>
                            `${Number(record.produced_quantity)} / ${Number(record.planned_quantity)}`,
                    },
                    {
                        title: '进度',
                        width: 150,
                        render: (_, record) => (
                            <Progress percent={Math.round(record.progress_percent)} size="small" />
                        ),
                    },
                    {
                        title: '状态',
                        dataIndex: 'status',
                        width: 90,
                        render: (value: ProductionTaskStatus, record) => (
                            <Tag color={statusColor[value]}>{record.status_label}</Tag>
                        ),
                    },
                    {
                        title: '操作',
                        key: 'actions',
                        width: 190,
                        render: (_, record) => (
                            <Space wrap>
                                {record.status !== 'completed' && (
                                    <Can code="button.production.tasks.update">
                                        <Button type="link" onClick={() => setAssigning(record)}>
                                            {record.status === 'pending' ? '分配' : '重新分配'}
                                        </Button>
                                    </Can>
                                )}
                                {record.status === 'in_progress' && (
                                    <Can code="button.production.tasks.pause">
                                        <Button
                                            type="link"
                                            onClick={() => act.mutate({ id: record.id, action: 'pause' })}
                                        >
                                            暂停
                                        </Button>
                                    </Can>
                                )}
                                {record.status === 'paused' && (
                                    <Can code="button.production.tasks.resume">
                                        <Button
                                            type="link"
                                            onClick={() => act.mutate({ id: record.id, action: 'resume' })}
                                        >
                                            恢复
                                        </Button>
                                    </Can>
                                )}
                                {['in_progress', 'paused'].includes(record.status) && (
                                    <Can code="button.production.tasks.complete">
                                        <Popconfirm
                                            title="确认完成该任务？"
                                            okText="完成"
                                            cancelText="取消"
                                            onConfirm={() =>
                                                act.mutate({ id: record.id, action: 'complete' })
                                            }
                                        >
                                            <Button type="link">完成</Button>
                                        </Popconfirm>
                                    </Can>
                                )}
                            </Space>
                        ),
                    },
                ]}
            />
            {splitting && (
                <SplitTaskModal
                    saving={split.isPending}
                    onSave={(id, payload) => split.mutate({ id, payload })}
                    onClose={() => setSplitting(false)}
                />
            )}
            {assigning && (
                <AssignTaskModal
                    record={assigning}
                    saving={assign.isPending}
                    onSave={(payload) => assign.mutate({ id: assigning.id, payload })}
                    onClose={() => setAssigning(null)}
                />
            )}
        </>
    );
}
