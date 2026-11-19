import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
    Alert,
    App,
    Button,
    DatePicker,
    Form,
    Input,
    InputNumber,
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
import { getInventoryOptions, listInventory } from '@/api/inventory';
import { listOrders } from '@/api/production';
import {
    createRequisition,
    issueRequisition,
    listRequisitions,
    requisitionSuggestions,
} from '@/api/requisitions';
import type { MaterialRequisition, RequisitionSuggestion } from '@/api/requisitions';
import { useAuthStore } from '@/stores/auth';
import { ApiRequestError } from '@/utils/api-error';
import { can } from '@/utils/permission';

export function filterRequisitionableOrders<T extends { can_requisition: boolean }>(orders: T[]): T[] {
    return orders.filter((order) => order.can_requisition);
}

function CreateModal({ onClose }: { onClose: () => void }) {
    const { message } = App.useApp();
    const client = useQueryClient();
    const [form] = Form.useForm();
    const orderId = Form.useWatch<number | undefined>('production_order_id', form);
    const orders = useQuery({
        queryKey: ['production.orders', 'requisition-options'],
        queryFn: () => listOrders({ per_page: 100 }),
    });
    const suggestions = useQuery({
        queryKey: ['material.requisitions', 'suggestions', orderId],
        queryFn: () => requisitionSuggestions(orderId!),
        enabled: orderId !== undefined,
    });
    const save = useMutation({
        mutationFn: createRequisition,
        onSuccess: async () => {
            await client.invalidateQueries({ queryKey: ['material.requisitions'] });
            message.success('领料单已创建');
            onClose();
        },
        onError: (error) => message.error(error instanceof ApiRequestError ? error.message : '创建失败'),
    });

    return (
        <Modal
            open
            title="创建领料单"
            okText="创建"
            confirmLoading={save.isPending}
            onCancel={onClose}
            onOk={() => form.submit()}
        >
            <Form
                form={form}
                layout="vertical"
                onFinish={(values) => save.mutate({
                    production_order_id: values.production_order_id,
                    remark: values.remark ?? null,
                    items: (suggestions.data ?? [])
                        .filter((item) => (values.quantities?.[item.material.id] ?? 0) > 0)
                        .map((item) => ({
                            material_id: item.material.id,
                            requested_quantity: values.quantities[item.material.id],
                        })),
                })}
            >
                <Form.Item name="production_order_id" label="生产订单" rules={[{ required: true, message: '请选择生产订单' }]}>
                    <Select
                        showSearch
                        optionFilterProp="label"
                        placeholder="请选择已下达、生产中或已暂停的订单"
                        options={filterRequisitionableOrders(orders.data?.items ?? [])
                            .map((order) => ({ value: order.id, label: `${order.code} ${order.status_label}` }))}
                    />
                </Form.Item>
                {(suggestions.data ?? []).map((item: RequisitionSuggestion) => (
                    <Form.Item
                        key={item.material.id}
                        name={['quantities', item.material.id]}
                        label={`${item.material.code} ${item.material.name}`}
                        initialValue={item.suggested_quantity}
                        extra={`BOM 需求 ${item.required_quantity}，已领 ${item.issued_quantity}，建议 ${item.suggested_quantity} ${item.material.unit}`}
                    >
                        <InputNumber min={0} precision={3} step={0.001} className="w-full" />
                    </Form.Item>
                ))}
                <Form.Item name="remark" label="备注">
                    <Input maxLength={255} />
                </Form.Item>
            </Form>
        </Modal>
    );
}

