import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
    App,
    Button,
    DatePicker,
    Form,
    Input,
    Modal,
    Select,
    Space,
    Table,
    Tabs,
    Tag,
    Typography,
} from 'antd';
import dayjs from 'dayjs';
import type { Dayjs } from 'dayjs';
import { useState } from 'react';
import Can from '@/components/can';
import { listEmployees, listTeams } from '@/api/basic-data';
import {
    createDispatch,
    listDispatches,
    listMyDispatches,
    listOrders,
    listTasks,
    listTaskOperations,
    PRODUCTION_TASK_STATUSES,
} from '@/api/production';
import type {
    ProductionDispatch,
    ProductionDispatchPayload,
    ProductionTaskStatus,
} from '@/api/production';
import { useAuthStore } from '@/stores/auth';
import { ApiRequestError } from '@/utils/api-error';
import { can } from '@/utils/permission';

const statusColor = Object.fromEntries(
    PRODUCTION_TASK_STATUSES.map(({ value, color }) => [value, color]),
);

function DispatchTable({
    data,
    loading,
    page,
    onPageChange,
}: {
    data?: { items: ProductionDispatch[]; meta: { per_page: number; total: number } };
    loading: boolean;
    page: number;
    onPageChange: (page: number) => void;
}) {
    return (
        <Table<ProductionDispatch>
            rowKey="id"
            loading={loading}
            dataSource={data?.items}
            scroll={{ x: 1180 }}
            pagination={{
                current: page,
                pageSize: data?.meta.per_page,
                total: data?.meta.total,
                onChange: onPageChange,
            }}
            columns={[
                { title: '派工单号', dataIndex: 'code', width: 160 },
                {
                    title: '生产任务',
                    width: 160,
                    render: (_, record) => record.production_task?.code ?? '-',
                },
                {
                    title: '生产订单',
                    width: 140,
                    render: (_, record) => record.production_order?.code ?? '-',
                },
                {
                    title: '工序',
                    width: 170,
                    render: (_, record) =>
                        record.route_operation
                            ? `${record.route_operation.sequence}. ${record.route_operation.process.name}`
                            : '-',
                },
                {
                    title: '作业人员',
                    width: 150,
                    render: (_, record) =>
                        record.employee
                            ? `${record.employee.employee_no} ${record.employee.name}`
                            : '-',
                },
                {
                    title: '班组',
                    width: 120,
                    render: (_, record) => record.team?.name ?? '-',
                },
                {
                    title: '派工时间',
                    width: 170,
                    render: (_, record) =>
                        dayjs(record.dispatched_at).format('YYYY-MM-DD HH:mm'),
                },
                {
                    title: '任务状态',
                    width: 100,
                    render: (_, record) =>
                        record.production_task ? (
                            <Tag color={statusColor[record.production_task.status]}>
                                {record.production_task.status_label}
                            </Tag>
                        ) : (
                            '-'
                        ),
                },
            ]}
        />
    );
}

