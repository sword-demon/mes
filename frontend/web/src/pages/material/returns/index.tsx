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
import type { FormInstance } from 'antd';
import dayjs from 'dayjs';
import type { Dayjs } from 'dayjs';
import { useState } from 'react';
import Can from '@/components/can';
import { getInventoryOptions } from '@/api/inventory';
import type { InventoryOptions } from '@/api/inventory';
import { listOrders } from '@/api/production';
import { listRequisitions } from '@/api/requisitions';
import {
    approveReturn,
    createReturn,
    listReturns,
    returnSuggestions,
} from '@/api/returns';
import type { MaterialReturn, MaterialReturnItem, ReturnSuggestion } from '@/api/returns';
import { useAuthStore } from '@/stores/auth';
import { ApiRequestError } from '@/utils/api-error';
import { can } from '@/utils/permission';

function CreateModal({ onClose }: { onClose: () => void }) {
    const { message } = App.useApp();
    const client = useQueryClient();
    const [form] = Form.useForm();
    const requisitionId = Form.useWatch<number | undefined>('requisition_id', form);
    const requisitions = useQuery({
        queryKey: ['material.requisitions', 'return-options'],
        queryFn: () => listRequisitions({ per_page: 100 }),
    });
    const suggestions = useQuery({
        queryKey: ['material.returns', 'suggestions', requisitionId],
        queryFn: () => returnSuggestions(requisitionId!),
        enabled: requisitionId !== undefined,
    });
    const save = useMutation({
        mutationFn: createReturn,
        onSuccess: async () => {
            await client.invalidateQueries({ queryKey: ['material.returns'] });
            message.success('退料单已创建');
            onClose();
        },
        onError: (error) => message.error(error instanceof ApiRequestError ? error.message : '创建失败'),
    });

    return (
        <Modal
            open
            title="创建退料单"
            okText="创建"
            confirmLoading={save.isPending}
            okButtonProps={{ disabled: requisitionId !== undefined && (suggestions.isLoading || !suggestions.data?.length) }}
            onCancel={onClose}
            onOk={() => form.submit()}
        >
            <Form
                form={form}
                layout="vertical"
                onFinish={(values) => save.mutate({
                    requisition_id: values.requisition_id,
                    remark: values.remark ?? null,
                    items: (suggestions.data ?? [])
                        .filter((item) => (values.quantities?.[item.requisition_item_id] ?? 0) > 0)
                        .map((item) => ({
                            requisition_item_id: item.requisition_item_id,
                            quantity: values.quantities[item.requisition_item_id],
                        })),
                })}
            >
                <Form.Item name="requisition_id" label="原领料单" rules={[{ required: true, message: '请选择领料单' }]}>
                    <Select
                        showSearch
                        optionFilterProp="label"
                        placeholder="请选择已领料的领料单"
                        options={(requisitions.data?.items ?? [])
                            .filter((requisition) => requisition.items.some((item) => item.issued_quantity > 0))
                            .map((requisition) => ({
                                value: requisition.id,
                                label: `${requisition.code} / ${requisition.production_order?.code ?? ''}`,
                            }))}
                    />
                </Form.Item>
                {(suggestions.data ?? []).map((item: ReturnSuggestion) => (
                    <Form.Item
                        key={item.requisition_item_id}
                        name={['quantities', item.requisition_item_id]}
                        label={`${item.material.code} ${item.material.name}`}
                        initialValue={item.returnable_quantity}
                        extra={`已领 ${item.issued_quantity}，已审退 ${item.approved_returned_quantity}，待审 ${item.pending_quantity}，可申请 ${item.returnable_quantity} ${item.material.unit}`}
                    >
                        <InputNumber min={0} max={item.returnable_quantity} precision={3} step={0.001} className="w-full" />
                    </Form.Item>
                ))}
                <Form.Item name="remark" label="备注">
                    <Input maxLength={255} />
                </Form.Item>
            </Form>
        </Modal>
    );
}

function ReturnItemFields({
    item,
    warehouses,
    form,
}: {
    item: MaterialReturnItem;
    warehouses: InventoryOptions['warehouses'];
    form: FormInstance;
}) {
    const warehouseId = Form.useWatch(['items', item.id, 'warehouse_id'], form);
    const locations = warehouses
        .filter((warehouse) => warehouse.id === warehouseId)
        .flatMap((warehouse) => warehouse.locations.map((location) => ({
            value: location.id,
            label: `${warehouse.code} / ${location.name}`,
        })));

    return (
        <div>
            <Typography.Text strong>
                {item.material?.code} {item.material?.name}，退料 {item.quantity} {item.material?.unit}
            </Typography.Text>
            <Form.Item name={['items', item.id, 'warehouse_id']} label="退回仓库" rules={[{ required: true, message: '请选择仓库' }]}>
                <Select
                    options={warehouses.map((warehouse) => ({ value: warehouse.id, label: `${warehouse.code} ${warehouse.name}` }))}
                    onChange={() => form.setFieldValue(['items', item.id, 'location_id'], undefined)}
                />
            </Form.Item>
            <Form.Item name={['items', item.id, 'location_id']} label="退回库位" rules={[{ required: true, message: '请选择库位' }]}>
                <Select disabled={warehouseId === undefined} options={locations} />
            </Form.Item>
            <Form.Item
                name={['items', item.id, 'batch_no']}
                label={item.material?.batch_managed === true ? '批次号（必填）' : '批次号'}
                rules={item.material?.batch_managed === true
                    ? [{ required: true, message: '批次管理物料必须填写批次号' }]
                    : []}
            >
                <Input maxLength={80} placeholder={item.material?.batch_managed === true ? '批次管理物料必填' : '选填'} />
            </Form.Item>
        </div>
    );
}

