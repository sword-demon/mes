import { useQuery } from '@tanstack/react-query';
import { Form, InputNumber, Modal, Select } from 'antd';
import { getProductSnOptions } from '@/api/production';
import type { ProductSnGeneratePayload } from '@/api/production';

export default function GenerateSnModal({
    saving,
    onSave,
    onClose,
}: {
    saving: boolean;
    onSave: (payload: ProductSnGeneratePayload) => void;
    onClose: () => void;
}) {
    const [form] = Form.useForm();
    const options = useQuery({
        queryKey: ['production.sns', 'options'],
        queryFn: getProductSnOptions,
    });

    return (
        <Modal
            open
            title="生成 SN"
            confirmLoading={saving}
            width={520}
            onCancel={onClose}
            onOk={() => form.submit()}
        >
            <Form
                form={form}
                layout="vertical"
                initialValues={{ quantity: 1 }}
                onFinish={(values) =>
                    onSave({
                        production_order_id: values.production_order_id,
                        quantity: values.quantity,
                        employee_id: values.employee_id,
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
                        loading={options.isLoading}
                        options={(options.data?.orders ?? []).map((order) => ({
                            value: order.id,
                            label: `${order.code}${order.product ? `（${order.product.name}）` : ''}`,
                        }))}
                    />
                </Form.Item>
                <Form.Item
                    name="quantity"
                    label="生成数量"
                    rules={[{ required: true, message: '请输入生成数量' }]}
                >
                    <InputNumber min={1} max={1000} className="w-full" />
                </Form.Item>
                <Form.Item name="employee_id" label="操作人员">
                    <Select
                        allowClear
                        showSearch
                        optionFilterProp="label"
                        options={(options.data?.employees ?? []).map((employee) => ({
                            value: employee.id,
                            label: `${employee.employee_no} ${employee.name}`,
                        }))}
                    />
                </Form.Item>
            </Form>
        </Modal>
    );
}
