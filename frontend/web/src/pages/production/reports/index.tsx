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
import {
    createReport,
    getReportOptions,
    listReports,
    listReportOperations,
    PRODUCTION_REPORT_TYPES,
    reverseReport,
} from '@/api/production';
import type {
    ProductionReport,
    ProductionReportPayload,
    ProductionReportType,
} from '@/api/production';
import { useAuthStore } from '@/stores/auth';
import { ApiRequestError } from '@/utils/api-error';
import { can } from '@/utils/permission';

const typeColor = Object.fromEntries(
    PRODUCTION_REPORT_TYPES.map(({ value, color }) => [value, color]),
);

function CreateReportModal({
    saving,
    onSave,
    onClose,
}: {
    saving: boolean;
    onSave: (payload: ProductionReportPayload) => void;
    onClose: () => void;
}) {
    const [form] = Form.useForm();
    const taskId = Form.useWatch<number | undefined>('production_task_id', form);
    const options = useQuery({
        queryKey: ['production.reports', 'options'],
        queryFn: getReportOptions,
    });
    const operations = useQuery({
        queryKey: ['production.reports', 'operations', taskId],
        queryFn: () => listReportOperations(taskId as number),
        enabled: taskId !== undefined,
    });
    const reportableTasks = options.data?.tasks ?? [];
    const selectedTask = reportableTasks.find((task) => task.id === taskId);
    const remaining = selectedTask ? Number(selectedTask.remaining_quantity) : undefined;
    const equipmentOptions = (options.data?.equipments ?? []).filter(
        (equipment) => selectedTask?.production_line_id == null
            || equipment.production_line_id === selectedTask.production_line_id,
    );

    return (
        <Modal
            open
            title="提交报工"
            confirmLoading={saving}
            width={560}
            onCancel={onClose}
            onOk={() => form.submit()}
        >
            <Form
                form={form}
                layout="vertical"
                initialValues={{ quantity: 1, qualified_quantity: 1, defective_quantity: 0, type: 'normal' }}
                onValuesChange={(changed) => {
                    if ('production_task_id' in changed) {
                        form.setFieldValue('route_operation_id', undefined);
                    }
                }}
                onFinish={(values) =>
                    onSave({
                        production_task_id: values.production_task_id,
                        route_operation_id: values.route_operation_id,
                        employee_id: values.employee_id,
                        equipment_id: values.equipment_id,
                        quantity: values.quantity,
                        qualified_quantity: values.qualified_quantity,
                        defective_quantity: values.defective_quantity,
                        type: values.type,
                        reported_at: (values.reported_at as Dayjs | undefined)?.toISOString(),
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
                        loading={options.isLoading}
                        options={reportableTasks.map((task) => ({
                            value: task.id,
                            label: `${task.code}（剩余 ${Number(task.remaining_quantity)}）`,
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
                    name="employee_id"
                    label="作业人员"
                    rules={[{ required: true, message: '请选择作业人员' }]}
                >
                    <Select
                        showSearch
                        optionFilterProp="label"
                        loading={options.isLoading}
                        options={(options.data?.employees ?? []).map((employee) => ({
                            value: employee.id,
                            label: `${employee.employee_no} ${employee.name}`,
                        }))}
                    />
                </Form.Item>
                <Form.Item name="equipment_id" label="使用设备">
                    <Select
                        allowClear
                        showSearch
                        optionFilterProp="label"
                        loading={options.isLoading}
                        placeholder="可选，运行或待机设备"
                        options={equipmentOptions.map((equipment) => ({
                            value: equipment.id,
                            label: `${equipment.code} ${equipment.name}（${equipment.status_label}）`,
                        }))}
                    />
                </Form.Item>
                <Form.Item name="type" label="报工类型">
                    <Select
                        options={PRODUCTION_REPORT_TYPES.filter(
                            (item) => item.value !== 'reversed',
                        ).map(({ value, label }) => ({ value, label }))}
                    />
                </Form.Item>
                <Form.Item
                    name="quantity"
                    label="报工数量"
                    extra={remaining === undefined ? undefined : `任务剩余 ${remaining}`}
                    rules={[{ required: true, message: '请填写报工数量' }]}
                >
                    <InputNumber min={0.001} max={remaining} precision={3} className="w-full" />
                </Form.Item>
                <Form.Item
                    name="qualified_quantity"
                    label="合格数"
                    dependencies={['quantity']}
                    rules={[{ required: true, message: '请填写合格数' }]}
                >
                    <InputNumber min={0} max={remaining} precision={3} className="w-full" />
                </Form.Item>
                <Form.Item
                    name="defective_quantity"
                    label="不良数"
                    dependencies={['quantity', 'qualified_quantity']}
                    rules={[
                        { required: true, message: '请填写不良数' },
                        ({ getFieldValue }) => ({
                            validator(_, value: number | undefined) {
                                if (value === undefined) {
                                    return Promise.resolve();
                                }

                                const total = Number(getFieldValue('quantity') ?? 0);
                                const qualified = Number(getFieldValue('qualified_quantity') ?? 0);

                                return Math.round((qualified + value) * 1000) === Math.round(total * 1000)
                                    ? Promise.resolve()
                                    : Promise.reject(new Error('合格数 + 不良数必须等于报工数'));
                            },
                        }),
                    ]}
                >
                    <InputNumber min={0} max={remaining} precision={3} className="w-full" />
                </Form.Item>
                <Form.Item name="reported_at" label="报工时间">
                    <DatePicker showTime format="YYYY-MM-DD HH:mm" className="w-full" />
                </Form.Item>
            </Form>
        </Modal>
    );
}

export default function ProductionReports() {
    const { message } = App.useApp();
    const queryClient = useQueryClient();
    const user = useAuthStore((state) => state.user);
    const canRead = can(user, 'production.reports.index');
    const canCreate = can(user, 'button.production.reports.create');
    const [page, setPage] = useState(1);
    const [keyword, setKeyword] = useState('');
    const [taskId, setTaskId] = useState<number>();
    const [type, setType] = useState<ProductionReportType>();
    const [dates, setDates] = useState<[Dayjs, Dayjs] | null>(null);
    const [creating, setCreating] = useState(false);

    const tasks = useQuery({
        queryKey: ['production.reports', 'options'],
        queryFn: getReportOptions,
        enabled: canCreate,
    });
    const reports = useQuery({
        queryKey: [
            'production.reports',
            page,
            keyword,
            taskId,
            type,
            dates?.[0].format('YYYY-MM-DD'),
            dates?.[1].format('YYYY-MM-DD'),
        ],
        queryFn: () =>
            listReports({
                page,
                keyword,
                production_task_id: taskId,
                type,
                start_date: dates?.[0].format('YYYY-MM-DD'),
                end_date: dates?.[1].format('YYYY-MM-DD'),
            }),
        enabled: canRead,
    });

    const showError = (error: unknown) =>
        message.error(error instanceof ApiRequestError ? error.message : '操作失败');
    const invalidate = async () => {
        await Promise.all([
            queryClient.invalidateQueries({ queryKey: ['production.reports'] }),
            queryClient.invalidateQueries({ queryKey: ['production.tasks'] }),
            queryClient.invalidateQueries({ queryKey: ['production.orders'] }),
        ]);
    };
    const create = useMutation({
        mutationFn: createReport,
        onSuccess: async () => {
            await invalidate();
            message.success('报工已提交');
            setCreating(false);
        },
        onError: showError,
    });
    const reverse = useMutation({
        mutationFn: reverseReport,
        onSuccess: async () => {
            await invalidate();
            message.success('报工已冲销');
        },
        onError: showError,
    });

    return (
        <>
            <Space className="mb-4 w-full justify-between" wrap>
                <Typography.Title level={4} className="mb-0!">
                    生产报工
                </Typography.Title>
                <Can code="button.production.reports.create">
                    <Button type="primary" onClick={() => setCreating(true)}>
                        提交报工
                    </Button>
                </Can>
            </Space>
            <Space className="mb-4" wrap>
                {canCreate && (
                    <Select
                        allowClear
                        showSearch
                        optionFilterProp="label"
                        placeholder="生产任务"
                        className="w-48"
                        loading={tasks.isLoading}
                        options={(tasks.data?.tasks ?? []).map((task) => ({
                            value: task.id,
                            label: task.code,
                        }))}
                        onChange={(value?: number) => {
                            setTaskId(value);
                            setPage(1);
                        }}
                    />
                )}
                <Select
                    allowClear
                    placeholder="报工类型"
                    className="w-32"
                    options={PRODUCTION_REPORT_TYPES.map(({ value, label }) => ({ value, label }))}
                    onChange={(value?: ProductionReportType) => {
                        setType(value);
                        setPage(1);
                    }}
                />
                <DatePicker.RangePicker
                    onChange={(value) => {
                        setDates(value?.[0] && value[1] ? [value[0], value[1]] : null);
                        setPage(1);
                    }}
                />
                <Input.Search
                    allowClear
                    placeholder="报工单 / 任务 / 人员"
                    className="w-48"
                    onSearch={(value) => {
                        setKeyword(value);
                        setPage(1);
                    }}
                />
            </Space>
            <Table<ProductionReport>
                rowKey="id"
                loading={reports.isLoading}
                dataSource={reports.data?.items}
                scroll={{ x: 1280 }}
                pagination={{
                    current: page,
                    pageSize: reports.data?.meta.per_page,
                    total: reports.data?.meta.total,
                    onChange: setPage,
                }}
                columns={[
                    { title: '报工单号', dataIndex: 'code', width: 160 },
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
                        width: 160,
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
                        title: '设备',
                        width: 150,
                        render: (_, record) =>
                            record.equipment ? `${record.equipment.code} ${record.equipment.name}` : '-',
                    },
                    {
                        title: '报工数量',
                        width: 100,
                        render: (_, record) => Number(record.quantity),
                    },
                    {
                        title: '合格数',
                        width: 90,
                        render: (_, record) => Number(record.qualified_quantity),
                    },
                    {
                        title: '不良数',
                        width: 90,
                        render: (_, record) => Number(record.defective_quantity),
                    },
                    {
                        title: '类型',
                        width: 90,
                        render: (_, record) => (
                            <Tag color={typeColor[record.type]}>{record.type_label}</Tag>
                        ),
                    },
                    {
                        title: '报工时间',
                        width: 170,
                        render: (_, record) =>
                            dayjs(record.reported_at).format('YYYY-MM-DD HH:mm'),
                    },
                    {
                        title: '操作',
                        width: 90,
                        fixed: 'right' as const,
                        render: (_, record) =>
                            record.type === 'reversed' ? (
                                <Typography.Text type="secondary">不可冲销</Typography.Text>
                            ) : (
                                <Can code="button.production.reports.reverse">
                                    <Popconfirm
                                        title="确认冲销该报工？冲销后数量将回退"
                                        onConfirm={() => reverse.mutate(record.id)}
                                    >
                                        <Button type="link" danger loading={reverse.isPending}>
                                            冲销
                                        </Button>
                                    </Popconfirm>
                                </Can>
                            ),
                    },
                ]}
            />
            {creating && (
                <CreateReportModal
                    saving={create.isPending}
                    onSave={(payload) => create.mutate(payload)}
                    onClose={() => setCreating(false)}
                />
            )}
        </>
    );
}
