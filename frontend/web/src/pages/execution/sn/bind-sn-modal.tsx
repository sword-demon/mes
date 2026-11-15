import { useQuery } from '@tanstack/react-query';
import { Form, Input, Modal, Select } from 'antd';
import { getProductSnOptions } from '@/api/production';
import type { ProductSn, ProductSnBindPayload } from '@/api/production';

export default function BindSnModal({
    sn,
    saving,
    onSave,
    onClose,
}: {
    sn: ProductSn;
    saving: boolean;
    onSave: (payload: ProductSnBindPayload) => void;
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
            title={`绑定信息 · ${sn.sn}`}
            confirmLoading={saving}
            width={520}
            onCancel={onClose}
            onOk={() => form.submit()}
        >
            <Form
                form={form}
                layout="vertical"
                initialValues={{
                    material_batch_no: sn.material_batch_no ?? undefined,
                    employee_id: sn.employee_id ?? undefined,
                }}
                onFinish={(values) =>
                    onSave({
                        material_batch_no: values.material_batch_no || undefined,
                        employee_id: values.employee_id,
                    })
                }
            >
                <Form.Item name="material_batch_no" label="物料批次">
                    <Input placeholder="扫描或输入物料批次号" maxLength={64} />
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
