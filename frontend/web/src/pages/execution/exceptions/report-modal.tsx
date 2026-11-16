import { useQuery } from '@tanstack/react-query';
import { App, Button, Form, Input, Modal, Select, Space, Tag, Upload } from 'antd';
import { useRef, useState } from 'react';
import { getProductionExceptionOptions, PRODUCTION_EXCEPTION_TYPES } from '@/api/production';
import type { ProductionExceptionPayload } from '@/api/production';
import { useDebouncedValue } from '@/hooks/use-debounced-value';

export default function ReportExceptionModal({
    saving,
    onSave,
    onClose,
}: {
    saving: boolean;
    onSave: (payload: ProductionExceptionPayload, files: File[]) => void;
    onClose: () => void;
}) {
    const { message } = App.useApp();
    const [form] = Form.useForm<ProductionExceptionPayload>();
    const [files, setFiles] = useState<File[]>([]);
    // beforeUpload runs once per selected file, so the ref enforces the cap within a batch.
    const filesRef = useRef<File[]>([]);
    const [orderKeyword, setOrderKeyword] = useState('');
    const debouncedOrderKeyword = useDebouncedValue(orderKeyword);
    const orderId = Form.useWatch<number | undefined>('production_order_id', form);
    const orders = useQuery({
        queryKey: ['production', 'exceptions', 'options', 'orders', debouncedOrderKeyword],
        queryFn: () => getProductionExceptionOptions(undefined, debouncedOrderKeyword, 'orders'),
        staleTime: 30_000,
    });
    const globalRecords = useQuery({
        queryKey: ['production', 'exceptions', 'options', 'process-records', null],
        queryFn: () => getProductionExceptionOptions(undefined, undefined, 'process_records'),
        enabled: orderId === undefined,
        staleTime: 30_000,
    });
    const orderOptions = useQuery({
        queryKey: ['production', 'exceptions', 'options', 'process-records', orderId ?? 'none'],
        queryFn: () => getProductionExceptionOptions(orderId, undefined, 'process_records'),
        enabled: orderId !== undefined,
        staleTime: 30_000,
    });

    const beforeUpload = (file: File) => {
        if (filesRef.current.length >= 5) {
            message.error('最多上传 5 张图片');
            return Upload.LIST_IGNORE;
        }

        const next = [...filesRef.current, file];
        filesRef.current = next;
        setFiles(next);

        return false;
    };

    return (
        <Modal
            open
            title="上报生产异常"
            okText="提交上报"
            confirmLoading={saving}
            onCancel={onClose}
            onOk={() => form.submit()}
        >
            <Form
                form={form}
                layout="vertical"
                onValuesChange={(changed) => {
                    if ('production_order_id' in changed) {
                        form.setFieldValue('process_record_id', undefined);
                    }
                }}
                onFinish={(values) => onSave({
                    ...values,
                    production_order_id: values.production_order_id ?? null,
                    process_record_id: values.process_record_id ?? null,
                }, files)}
            >
                <Form.Item
                    name="type"
                    label="异常类型"
                    rules={[{ required: true, message: '请选择异常类型' }]}
                >
                    <Select
                        options={PRODUCTION_EXCEPTION_TYPES.map(({ value, label }) => ({ value, label }))}
                    />
                </Form.Item>
                <Form.Item
                    name="production_order_id"
                    label="生产订单"
                    extra="生产订单与工序执行记录至少填写一项"
                    dependencies={['process_record_id']}
                    rules={[({ getFieldValue }) => ({
                        validator(_, value) {
                            return value || getFieldValue('process_record_id')
                                ? Promise.resolve()
                                : Promise.reject(new Error('生产订单与工序执行记录至少填写一项'));
                        },
                    })]}
                >
                    <Select
                        allowClear
                        showSearch
                        filterOption={false}
                        onSearch={setOrderKeyword}
                        placeholder="请选择生产订单"
                        loading={orders.isLoading}
                        options={(orders.data?.orders ?? []).map((order) => ({
                            value: order.id,
                            label: `${order.code} ${order.product.name}`,
                        }))}
                    />
                </Form.Item>
                <Form.Item
                    name="process_record_id"
                    label="工序执行记录"
                    extra={orderId === undefined ? '可直接关联具体工序，订单将自动带入' : undefined}
                >
                    <Select
                        allowClear
                        showSearch
                        optionFilterProp="label"
                        placeholder="可不选，关联到具体工序"
                        loading={orderId === undefined ? globalRecords.isLoading : orderOptions.isLoading}
                        notFoundContent={orderId ? '该订单暂无工序执行记录' : '暂无工序执行记录'}
                        options={((orderId === undefined
                            ? globalRecords.data?.process_records
                            : orderOptions.data?.process_records) ?? []).map((record) => ({
                            value: record.id,
                            label: `${record.production_order ? `${record.production_order.code} · ` : ''}${record.route_operation.sequence}. ${record.route_operation.process.name}${record.sn ? `（${record.sn}）` : ''}`,
                        }))}
                    />
                </Form.Item>
                <Form.Item
                    name="description"
                    label="异常描述"
                    rules={[{ required: true, message: '请填写异常描述' }]}
                >
                    <Input.TextArea rows={4} maxLength={2000} showCount />
                </Form.Item>
                <Form.Item label="现场图片">
                    <Space orientation="vertical" className="w-full">
                        <Upload
                            multiple
                            accept="image/*"
                            showUploadList={false}
                            beforeUpload={beforeUpload}
                        >
                            <Button>选择图片</Button>
                        </Upload>
                        {files.length > 0 && (
                            <Space wrap>
                                {files.map((file, index) => (
                                    <Tag
                                        key={`${file.name}-${index}`}
                                        closable
                                        onClose={() => {
                                            const next = filesRef.current.filter((_, i) => i !== index);
                                            filesRef.current = next;
                                            setFiles(next);
                                        }}
                                    >
                                        {file.name}
                                    </Tag>
                                ))}
                            </Space>
                        )}
                    </Space>
                </Form.Item>
            </Form>
        </Modal>
    );
}
