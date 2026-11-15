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
import { listProductionLines } from '@/api/basic-data';
import { listProducts } from '@/api/products';
import {
    createOrder,
    listApprovedBoms,
    listOrders,
    listUsableRoutes,
    PRODUCTION_ORDER_STATUSES,
    transitionOrder,
    updateOrder,
} from '@/api/production';
import type {
    ProductionOrder,
    ProductionOrderAction,
    ProductionOrderPayload,
    ProductionOrderStatus,
} from '@/api/production';
import { ApiRequestError } from '@/utils/api-error';

type Editing = { mode: 'create' } | { mode: 'edit'; record: ProductionOrder };

const statusOptions = PRODUCTION_ORDER_STATUSES.map(({ value, label }) => ({ value, label }));
const statusColor = Object.fromEntries(
    PRODUCTION_ORDER_STATUSES.map(({ value, color }) => [value, color]),
);

function OrderFormModal({
    editing,
    onSave,
    saving,
    onClose,
}: {
    editing: Editing;
    onSave: (payload: ProductionOrderPayload) => void;
    saving: boolean;
    onClose: () => void;
}) {
    const [form] = Form.useForm();
    const productId = Form.useWatch<number | undefined>('product_id', form);
    const products = useQuery({
        queryKey: ['basic.products', 'options'],
        queryFn: () => listProducts({ status: true, per_page: 100 }),
    });
    const lines = useQuery({
        queryKey: ['basic.lines', 'options'],
        queryFn: () => listProductionLines({ status: true, per_page: 100 }),
    });
    const boms = useQuery({ queryKey: ['basic.boms', 'approved'], queryFn: listApprovedBoms });
    const routes = useQuery({
        queryKey: ['process.routes', 'usable', productId],
        queryFn: () => listUsableRoutes(productId!),
        enabled: productId !== undefined,
    });

    const bomOptions = (boms.data?.items ?? [])
        .filter((bom) => bom.product_version.product.id === productId)
        .map((bom) => ({
            value: bom.id,
            label: `${bom.code} ${bom.version}（产品版本 ${bom.product_version.version}）`,
        }));
    const routeOptions = (routes.data?.items ?? []).map((route) => ({
        value: route.id,
        label: `${route.code} ${route.version}`,
    }));

    const initialValues =
        editing.mode === 'edit'
            ? {
                  ...editing.record,
                  planned_quantity: Number(editing.record.planned_quantity),
                  planned_start_at: dayjs(editing.record.planned_start_at),
                  planned_end_at: editing.record.planned_end_at ? dayjs(editing.record.planned_end_at) : null,
              }
            : { planned_quantity: 1 };

    return (
        <Modal
            open
            title={editing.mode === 'create' ? '新增生产订单' : '编辑生产订单'}
            confirmLoading={saving}
            width={640}
            onCancel={onClose}
            onOk={() => form.submit()}
        >
            <Form
                form={form}
                layout="vertical"
                initialValues={initialValues}
                onValuesChange={(changed) => {
                    if ('product_id' in changed) {
                        form.setFieldsValue({ bom_id: undefined, route_id: undefined });
                    }
                }}
                onFinish={(values) =>
                    onSave({
                        code: values.code,
                        product_id: values.product_id,
                        bom_id: values.bom_id,
                        route_id: values.route_id,
                        production_line_id: values.production_line_id,
                        planned_quantity: values.planned_quantity,
                        planned_start_at: (values.planned_start_at as Dayjs).toISOString(),
                        planned_end_at: values.planned_end_at
                            ? (values.planned_end_at as Dayjs).toISOString()
                            : null,
                        remark: values.remark ?? null,
                    })
                }
            >
                <Form.Item name="code" label="订单号" rules={[{ required: true, message: '请填写订单号' }]}>
                    <Input placeholder="如 PO-2026-001" />
                </Form.Item>
                <Form.Item name="product_id" label="产品" rules={[{ required: true, message: '请选择产品' }]}>
                    <Select
                        showSearch
                        optionFilterProp="label"
                        loading={products.isLoading}
                        options={(products.data?.items ?? []).map((product) => ({
                            value: product.id,
                            label: `${product.code} ${product.name}`,
                        }))}
                    />
                </Form.Item>
                <Form.Item name="bom_id" label="BOM 版本" rules={[{ required: true, message: '请选择 BOM' }]}>
                    <Select
                        showSearch
                        optionFilterProp="label"
                        loading={boms.isLoading}
                        options={bomOptions}
                        placeholder={productId ? '仅列出已审核且启用的 BOM' : '请先选择产品'}
                        disabled={!productId}
                    />
                </Form.Item>
                <Form.Item
                    name="route_id"
                    label="工艺路线版本"
                    rules={[{ required: true, message: '请选择工艺路线' }]}
                >
                    <Select
                        showSearch
                        optionFilterProp="label"
                        loading={routes.isLoading}
                        options={routeOptions}
                        placeholder={productId ? '仅列出已审核且启用的路线' : '请先选择产品'}
                        disabled={!productId}
                    />
                </Form.Item>
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
                    name="planned_quantity"
                    label="计划数量"
                    rules={[{ required: true, message: '请填写计划数量' }]}
                >
                    <InputNumber min={0.001} precision={3} className="w-full" />
                </Form.Item>
                <Form.Item
                    name="planned_start_at"
                    label="计划开始时间"
                    rules={[{ required: true, message: '请选择计划开始时间' }]}
                >
                    <DatePicker showTime format="YYYY-MM-DD HH:mm" className="w-full" />
                </Form.Item>
                <Form.Item name="planned_end_at" label="计划结束时间">
                    <DatePicker showTime format="YYYY-MM-DD HH:mm" className="w-full" />
                </Form.Item>
                <Form.Item name="remark" label="备注">
                    <Input.TextArea rows={3} />
                </Form.Item>
            </Form>
        </Modal>
    );
}

