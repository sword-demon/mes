import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { App, Button, Form, Input, InputNumber, Modal, Popconfirm, Select, Space, Table, Tag } from 'antd';
import { useState } from 'react';
import { listProcesses } from '@/api/basic-data';
import {
    createRouteOperation,
    deleteRouteOperation,
    listRouteOperations,
    updateRouteOperation,
} from '@/api/production';
import type { RouteOperationPayload } from '@/api/production';
import Can from '@/components/can';
import type { Process, ProcessRoute, RouteOperation } from '@/types/api';
import { ApiRequestError } from '@/utils/api-error';

type Editing = { mode: 'create' } | { mode: 'edit'; operation: RouteOperation } | null;

export default function RouteOperations({ route }: { route: ProcessRoute }) {
    const { message } = App.useApp();
    const queryClient = useQueryClient();
    const [editing, setEditing] = useState<Editing>(null);
    const [form] = Form.useForm<RouteOperationPayload>();
    const editable = route.audit_status === 'draft';

    const operations = useQuery({
        queryKey: ['process', 'routes', route.id, 'operations'],
        queryFn: () => listRouteOperations(route.id),
    });
    const processes = useQuery({
        queryKey: ['process', 'operations', 'options'],
        queryFn: () => listProcesses({ status: true, per_page: 100 }),
        enabled: editing !== null,
    });

    const showError = (error: unknown) => message.error(error instanceof ApiRequestError ? error.message : '操作失败');
    const refresh = () => queryClient.invalidateQueries({ queryKey: ['process', 'routes', route.id, 'operations'] });
    const close = () => setEditing(null);

    const save = useMutation({
        mutationFn: (values: RouteOperationPayload) => {
            if (!editing) throw new Error('未选择工序');
            return editing.mode === 'create'
                ? createRouteOperation(route.id, values)
                : updateRouteOperation(route.id, editing.operation.id, values);
        },
        onSuccess: () => {
            refresh();
            message.success('工序已保存');
            close();
        },
        onError: showError,
    });
    const remove = useMutation({
        mutationFn: (id: number) => deleteRouteOperation(route.id, id),
        onSuccess: () => {
            refresh();
            message.success('工序已删除');
        },
        onError: showError,
    });

    const formValues = editing?.mode === 'edit'
        ? {
              process_id: editing.operation.process_id,
              sequence: editing.operation.sequence,
              standard_hours: editing.operation.standard_hours,
              parameters: editing.operation.parameters,
          }
        : { sequence: Math.max(0, ...(operations.data ?? []).map((operation) => operation.sequence)) + 1, standard_hours: 0 };

    return (
        <>
            <Space className="mb-3 w-full justify-end">
                <Can code="button.process.routes.create">
                    <Button disabled={!editable} type="primary" onClick={() => setEditing({ mode: 'create' })}>
                        新增工序
                    </Button>
                </Can>
            </Space>
            {!editable ? <Tag className="mb-3">已提交审核，工序内容已锁定</Tag> : null}
            <Table<RouteOperation>
                rowKey="id"
                size="small"
                loading={operations.isLoading}
                dataSource={operations.data}
                pagination={false}
                columns={[
                    { title: '顺序', dataIndex: 'sequence', width: 80 },
                    { title: '工序编码', key: 'code', render: (_, operation) => operation.process?.code ?? '-' },
                    { title: '工序名称', key: 'name', render: (_, operation) => operation.process?.name ?? '-' },
                    { title: '类型', key: 'type', render: (_, operation) => operation.process?.type ?? '-' },
                    { title: '标准工时', dataIndex: 'standard_hours', width: 110 },
                    {
                        title: '需检验',
                        key: 'inspection',
                        render: (_, operation) => operation.process?.requires_inspection ? '是' : '否',
                    },
                    {
                        title: '操作',
                        key: 'actions',
                        render: (_, operation) => editable ? (
                            <Space>
                                <Can code="button.process.routes.update">
                                    <Button type="link" onClick={() => setEditing({ mode: 'edit', operation })}>编辑</Button>
                                </Can>
                                <Can code="button.process.routes.update">
                                    <Popconfirm title="删除该工序？" onConfirm={() => remove.mutate(operation.id)}>
                                        <Button type="link" danger>删除</Button>
                                    </Popconfirm>
                                </Can>
                            </Space>
                        ) : null,
                    },
                ]}
            />
            <Modal
                open={editing !== null}
                title={editing?.mode === 'create' ? '新增工序' : '编辑工序'}
                confirmLoading={save.isPending}
                onCancel={close}
                onOk={() => form.submit()}
            >
                <Form
                    key={editing?.mode === 'create' ? 'create' : editing?.operation.id}
                    form={form}
                    layout="vertical"
                    initialValues={formValues}
                    onFinish={(values) => save.mutate(values)}
                >
                    <Form.Item name="process_id" label="工序" rules={[{ required: true }]}>
                        <Select
                            showSearch
                            optionFilterProp="label"
                            loading={processes.isLoading}
                            options={(processes.data?.items ?? []).map((process: Process) => ({
                                value: process.id,
                                label: `${process.code} ${process.name}`,
                            }))}
                        />
                    </Form.Item>
                    <Form.Item name="sequence" label="顺序" rules={[{ required: true, type: 'number', min: 1, max: 9999 }]}>
                        <InputNumber min={1} max={9999} className="w-full" />
                    </Form.Item>
                    <Form.Item name="standard_hours" label="标准工时" rules={[{ type: 'number', min: 0, max: 9999.99 }]}>
                        <InputNumber min={0} max={9999.99} precision={2} className="w-full" />
                    </Form.Item>
                    <Form.Item name="parameters" label="工艺参数">
                        <Input maxLength={255} />
                    </Form.Item>
                </Form>
            </Modal>
        </>
    );
}
