import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
    App,
    Button,
    Descriptions,
    Drawer,
    Form,
    Input,
    Modal,
    Segmented,
    Space,
    Switch,
    Tag,
    Typography,
} from 'antd';
import dayjs from 'dayjs';
import { useState } from 'react';
import Can from '@/components/can';
import {
    createProductVersion,
    listProductVersions,
    updateProductVersion,
} from '@/api/products';
import type { Product, ProductVersion, ProductVersionPayload } from '@/api/products';
import { ApiRequestError } from '@/utils/api-error';

type Editing = { mode: 'create' } | { mode: 'edit'; record: ProductVersion } | null;

export default function ProductVersionsDrawer({
    product,
    onClose,
}: {
    product: Product | null;
    onClose: () => void;
}) {
    const { message } = App.useApp();
    const queryClient = useQueryClient();
    const [activeId, setActiveId] = useState<number | null>(null);
    const [editing, setEditing] = useState<Editing>(null);
    const [form] = Form.useForm();

    const queryKey = ['basic.products', product?.id, 'versions'];
    const query = useQuery({
        queryKey,
        queryFn: () => listProductVersions(product!.id),
        enabled: product !== null,
    });

    const versions = query.data?.items ?? [];
    const active = versions.find((version) => version.id === activeId) ?? versions[0] ?? null;

    const save = useMutation({
        mutationFn: (values: ProductVersionPayload) => {
            if (!product || !editing) {
                throw new Error('未选择版本');
            }

            return editing.mode === 'create'
                ? createProductVersion(product.id, values)
                : updateProductVersion(product.id, editing.record.id, values);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey });
            message.success('已保存');
            setEditing(null);
        },
        onError: (error) =>
            message.error(error instanceof ApiRequestError ? error.message : '操作失败'),
    });

    const closeEditing = () => setEditing(null);

    return (
        <Drawer
            open={product !== null}
            title={product ? `${product.code} ${product.name}` : '产品版本'}
            size={480}
            onClose={() => {
                setActiveId(null);
                setEditing(null);
                onClose();
            }}
            extra={
                <Can code="button.basic.products.create">
                    <Button type="primary" onClick={() => setEditing({ mode: 'create' })}>
                        新增版本
                    </Button>
                </Can>
            }
        >
            {versions.length === 0 ? (
                <Typography.Text type="secondary">
                    {query.isLoading ? '加载中…' : '该产品还没有版本'}
                </Typography.Text>
            ) : (
                <Space orientation="vertical" className="w-full" size="large">
                    <Segmented
                        block
                        className="w-full"
                        value={active?.id}
                        options={versions.map((version) => ({
                            value: version.id,
                            label: `${version.version}${version.status ? '' : '（停用）'}`,
                        }))}
                        onChange={(value) => setActiveId(value as number)}
                    />
                    {active && (
                        <>
                            <Descriptions column={1} bordered size="small">
                                <Descriptions.Item label="版本号">{active.version}</Descriptions.Item>
                                <Descriptions.Item label="状态">
                                    <Tag color={active.status ? 'green' : 'default'}>
                                        {active.status ? '启用' : '停用'}
                                    </Tag>
                                </Descriptions.Item>
                                <Descriptions.Item label="备注">{active.remark ?? '-'}</Descriptions.Item>
                                <Descriptions.Item label="创建时间">
                                    {dayjs(active.created_at).format('YYYY-MM-DD HH:mm')}
                                </Descriptions.Item>
                            </Descriptions>
                            <Can code="button.basic.products.update">
                                <Button onClick={() => setEditing({ mode: 'edit', record: active })}>
                                    编辑当前版本
                                </Button>
                            </Can>
                        </>
                    )}
                </Space>
            )}

            <Modal
                open={editing !== null}
                title={editing?.mode === 'create' ? '新增版本' : '编辑版本'}
                confirmLoading={save.isPending}
                onCancel={closeEditing}
                onOk={() => form.submit()}
                destroyOnHidden
            >
                <Form
                    form={form}
                    layout="vertical"
                    initialValues={editing?.mode === 'edit' ? editing.record : { status: true }}
                    onFinish={(values) => save.mutate(values)}
                >
                    <Form.Item
                        name="version"
                        label="版本号"
                        rules={[{ required: true, message: '请填写版本号' }]}
                    >
                        <Input placeholder="如 V1.0" />
                    </Form.Item>
                    <Form.Item name="remark" label="备注">
                        <Input.TextArea rows={3} />
                    </Form.Item>
                    <Form.Item name="status" label="启用" valuePropName="checked">
                        <Switch />
                    </Form.Item>
                </Form>
            </Modal>
        </Drawer>
    );
}