function IssueModal({ requisition, onClose }: { requisition: MaterialRequisition; onClose: () => void }) {
    const { message } = App.useApp();
    const client = useQueryClient();
    const [form] = Form.useForm();
    const options = useQuery({ queryKey: ['inventory', 'options'], queryFn: getInventoryOptions });
    const stock = useQuery({
        queryKey: ['inventory', 'requisition', requisition.id],
        queryFn: () => listInventory({ per_page: 100 }),
    });
    const save = useMutation({
        mutationFn: (values: { items: Record<number, { warehouse_id: number; location_id: number; batch_no?: string; quantity: number }> }) => issueRequisition(requisition.id, {
            items: requisition.items.map((item) => ({
                id: item.id,
                warehouse_id: values.items[item.id].warehouse_id,
                location_id: values.items[item.id].location_id,
                batch_no: values.items[item.id].batch_no ?? null,
                quantity: values.items[item.id].quantity,
            })),
        }),
        onSuccess: async (result) => {
            await client.invalidateQueries({ queryKey: ['material.requisitions'] });
            await client.invalidateQueries({ queryKey: ['inventory'] });
            const warned = result.items.some((item) => item.over_requirement);
            message.success(warned ? '已发料，累计领料超过 BOM 需求' : '已发料');
            onClose();
        },
        onError: (error) => message.error(error instanceof ApiRequestError ? error.message : '发料失败'),
    });

    return (
        <Modal open title={`发料 ${requisition.code}`} okText="确认发料" confirmLoading={save.isPending} onCancel={onClose} onOk={() => form.submit()}>
            <Form form={form} layout="vertical" onFinish={(values) => save.mutate(values)}>
                {requisition.items.map((item) => {
                    const available = (stock.data?.items ?? [])
                        .filter((row) => row.material_id === item.material_id)
                        .reduce((sum, row) => sum + row.available_quantity, 0);

                    return (
                        <div key={item.id}>
                            <Typography.Text strong>
                                {item.material?.code} {item.material?.name}，申请 {item.requested_quantity} {item.material?.unit}
                            </Typography.Text>
                            <Typography.Paragraph type="secondary">当前可用库存合计：{available}</Typography.Paragraph>
                            <Form.Item name={['items', item.id, 'warehouse_id']} label="仓库" rules={[{ required: true, message: '请选择仓库' }]}>
                                <Select options={(options.data?.warehouses ?? []).map((warehouse) => ({ value: warehouse.id, label: `${warehouse.code} ${warehouse.name}` }))} />
                            </Form.Item>
                            <Form.Item name={['items', item.id, 'location_id']} label="库位" rules={[{ required: true, message: '请选择库位' }]}>
                                <Select options={(options.data?.warehouses ?? []).flatMap((warehouse) => warehouse.locations.map((location) => ({ value: location.id, label: `${warehouse.code} / ${location.code}` })))} />
                            </Form.Item>
                            <Form.Item name={['items', item.id, 'batch_no']} label="批次号">
                                <Input maxLength={80} />
                            </Form.Item>
                            <Form.Item name={['items', item.id, 'quantity']} label="发料数量" initialValue={item.requested_quantity} rules={[{ required: true, message: '请填写发料数量' }]}>
                                <InputNumber min={0.001} precision={3} step={0.001} className="w-full" />
                            </Form.Item>
                        </div>
                    );
                })}
            </Form>
        </Modal>
    );
}

export default function MaterialRequisitions() {
    const user = useAuthStore((state) => state.user);
    const [page, setPage] = useState(1);
    const [orderId, setOrderId] = useState<number>();
    const [dates, setDates] = useState<[Dayjs, Dayjs] | null>(null);
    const [creating, setCreating] = useState(false);
    const [issuing, setIssuing] = useState<MaterialRequisition>();
    const orders = useQuery({ queryKey: ['production.orders', 'filter'], queryFn: () => listOrders({ per_page: 100 }) });
    const requisitions = useQuery({
        queryKey: ['material.requisitions', page, orderId, dates?.map((date) => date.format('YYYY-MM-DD'))],
        queryFn: () => listRequisitions({
            page,
            production_order_id: orderId,
            date_from: dates?.[0].format('YYYY-MM-DD'),
            date_to: dates?.[1].format('YYYY-MM-DD'),
        }),
        enabled: can(user, 'material.requisitions.index'),
    });

    if (!can(user, 'material.requisitions.index')) {
        return <Alert type="warning" showIcon title="没有领料查询权限" />;
    }

    return (
        <Space orientation="vertical" size="middle" className="w-full">
            <Space>
                <Typography.Title level={4} className="!mb-0">领料管理</Typography.Title>
                <Can code="button.material.requisitions.create">
                    <Button type="primary" onClick={() => setCreating(true)}>创建领料单</Button>
                </Can>
            </Space>
            <Space>
                <Select
                    allowClear
                    showSearch
                    optionFilterProp="label"
                    placeholder="生产订单"
                    className="w-64"
                    value={orderId}
                    onChange={(value) => { setOrderId(value); setPage(1); }}
                    options={(orders.data?.items ?? []).map((order) => ({ value: order.id, label: order.code }))}
                />
                <DatePicker.RangePicker
                    value={dates}
                    onChange={(value) => {
                        setDates(value?.[0] && value[1] ? [value[0], value[1]] : null);
                        setPage(1);
                    }}
                />
            </Space>
            <Table
                rowKey="id"
                loading={requisitions.isLoading}
                dataSource={requisitions.data?.items ?? []}
                pagination={{ current: page, total: requisitions.data?.meta.total, onChange: setPage }}
                columns={[
                    { title: '领料单号', dataIndex: 'code' },
                    { title: '生产订单', render: (_, record) => record.production_order?.code },
                    { title: '物料', render: (_, record) => record.items.map((item) => `${item.material?.code} ${item.requested_quantity}`).join('、') },
                    { title: '状态', dataIndex: 'status', render: (status) => <Tag color={status === 'issued' ? 'green' : 'gold'}>{status === 'issued' ? '已发料' : '待发料'}</Tag> },
                    { title: '创建时间', dataIndex: 'created_at', render: (value) => businessTime(value).format('YYYY-MM-DD HH:mm') },
                    {
                        title: '操作',
                        render: (_, record) => record.status === 'pending' && can(user, 'button.material.requisitions.issue') && (
                            <Button type="link" onClick={() => setIssuing(record)}>发料</Button>
                        ),
                    },
                ]}
            />
            {creating && <CreateModal onClose={() => setCreating(false)} />}
            {issuing && <IssueModal requisition={issuing} onClose={() => setIssuing(undefined)} />}
        </Space>
    );
}
