import { useQuery } from '@tanstack/react-query';
import { Form, Input, InputNumber, Modal, Select } from 'antd';
import { useState } from 'react';
import { getDefectOptions } from '@/api/quality';
import type { DefectPayload } from '@/api/quality';
import { useDebouncedValue } from '@/hooks/use-debounced-value';

export default function CreateDefectModal({
    saving,
    onSave,
    onClose,
}: {
    saving: boolean;
    onSave: (payload: DefectPayload) => void;
    onClose: () => void;
}) {
    const [form] = Form.useForm();
    const [orderKeyword, setOrderKeyword] = useState('');
    const debouncedKeyword = useDebouncedValue(orderKeyword);
    const orderId = Form.useWatch<number | undefined>('production_order_id', form);
    const batchId = Form.useWatch<number | undefined>('production_batch_id', form);
    const orders = useQuery({
        queryKey: ['quality.defects', 'order-options', debouncedKeyword],
        queryFn: () => getDefectOptions(undefined, debouncedKeyword),
    });
    const orderOptions = useQuery({
        queryKey: ['quality.defects', 'order-detail', orderId],
        queryFn: () => getDefectOptions(orderId),
        enabled: orderId !== undefined,
    });
    const selectedBatch = orderOptions.data?.batches.find((batch) => batch.id === batchId);

    return (
        <Modal
            open
            title="登记不良品"
            okText="登记"
            confirmLoading={saving}
            onCancel={onClose}
            onOk={() => form.submit()}
        >
            <Form
                form={form}
                layout="vertical"
                onValuesChange={(changed) => {
                    if ('production_order_id' in changed) {
                        form.setFieldValue('route_operation_id', undefined);
                        form.setFieldValue('production_batch_id', undefined);
                    }
                }}
                onFinish={(values: DefectPayload) => onSave({
                    production_order_id: values.production_order_id,
                    route_operation_id: values.route_operation_id,
                    production_batch_id: values.production_batch_id,
                    defect_code_id: values.defect_code_id,
                    quantity: values.quantity,
                    remark: values.remark,
                })}
            >
                <Form.Item
                    name="production_order_id"
                    label="生产订单"
                    rules={[{ required: true, message: '请选择生产订单' }]}
                >
                    <Select
                        showSearch
                        filterOption={false}
                        onSearch={setOrderKeyword}
                        loading={orders.isLoading}
                        options={(orders.data?.orders ?? []).map((order) => ({
                            value: order.id,
                            label: `${order.code} ${order.product.name}`,
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
                        loading={orderOptions.isLoading}
                        disabled={orderId === undefined}
                        placeholder={orderId ? '请选择工序' : '请先选择生产订单'}
                        options={(orderOptions.data?.operations ?? []).map((operation) => ({
                            value: operation.id,
                            label: `${operation.sequence}. ${operation.process.name}`,
                        }))}
                    />
                </Form.Item>
                <Form.Item
                    name="production_batch_id"
                    label="生产批次"
                    rules={[{ required: true, message: '请选择生产批次' }]}
                >
                    <Select
                        showSearch
                        optionFilterProp="label"
                        loading={orderOptions.isLoading}
                        disabled={orderId === undefined}
                        placeholder={orderId ? '请选择批次' : '请先选择生产订单'}
                        options={(orderOptions.data?.batches ?? []).map((batch) => ({
                            value: batch.id,
                            label: `${batch.code}（${batch.quantity} 件）`,
                        }))}
                    />
                </Form.Item>
                <Form.Item
                    name="defect_code_id"
                    label="不良原因"
                    rules={[{ required: true, message: '请选择不良原因' }]}
                >
                    <Select
                        showSearch
                        optionFilterProp="label"
                        loading={orders.isLoading}
                        options={(orders.data?.defect_codes ?? []).map((code) => ({
                            value: code.id,
                            label: `${code.code} ${code.name}（${code.category}）`,
                        }))}
                    />
                </Form.Item>
                <Form.Item
                    name="quantity"
                    label="不良数量"
                    rules={[{ required: true, message: '请填写不良数量' }]}
                >
                    <InputNumber
                        className="w-full"
                        min={0.001}
                        max={selectedBatch?.quantity}
                        precision={3}
                    />
                </Form.Item>
                <Form.Item name="remark" label="备注">
                    <Input.TextArea rows={2} maxLength={255} />
                </Form.Item>
            </Form>
        </Modal>
    );
}