function CreateDispatchModal({
    saving,
    onSave,
    onClose,
}: {
    saving: boolean;
    onSave: (payload: ProductionDispatchPayload) => void;
    onClose: () => void;
}) {
    const [form] = Form.useForm();
    const taskId = Form.useWatch<number | undefined>('production_task_id', form);
    const teamId = Form.useWatch<number | undefined>('team_id', form);
    const tasks = useQuery({
        queryKey: ['production.tasks', 'options'],
        queryFn: () => listTasks({ per_page: 100 }),
    });
    const operations = useQuery({
        queryKey: ['production.tasks', taskId, 'operations'],
        queryFn: () => listTaskOperations(taskId as number),
        enabled: taskId !== undefined,
    });
    const teams = useQuery({
        queryKey: ['basic.teams', 'options'],
        queryFn: () => listTeams({ status: true, per_page: 100 }),
    });
    const employees = useQuery({
        queryKey: ['basic.employees', 'options'],
        queryFn: () => listEmployees({ status: true, per_page: 100 }),
    });
    const workers = (employees.data?.items ?? []).filter(
        (employee) => teamId === undefined || employee.team_id === teamId,
    );
    const dispatchableTasks = (tasks.data?.items ?? []).filter(
        (task) => task.status !== 'completed',
    );

    return (
        <Modal
            open
            title="新增派工"
            confirmLoading={saving}
            width={560}
            onCancel={onClose}
            onOk={() => form.submit()}
        >
            <Form
                form={form}
                layout="vertical"
                initialValues={{ dispatched_at: dayjs() }}
                onValuesChange={(changed) => {
                    if ('production_task_id' in changed) {
                        form.setFieldValue('route_operation_id', undefined);
                    }

                    if ('team_id' in changed) {
                        form.setFieldValue('employee_id', undefined);
                    }
                }}
                onFinish={(values) =>
                    onSave({
                        production_task_id: values.production_task_id,
                        route_operation_id: values.route_operation_id,
                        employee_id: values.employee_id,
                        team_id: values.team_id,
                        dispatched_at: (values.dispatched_at as Dayjs | undefined)?.toISOString(),
                    })
                }
            >
                <Form.Item
                    name="production_task_id"
                    label="生产任务"
                    rules={[{ required: true, message: '请选择生产任务' }]}
                >
                    <Select
                        showSearch
                        optionFilterProp="label"
                        loading={tasks.isLoading}
                        options={dispatchableTasks.map((task) => ({
                            value: task.id,
                            label: `${task.code}（${task.product?.name ?? ''}）`,
                        }))}
                    />
                </Form.Item>
                <Form.Item
                    name="route_operation_id"
                    label="工序"
                    rules={[{ required: true, message: '请选择工序' }]}
                >
                    <Select
                        showSearch
                        optionFilterProp="label"
                        loading={operations.isLoading}
                        disabled={taskId === undefined}
                        placeholder={taskId ? '请选择工序' : '请先选择生产任务'}
                        options={(operations.data ?? []).map((operation) => ({
                            value: operation.id,
                            label: `${operation.sequence}. ${operation.process.name}`,
                        }))}
                    />
                </Form.Item>
                <Form.Item
                    name="team_id"
                    label="作业班组"
                    rules={[{ required: true, message: '请选择作业班组' }]}
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
                    name="employee_id"
                    label="作业人员"
                    rules={[{ required: true, message: '请选择作业人员' }]}
                >
                    <Select
                        showSearch
                        optionFilterProp="label"
                        loading={employees.isLoading}
                        disabled={teamId === undefined}
                        placeholder={teamId ? '仅列出所选班组人员' : '请先选择作业班组'}
                        options={workers.map((employee) => ({
                            value: employee.id,
                            label: `${employee.employee_no} ${employee.name}`,
                        }))}
                    />
                </Form.Item>
                <Form.Item name="dispatched_at" label="派工时间">
                    <DatePicker showTime format="YYYY-MM-DD HH:mm" className="w-full" />
                </Form.Item>
            </Form>
        </Modal>
    );
}

