import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
    App,
    Button,
    Form,
    Input,
    InputNumber,
    Modal,
    Popconfirm,
    Space,
    Table,
    TreeSelect,
    Typography,
} from 'antd';
import { useEffect, useMemo, useState } from 'react';
import type { Key } from 'react';
import {
    createDepartment,
    deleteDepartment,
    listDepartments,
    updateDepartment,
} from '@/api/system';
import type { DepartmentPayload } from '@/api/system';
import Can from '@/components/can';
import type { DepartmentNode } from '@/types/api';
import { ApiRequestError } from '@/utils/api-error';
import { flattenTree } from '@/utils/tree';

type Editing = { mode: 'create' } | { mode: 'edit'; department: DepartmentNode } | null;

type TreeOption = { title: string; value: number; children: TreeOption[] };

function toTreeData(nodes: DepartmentNode[]): TreeOption[] {
    return nodes.map((node) => ({
        title: `${node.name}（${node.code}）`,
        value: node.id,
        children: toTreeData(node.children),
    }));
}

export default function Departments() {
    const { message } = App.useApp();
    const queryClient = useQueryClient();
    const [editing, setEditing] = useState<Editing>(null);
    const [expandedKeys, setExpandedKeys] = useState<Key[]>([]);
    const [form] = Form.useForm<DepartmentPayload>();

    const departments = useQuery({
        queryKey: ['system', 'departments'],
        queryFn: listDepartments,
    });

    // The tree arrives with the query, after the table mounted, so the
    // expanded keys are driven by the data instead of the mount-time default.
    useEffect(() => {
        setExpandedKeys(flattenTree(departments.data ?? []).map((node) => node.id));
    }, [departments.data]);

    const close = () => setEditing(null);
    const showError = (error: unknown) =>
        message.error(error instanceof ApiRequestError ? error.message : '操作失败');

    const save = useMutation({
        mutationFn: async (values: DepartmentPayload) => {
            if (!editing) {
                throw new Error('未选择部门');
            }

            return editing.mode === 'create'
                ? createDepartment(values)
                : updateDepartment(editing.department.id, values);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['system', 'departments'] });
            message.success('已保存');
            close();
        },
        onError: showError,
    });

    const remove = useMutation({
        mutationFn: (id: number) => deleteDepartment(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['system', 'departments'] });
            message.success('已删除');
        },
        onError: showError,
    });

    // The edited branch is removed so a department cannot become its own
    // descendant.
    const parentTree = useMemo(() => {
        const roots = departments.data ?? [];

        if (editing?.mode !== 'edit') {
            return toTreeData(roots);
        }

        return toTreeData(roots.filter((node) => node.id !== editing.department.id));
    }, [departments.data, editing]);

    const formValues =
        editing?.mode === 'edit'
            ? {
                  parent_id: editing.department.parent_id,
                  code: editing.department.code,
                  name: editing.department.name,
                  sort: editing.department.sort,
              }
            : { sort: 0 };

    return (
        <>
            <Space className="mb-4 w-full justify-between">
                <Typography.Title level={4} className="mb-0!">
                    部门管理
                </Typography.Title>
                <Can code="button.system.departments.create">
                    <Button type="primary" onClick={() => setEditing({ mode: 'create' })}>
                        新增部门
                    </Button>
                </Can>
            </Space>
            <Table<DepartmentNode>
                rowKey="id"
                loading={departments.isLoading}
                dataSource={departments.data}
                pagination={false}
                expandable={{
                    expandedRowKeys: expandedKeys,
                    onExpandedRowsChange: (keys) => setExpandedKeys([...keys]),
                }}
                columns={[
                    { title: '名称', dataIndex: 'name' },
                    { title: '编码', dataIndex: 'code' },
                    { title: '排序', dataIndex: 'sort' },
                    {
                        title: '操作',
                        key: 'actions',
                        render: (_, department) => (
                            <Space>
                                <Can code="button.system.departments.update">
                                    <Button
                                        type="link"
                                        onClick={() => setEditing({ mode: 'edit', department })}
                                    >
                                        编辑
                                    </Button>
                                </Can>
                                <Can code="button.system.departments.delete">
                                    <Popconfirm
                                        title="删除该部门？"
                                        description="存在下级部门或在职人员时不能删除。"
                                        onConfirm={() => remove.mutate(department.id)}
                                    >
                                        <Button type="link" danger>
                                            删除
                                        </Button>
                                    </Popconfirm>
                                </Can>
                            </Space>
                        ),
                    },
                ]}
            />
            <Modal
                open={editing !== null}
                title={editing?.mode === 'create' ? '新增部门' : '编辑部门'}
                confirmLoading={save.isPending}
                onCancel={close}
                onOk={() => form.submit()}
            >
                <Form
                    key={editing?.mode === 'create' ? 'create' : editing?.department.id}
                    form={form}
                    layout="vertical"
                    initialValues={formValues}
                    onFinish={(values) => save.mutate(values)}
                >
                    <Form.Item name="name" label="名称" rules={[{ required: true }]}>
                        <Input />
                    </Form.Item>
                    <Form.Item name="code" label="编码" rules={[{ required: true }]}>
                        <Input placeholder="production" />
                    </Form.Item>
                    <Form.Item name="parent_id" label="上级部门">
                        <TreeSelect
                            allowClear
                            treeDefaultExpandAll
                            placeholder="顶级部门"
                            treeData={parentTree}
                        />
                    </Form.Item>
                    <Form.Item name="sort" label="排序" rules={[{ type: 'number', min: 0 }]}>
                        <InputNumber min={0} className="w-full" />
                    </Form.Item>
                </Form>
            </Modal>
        </>
    );
}
