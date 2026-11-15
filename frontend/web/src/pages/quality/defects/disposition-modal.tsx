import { Form, Input, Modal, Radio, Typography } from 'antd';
import type { DefectiveProduct } from '@/api/quality';
import type { DefectDisposition } from '@/api/quality';

export type DispositionMode = 'rework' | 'scrap' | 'recheck';

const MODES: Record<DispositionMode, { title: string; okText: string }> = {
    rework: { title: '返工处理', okText: '生成返工单' },
    scrap: { title: '报废处理', okText: '确认报废' },
    recheck: { title: '复检判定', okText: '提交复检' },
};

export default function DispositionModal({
    defect,
    mode,
    saving,
    onSave,
    onClose,
}: {
    defect: DefectiveProduct;
    mode: DispositionMode;
    saving: boolean;
    onSave: (disposition: DefectDisposition) => void;
    onClose: () => void;
}) {
    const [form] = Form.useForm();

    return (
        <Modal
            open
            title={`${MODES[mode].title} · ${defect.production_order.code}`}
            okText={MODES[mode].okText}
            confirmLoading={saving}
            onCancel={onClose}
            onOk={() => form.submit()}
        >
            <Form
                form={form}
                layout="vertical"
                onFinish={(values: { reason?: string; passed?: boolean; remark?: string | null }) => {
                    if (mode === 'recheck') {
                        onSave({ mode, passed: values.passed ?? false, remark: values.remark });

                        return;
                    }

                    if (mode === 'scrap') {
                        onSave({
                            mode,
                            reason: values.reason ?? '',
                            quantity: Number(defect.quantity),
                        });

                        return;
                    }

                    onSave({ mode, reason: values.reason ?? '' });
                }}
            >
                {mode === 'recheck' ? (
                    <>
                        <Form.Item
                            name="passed"
                            label="复检结果"
                            rules={[{ required: true, message: '请选择复检结果' }]}
                        >
                            <Radio.Group
                                options={[
                                    { value: true, label: '合格，关闭不良品' },
                                    { value: false, label: '仍不合格，退回处理' },
                                ]}
                            />
                        </Form.Item>
                        <Form.Item name="remark" label="复检备注">
                            <Input.TextArea rows={2} maxLength={255} />
                        </Form.Item>
                    </>
                ) : (
                    <>
                        <Form.Item
                            name="reason"
                            label={mode === 'rework' ? '返工原因' : '报废原因'}
                            rules={[{ required: true, message: '请填写原因' }]}
                        >
                            <Input.TextArea rows={2} maxLength={255} />
                        </Form.Item>
                        {mode === 'scrap' && (
                            <Typography.Text type="secondary">
                                报废数量：{defect.quantity}（整单不良数量）
                            </Typography.Text>
                        )}
                    </>
                )}
            </Form>
        </Modal>
    );
}
