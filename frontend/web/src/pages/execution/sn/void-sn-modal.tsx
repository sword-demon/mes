import { Form, Input, Modal } from 'antd';
import type { ProductSn } from '@/api/production';

export default function VoidSnModal({
    sn,
    saving,
    onSave,
    onClose,
}: {
    sn: ProductSn;
    saving: boolean;
    onSave: (reason: string) => void;
    onClose: () => void;
}) {
    const [form] = Form.useForm();

    return (
        <Modal
            open
            title={`作废 SN · ${sn.sn}`}
            confirmLoading={saving}
            width={480}
            onCancel={onClose}
            onOk={() => form.submit()}
        >
            <Form form={form} layout="vertical" onFinish={(values) => onSave(values.reason)}>
                <Form.Item
                    name="reason"
                    label="作废原因"
                    rules={[{ required: true, message: '请填写作废原因' }]}
                >
                    <Input.TextArea rows={3} maxLength={255} placeholder="如：产品报废" />
                </Form.Item>
            </Form>
        </Modal>
    );
}