export default function ProductionDispatches() {
    const { message } = App.useApp();
    const queryClient = useQueryClient();
    const user = useAuthStore((state) => state.user);
    const canReadAll = can(user, 'production.dispatches.index');
    const canReadMine = can(user, 'production.dispatches.mine');
    const [minePage, setMinePage] = useState(1);
    const [allPage, setAllPage] = useState(1);
    const [keyword, setKeyword] = useState('');
    const [taskId, setTaskId] = useState<number>();
    const [orderId, setOrderId] = useState<number>();
    const [employeeId, setEmployeeId] = useState<number>();
    const [dates, setDates] = useState<[Dayjs, Dayjs] | null>(null);
    const [creating, setCreating] = useState(false);

    const mine = useQuery({
        queryKey: ['production.dispatches', 'mine', minePage],
        queryFn: () => listMyDispatches({ page: minePage }),
        enabled: canReadMine,
    });
    const tasks = useQuery({
        queryKey: ['production.tasks', 'options'],
        queryFn: () => listTasks({ per_page: 100 }),
        enabled: canReadAll,
    });
    const orders = useQuery({
        queryKey: ['production.orders', 'options'],
        queryFn: () => listOrders({ per_page: 100 }),
        enabled: canReadAll,
    });
    const employees = useQuery({
        queryKey: ['basic.employees', 'options'],
        queryFn: () => listEmployees({ status: true, per_page: 100 }),
        enabled: canReadAll,
    });
    const all = useQuery({
        queryKey: [
            'production.dispatches',
            'all',
            allPage,
            keyword,
            taskId,
            orderId,
            employeeId,
            dates?.[0].format('YYYY-MM-DD'),
            dates?.[1].format('YYYY-MM-DD'),
        ],
        queryFn: () =>
            listDispatches({
                page: allPage,
                keyword,
                production_task_id: taskId,
                production_order_id: orderId,
                employee_id: employeeId,
                start_date: dates?.[0].format('YYYY-MM-DD'),
                end_date: dates?.[1].format('YYYY-MM-DD'),
            }),
        enabled: canReadAll,
    });

    const showError = (error: unknown) =>
        message.error(error instanceof ApiRequestError ? error.message : '操作失败');
    const create = useMutation({
        mutationFn: createDispatch,
        onSuccess: async () => {
            await queryClient.invalidateQueries({ queryKey: ['production.dispatches'] });
            message.success('派工单已创建');
            setCreating(false);
        },
        onError: showError,
    });

    const tabs = [];

    if (canReadAll) {
        tabs.push({
            key: 'all',
            label: '派工记录',
            children: (
                <>
                    <Space className="mb-4" wrap>
                        <Select
                            allowClear
                            showSearch
                            optionFilterProp="label"
                            placeholder="生产任务"
                            className="w-48"
                            loading={tasks.isLoading}
                            options={(tasks.data?.items ?? []).map((task) => ({
                                value: task.id,
                                label: task.code,
                            }))}
                            onChange={(value?: number) => {
                                setTaskId(value);
                                setAllPage(1);
                            }}
                        />
                        <Select
                            allowClear
                            showSearch
                            optionFilterProp="label"
                            placeholder="生产订单"
                            className="w-44"
                            loading={orders.isLoading}
                            options={(orders.data?.items ?? []).map((order) => ({
                                value: order.id,
                                label: order.code,
                            }))}
                            onChange={(value?: number) => {
                                setOrderId(value);
                                setAllPage(1);
                            }}
                        />
                        <Select
                            allowClear
                            showSearch
                            optionFilterProp="label"
                            placeholder="作业人员"
                            className="w-48"
                            loading={employees.isLoading}
                            options={(employees.data?.items ?? []).map((employee) => ({
                                value: employee.id,
                                label: `${employee.employee_no} ${employee.name}`,
                            }))}
                            onChange={(value?: number) => {
                                setEmployeeId(value);
                                setAllPage(1);
                            }}
                        />
                        <DatePicker.RangePicker
                            onChange={(value) => {
                                setDates(value?.[0] && value[1] ? [value[0], value[1]] : null);
                                setAllPage(1);
                            }}
                        />
                        <Input.Search
                            allowClear
                            placeholder="派工单 / 人员"
                            className="w-48"
                            onSearch={(value) => {
                                setKeyword(value);
                                setAllPage(1);
                            }}
                        />
                    </Space>
                    <DispatchTable
                        data={all.data}
                        loading={all.isLoading}
                        page={allPage}
                        onPageChange={setAllPage}
                    />
                </>
            ),
        });
    }

    if (canReadMine) {
        tabs.push({
            key: 'mine',
            label: '我的待办',
            children: (
                <DispatchTable
                    data={mine.data}
                    loading={mine.isLoading}
                    page={minePage}
                    onPageChange={setMinePage}
                />
            ),
        });
    }

    return (
        <>
            <Space className="mb-4 w-full justify-between" wrap>
                <Typography.Title level={4} className="mb-0!">
                    生产派工
                </Typography.Title>
                <Can code="button.production.dispatches.create">
                    <Button type="primary" onClick={() => setCreating(true)}>
                        新增派工
                    </Button>
                </Can>
            </Space>
            <Tabs items={tabs} defaultActiveKey={canReadAll ? 'all' : 'mine'} />
            {creating && (
                <CreateDispatchModal
                    saving={create.isPending}
                    onSave={(payload) => create.mutate(payload)}
                    onClose={() => setCreating(false)}
                />
            )}
        </>
    );
}