function OrderActions({
    record,
    onAct,
}: {
    record: ProductionOrder;
    onAct: (action: ProductionOrderAction) => void;
}) {
    const button = (action: ProductionOrderAction, label: string, permission: string, danger = false) => (
        <Can code={`button.production.orders.${permission}`}>
            <Button type="link" danger={danger} onClick={() => onAct(action)}>
                {label}
            </Button>
        </Can>
    );
    const confirm = (action: ProductionOrderAction, label: string, permission: string) => (
        <Can code={`button.production.orders.${permission}`}>
            <Popconfirm title={`确认${label}该订单？`} okText={label} cancelText="取消" onConfirm={() => onAct(action)}>
                <Button type="link" danger>
                    {label}
                </Button>
            </Popconfirm>
        </Can>
    );

    return (
        <Space wrap>
            {record.status === 'draft' && button('submit', '提交审核', 'submit')}
            {record.status === 'pending' && button('approve', '审核', 'audit')}
            {record.status === 'approved' && button('release', '下达', 'release')}
            {record.status === 'released' && button('start', '开工', 'start')}
            {record.status === 'in_progress' && button('pause', '暂停', 'pause')}
            {record.status === 'in_progress' && button('complete', '完成', 'complete')}
            {record.status === 'paused' && button('resume', '恢复', 'resume')}
            {record.status === 'completed' && confirm('close', '关闭', 'close')}
            {['draft', 'pending', 'approved', 'released', 'in_progress', 'paused'].includes(record.status) &&
                confirm('cancel', '取消', 'cancel')}
        </Space>
    );
}

