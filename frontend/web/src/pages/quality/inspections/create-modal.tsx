import { useQuery } from '@tanstack/react-query';
import { Form, Input, Modal, Radio, Select } from 'antd';
import { getInspectionOptions } from '@/api/quality';
import type { InspectionTaskPayload } from '@/api/quality';

export default function CreateInspectionModal({
    saving,
    onSave,
    onClose,
}: {
    saving: boolean;
    onSave: (payload: InspectionTaskPayload) => void;
    onClose: () => void;
}) {
    const [form] = Form.useForm();
    const orderId = Form.useWatch<number | undefined>('production_order_id', form);
    const type = Form.useWatch<string | undefined>('inspection_type', form);
    const isIpqc = (type ?? 'ipqc') === 'ipqc';
    const orders = useQuery({
        queryKey: ['quality.inspections', 'options'],
        queryFn: () => getInspectionOptions(),
    });
    const orderOptions = useQuery({
        queryKey: ['quality.inspections', 'options', orderId],
        queryFn: () => getInspectionOptions(orderId),
        enabled: orderId !== undefined,
    });
    return (
        <Modal
            open
            title="新建检验任务"
            okText="创建"
            confirmLoading={saving}
            onCancel={onClose}
            onOk={() => form.submit()}
        >
            <Form
                form={form}
                layout="vertical"
                initialValues={{ inspection_type: 'ipqc' }}
                onValuesChange={(changed) => {
                    if ('production_order_id' in changed || 'inspection_type' in changed) {
                        form.setFieldValue('process_record_id', undefined);
                        form.setFieldValue('route_operation_id', undefined);
                    }
                }}
                onFinish={(values) => onSave({
                    production_order_id: values.production_order_id,
                    inspection_type: values.inspection_type,
                    process_record_id: values.inspection_type === 'ipqc' ? values.process_record_id : null,
                    route_operation_id: values.inspection_type === 'oqc' ? values.route_operation_id : null,
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
                        optionFilterProp="label"
                        loading={orders.isLoading}
                        options={(orders.data?.orders ?? []).map((order) => ({
                            value: order.id,
                            label: `${order.code} ${order.product.name}`,
                        }))}
                    />
                </Form.Item>
                <Form.Item name="inspection_type" label="检验类型" rules={[{ required: true }]}>
                    <Radio.Group
                        options={[
                            { value: 'ipqc', label: 'IPQC 过程检验' },
                            { value: 'oqc', label: 'OQC 出货检验' },
                        ]}
                    />
                </Form.Item>
                {isIpqc && (
                    <Form.Item
                        name="process_record_id"
                        label="待检验工序"
                        rules={[{ required: true, message: '请选择待检验工序' }]}
                        extra={orderId ? undefined : '请先选择生产订单'}
                    >
                        <Select
                            showSearch
                            optionFilterProp="label"
                            loading={orderOptions.isLoading}
                            disabled={orderId === undefined}
                            notFoundContent={orderId ? '该订单暂无待检验工序' : undefined}
                            options={(orderOptions.data?.process_records ?? []).map((record) => ({
                                value: record.id,
                                label: `${record.sequence}. ${record.route_operation.process.name}${record.sn ? `（${record.sn}）` : ''}`,
                            }))}
                        />
                    </Form.Item>
                )}
                {!isIpqc && (
                    <Form.Item
                        name="route_operation_id"
                        label="工序"
                        rules={[{ required: true, message: '请选择订单工艺路线中的工序' }]}
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
                )}
                <Form.Item name="remark" label="备注">
                    <Input.TextArea rows={2} maxLength={255} />
                </Form.Item>
            </Form>
        </Modal>
    );
}
