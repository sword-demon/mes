import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
    App,
    Button,
    Drawer,
    Alert,
    Form,
    Input,
    Modal,
    Popconfirm,
    Select,
    Space,
    Switch,
    Table,
    Tag,
    Typography,
} from 'antd';
import { useState } from 'react';
import { listProducts } from '@/api/products';
import {
    approveProcessRoute,
    createProcessRoute,
    listProcessRoutes,
    submitProcessRoute,
    updateProcessRoute,
} from '@/api/production';
import type { ProcessRoutePayload } from '@/api/production';
import Can from '@/components/can';
import type { ProcessRoute } from '@/types/api';
import { ApiRequestError } from '@/utils/api-error';
import RouteOperations from './route-operations';

type Editing = { mode: 'create' } | { mode: 'edit'; route: ProcessRoute } | null;

const AUDIT_STATUSES: Record<ProcessRoute['audit_status'], { label: string; color: string }> = {
    draft: { label: '草稿', color: 'default' },
    pending: { label: '待审核', color: 'gold' },
    approved: { label: '已通过', color: 'green' },
};

export default function ProcessRoutes() {
    const { message } = App.useApp();
    const queryClient = useQueryClient();
    const [page, setPage] = useState(1);
    const [keyword, setKeyword] = useState('');
    const [auditStatus, setAuditStatus] = useState<ProcessRoute['audit_status']>();
    const [editing, setEditing] = useState<Editing>(null);
    const [operationsRoute, setOperationsRoute] = useState<ProcessRoute | null>(null);
    const [form] = Form.useForm<ProcessRoutePayload>();

    const routes = useQuery({
        queryKey: ['process', 'routes', page, keyword, auditStatus],
        queryFn: () => listProcessRoutes({ page, keyword: keyword || undefined, audit_status: auditStatus }),
    });
    const products = useQuery({
        queryKey: ['products', 'options'],
        queryFn: () => listProducts({ page: 1, per_page: 100 }),
        enabled: editing?.mode === 'create',
    });

    const closeEditor = () => setEditing(null);
    const showError = (error: unknown) => message.error(error instanceof ApiRequestError ? error.message : '操作失败');
    const refresh = () => queryClient.invalidateQueries({ queryKey: ['process', 'routes'] });

    const save = useMutation({
        mutationFn: (values: ProcessRoutePayload) => {
            if (!editing) {
                throw new Error('未选择工艺路线');
            }

            return editing.mode === 'create'
                ? createProcessRoute(values)
                : updateProcessRoute(editing.route.id, values);
        },
        onSuccess: () => {
            refresh();
            message.success('工艺路线已保存');
            closeEditor();
        },
        onError: showError,
    });
    const submit = useMutation({
        mutationFn: (id: number) => submitProcessRoute(id),
        onSuccess: () => {
            refresh();
            message.success('已提交审核');
        },
        onError: showError,
    });
    const approve = useMutation({
        mutationFn: (id: number) => approveProcessRoute(id),
        onSuccess: () => {
            refresh();
            message.success('工艺路线已审核通过');
        },
        onError: showError,
    });
    const toggle = useMutation({
        mutationFn: (route: ProcessRoute) => updateProcessRoute(route.id, { status: !route.status }),
        onSuccess: () => { void refresh(); message.success('启用状态已更新'); },
        onError: showError,
    });

    const formValues = editing?.mode === 'edit'
        ? {
              code: editing.route.code,
              product_id: editing.route.product_id,
              version: editing.route.version,
              remark: editing.route.remark,
              status: editing.route.status,
          }
        : { status: true };

    return (
        <>
            <Space className="mb-4 w-full justify-between" wrap>
                <Typography.Title level={4} className="mb-0!">工艺路线</Typography.Title>
                <Space wrap>
                    <Input.Search
                        allowClear
                        className="w-56"
                        placeholder="路线编码 / 版本"
                        onSearch={(value) => { setKeyword(value); setPage(1); }}
                    />
                    <Select
                        allowClear
                        className="w-44"
                        placeholder="审核状态"
                        value={auditStatus}
                        onChange={(value?: ProcessRoute['audit_status']) => { setAuditStatus(value); setPage(1); }}
                        options={Object.entries(AUDIT_STATUSES).map(([value, item]) => ({ value, label: item.label }))}
                    />
                    <Can code="button.process.routes.create">
                        <Button type="primary" onClick={() => setEditing({ mode: 'create' })}>新增路线</Button>
                    </Can>
                </Space>
            </Space>
            {routes.isError && <Alert type="error" showIcon title={routes.error.message} />}
            <Table<ProcessRoute>
                rowKey="id"
                loading={routes.isLoading}
                dataSource={routes.data?.items}
                pagination={{
                    current: page,
                    pageSize: routes.data?.meta.per_page,
                    total: routes.data?.meta.total,
                    onChange: setPage,
                }}
                columns={[
                    { title: '路线编码', dataIndex: 'code' },
                    { title: '产品', key: 'product', render: (_, route) => `${route.product?.code ?? '-'} ${route.product?.name ?? ''}` },
                    { title: '版本', dataIndex: 'version' },
                    {
                        title: '审核状态',
                        dataIndex: 'audit_status',
                        render: (value: ProcessRoute['audit_status']) => <Tag color={AUDIT_STATUSES[value]?.color}>{AUDIT_STATUSES[value]?.label ?? value}</Tag>,
                    },
                    { title: '启用', dataIndex: 'status', render: (value: boolean) => <Tag color={value ? 'green' : 'default'}>{value ? '启用' : '停用'}</Tag> },
                    {
                        title: '操作',
                        key: 'actions',
                        render: (_, route) => (
                            <Space>
                                <Button type="link" onClick={() => setOperationsRoute(route)}>工序配置</Button>
                                <Can code="button.process.routes.update">
                                    <Button type="link" disabled={toggle.isPending} onClick={() => toggle.mutate(route)}>
                                        {route.status ? '停用' : '启用'}
                                    </Button>
                                </Can>
                                {route.audit_status === 'draft' ? (
                                    <>
                                        <Can code="button.process.routes.update">
                                            <Button type="link" onClick={() => setEditing({ mode: 'edit', route })}>编辑</Button>
                                        </Can>
                                        <Can code="button.process.routes.submit">
                                            <Popconfirm title="确认提交审核？" onConfirm={() => submit.mutate(route.id)}>
                                                <Button type="link" disabled={!route.status || submit.isPending}>提交审核</Button>
                                            </Popconfirm>
                                        </Can>
                                    </>
                                ) : null}
                                {route.audit_status === 'pending' ? (
                                    <Can code="button.process.routes.audit">
                                        <Popconfirm title="确认审核通过？" onConfirm={() => approve.mutate(route.id)}>
                                            <Button type="link" disabled={!route.status || approve.isPending}>审核通过</Button>
                                        </Popconfirm>
                                    </Can>
                                ) : null}
                            </Space>
                        ),
                    },
                ]}
            />
            <Modal
                open={editing !== null}
                title={editing?.mode === 'create' ? '新增工艺路线' : '编辑工艺路线'}
                confirmLoading={save.isPending}
                onCancel={closeEditor}
                onOk={() => form.submit()}
            >
                <Form
                    key={editing?.mode === 'create' ? 'create' : editing?.route.id}
                    form={form}
                    layout="vertical"
                    initialValues={formValues}
                    onFinish={(values) => save.mutate(values)}
                >
                    {editing?.mode === 'create' ? (
                        <Form.Item name="product_id" label="产品" rules={[{ required: true }]}>
                            <Select
                                showSearch
                                optionFilterProp="label"
                                loading={products.isLoading}
                                options={(products.data?.items ?? []).map((product) => ({ value: product.id, label: `${product.code} ${product.name}` }))}
                            />
                        </Form.Item>
                    ) : <Form.Item label="产品">{`${editing?.route.product?.code ?? ''} ${editing?.route.product?.name ?? ''}`}</Form.Item>}
                    <Form.Item name="code" label="路线编码" rules={[{ required: true }]}><Input /></Form.Item>
                    <Form.Item name="version" label="版本" rules={[{ required: true }]}><Input placeholder="V1.0" /></Form.Item>
                    <Form.Item name="remark" label="备注"><Input.TextArea rows={2} /></Form.Item>
                    <Form.Item name="status" label="启用" valuePropName="checked"><Switch /></Form.Item>
                </Form>
            </Modal>
            <Drawer
                open={operationsRoute !== null}
                title={operationsRoute ? `工序配置 · ${operationsRoute.code} ${operationsRoute.version}` : '工序配置'}
                width={760}
                onClose={() => setOperationsRoute(null)}
                destroyOnHidden
            >
                {operationsRoute ? <RouteOperations route={operationsRoute} /> : null}
            </Drawer>
        </>
    );
}