export default function ProductionOrders() {
    const { message } = App.useApp();
    const queryClient = useQueryClient();
    const [page, setPage] = useState(1);
    const [keyword, setKeyword] = useState('');
    const [status, setStatus] = useState<ProductionOrderStatus>();
    const [productId, setProductId] = useState<number>();
    const [lineId, setLineId] = useState<number>();
    const [dates, setDates] = useState<[Dayjs, Dayjs] | null>(null);
    const [editing, setEditing] = useState<Editing | null>(null);

    const products = useQuery({
        queryKey: ['basic.products', 'options'],
        queryFn: () => listProducts({ status: true, per_page: 100 }),
    });
    const lines = useQuery({
        queryKey: ['basic.lines', 'options'],
        queryFn: () => listProductionLines({ status: true, per_page: 100 }),
    });
    const query = useQuery({
        queryKey: [
            'production.orders',
            page,
            keyword,
            status,
            productId,
            lineId,
            dates?.[0].format('YYYY-MM-DD'),
            dates?.[1].format('YYYY-MM-DD'),
        ],
        queryFn: () =>
            listOrders({
                page,
                keyword,
                status,
                product_id: productId,
                production_line_id: lineId,
                start_date: dates?.[0].format('YYYY-MM-DD'),
                end_date: dates?.[1].format('YYYY-MM-DD'),
            }),
    });

    const showError = (error: unknown) =>
        message.error(error instanceof ApiRequestError ? error.message : '操作失败');
    const save = useMutation({
        mutationFn: (payload: ProductionOrderPayload) =>
            editing?.mode === 'edit' ? updateOrder(editing.record.id, payload) : createOrder(payload),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['production.orders'] });
            message.success('已保存');
            setEditing(null);
        },
        onError: showError,
    });
    const act = useMutation({
        mutationFn: ({ id, action }: { id: number; action: ProductionOrderAction }) =>
            transitionOrder(id, action),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['production.orders'] });
            message.success('状态已更新');
        },
        onError: showError,
    });

    return (
        <>
            <Space className="mb-4 w-full justify-between" wrap>
                <Typography.Title level={4} className="mb-0!">
                    生产订单
                </Typography.Title>
                <Space wrap>
                    <Select
                        allowClear
                        placeholder="状态"
                        className="w-32"
                        options={statusOptions}
                        onChange={(value?: ProductionOrderStatus) => {
                            setStatus(value);
                            setPage(1);
                        }}
                    />
                    <Select
                        allowClear
                        showSearch
                        optionFilterProp="label"
                        placeholder="产品"
                        className="w-48"
                        loading={products.isLoading}
                        options={(products.data?.items ?? []).map((product) => ({
                            value: product.id,
                            label: `${product.code} ${product.name}`,
                        }))}
                        onChange={(value?: number) => {
                            setProductId(value);
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
                    <DatePicker.RangePicker
                        onChange={(value) => {
                            setDates(value?.[0] && value[1] ? [value[0], value[1]] : null);
                            setPage(1);
                        }}
                    />
                    <Input.Search
                        allowClear
                        placeholder="订单号"
                        className="w-48"
                        onSearch={(value) => {
                            setKeyword(value);
                            setPage(1);
                        }}
                    />
                    <Can code="button.production.orders.create">
                        <Button type="primary" onClick={() => setEditing({ mode: 'create' })}>
                            新增
                        </Button>
                    </Can>
                </Space>
            </Space>
            <Table<ProductionOrder>
                rowKey="id"
                loading={query.isLoading}
                dataSource={query.data?.items}
                pagination={{
                    current: page,
                    pageSize: query.data?.meta.per_page,
                    total: query.data?.meta.total,
                    onChange: setPage,
                }}
                columns={[
                    { title: '订单号', dataIndex: 'code', width: 140 },
                    {
                        title: '产品',
                        render: (_, record) =>
                            record.product ? `${record.product.code} ${record.product.name}` : '-',
                    },
                    {
                        title: '状态',
                        dataIndex: 'status',
                        width: 90,
                        render: (value: ProductionOrderStatus, record) => (
                            <Tag color={statusColor[value]}>{record.status_label}</Tag>
                        ),
                    },
                    { title: 'BOM 版本', dataIndex: 'bom_version', width: 100 },
                    { title: '路线版本', dataIndex: 'route_version', width: 100 },
                    {
                        title: '产线',
                        width: 140,
                        render: (_, record) =>
                            record.production_line
                                ? `${record.production_line.code} ${record.production_line.name}`
                                : '-',
                    },
                    {
                        title: '计划 / 已生产',
                        width: 130,
                        render: (_, record) =>
                            `${Number(record.produced_quantity)} / ${Number(record.planned_quantity)}`,
                    },
                    {
                        title: '计划时间',
                        width: 150,
                        render: (_, record) => dayjs(record.planned_start_at).format('YYYY-MM-DD HH:mm'),
                    },
                    {
                        title: '操作',
                        key: 'actions',
                        width: 220,
                        render: (_, record) => (
                            <Space wrap>
                                {record.status === 'draft' && (
                                    <Can code="button.production.orders.update">
                                        <Button type="link" onClick={() => setEditing({ mode: 'edit', record })}>
                                            编辑
                                        </Button>
                                    </Can>
                                )}
                                <OrderActions
                                    record={record}
                                    onAct={(action) => act.mutate({ id: record.id, action })}
                                />
                            </Space>
                        ),
                    },
                ]}
            />
            {editing && (
                <OrderFormModal
                    key={editing.mode === 'create' ? 'create' : editing.record.id}
                    editing={editing}
                    saving={save.isPending}
                    onSave={(payload) => save.mutate(payload)}
                    onClose={() => setEditing(null)}
                />
            )}
        </>
    );
}