function ApproveModal({ materialReturn, onClose }: { materialReturn: MaterialReturn; onClose: () => void }) {
    const { message } = App.useApp();
    const client = useQueryClient();
    const [form] = Form.useForm();
    const options = useQuery({ queryKey: ['inventory', 'options'], queryFn: getInventoryOptions });
    const save = useMutation({
        mutationFn: (values: { items: Record<number, { warehouse_id: number; location_id: number; batch_no?: string }> }) => approveReturn(materialReturn.id, {
            items: materialReturn.items.map((item) => ({
                id: item.id,
                warehouse_id: values.items[item.id].warehouse_id,
                location_id: values.items[item.id].location_id,
                batch_no: values.items[item.id].batch_no ?? null,
            })),
        }),
        onSuccess: async () => {
            await client.invalidateQueries({ queryKey: ['material.returns'] });
            await client.invalidateQueries({ queryKey: ['inventory'] });
            message.success('退料已审核，库存已恢复');
            onClose();
        },
        onError: (error) => message.error(error instanceof ApiRequestError ? error.message : '审核失败'),
    });

    return (
        <Modal open title={`审核退料 ${materialReturn.code}`} okText="审核通过" confirmLoading={save.isPending} onCancel={onClose} onOk={() => form.submit()}>
            <Form form={form} layout="vertical" onFinish={(values) => save.mutate(values)}>
                {materialReturn.items.map((item) => (
                    <ReturnItemFields
                        key={item.id}
                        item={item}
                        warehouses={options.data?.warehouses ?? []}
                        form={form}
                    />
                ))}
            </Form>
        </Modal>
    );
}

export default function MaterialReturns() {
    const user = useAuthStore((state) => state.user);
    const [page, setPage] = useState(1);
    const [orderId, setOrderId] = useState<number>();
    const [dates, setDates] = useState<[Dayjs, Dayjs] | null>(null);
    const [creating, setCreating] = useState(false);
    const [approving, setApproving] = useState<MaterialReturn>();
    const orders = useQuery({ queryKey: ['production.orders', 'filter'], queryFn: () => listOrders({ per_page: 100 }) });
    const returns = useQuery({
        queryKey: ['material.returns', page, orderId, dates?.map((date) => date.format('YYYY-MM-DD'))],
        queryFn: () => listReturns({
            page,
            production_order_id: orderId,
            date_from: dates?.[0].format('YYYY-MM-DD'),
            date_to: dates?.[1].format('YYYY-MM-DD'),
        }),
        enabled: can(user, 'material.returns.index'),
    });

    if (!can(user, 'material.returns.index')) {
        return <Alert type="warning" showIcon title="没有退料查询权限" />;
    }

    return (
        <Space orientation="vertical" size="middle" className="w-full">
            <Space>
                <Typography.Title level={4} className="!mb-0">退料管理</Typography.Title>
                <Can code="button.material.returns.create">
                    <Button type="primary" onClick={() => setCreating(true)}>创建退料单</Button>
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
                loading={returns.isLoading}
                dataSource={returns.data?.items ?? []}
                pagination={{ current: page, total: returns.data?.meta.total, onChange: setPage }}
                columns={[
                    { title: '退料单号', dataIndex: 'code' },
                    { title: '生产订单', render: (_, record) => record.production_order?.code },
                    { title: '原领料单', render: (_, record) => record.requisition?.code },
                    { title: '物料', render: (_, record) => record.items.map((item) => `${item.material?.code} ${item.quantity}`).join('、') },
                    { title: '状态', dataIndex: 'status', render: (_, record) => <Tag color={record.status === 'approved' ? 'green' : 'gold'}>{record.status_label}</Tag> },
                    { title: '创建时间', dataIndex: 'created_at', render: (value) => dayjs(value).format('YYYY-MM-DD HH:mm') },
                    {
                        title: '操作',
                        render: (_, record) => record.status === 'pending' && can(user, 'button.material.returns.audit') && (
                            <Button type="link" onClick={() => setApproving(record)}>审核</Button>
                        ),
                    },
                ]}
            />
            {creating && <CreateModal onClose={() => setCreating(false)} />}
            {approving && <ApproveModal materialReturn={approving} onClose={() => setApproving(undefined)} />}
        </Space>
    );
}
