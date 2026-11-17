import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
    App,
    Button,
    Form,
    Input,
    InputNumber,
    Modal,
    Popconfirm,
    Select,
    Space,
    Table,
    Tag,
    TreeSelect,
    Typography,
} from 'antd';
import { useEffect, useMemo, useState } from 'react';
import type { Key } from 'react';
import { createMenu, deleteMenu, listMenus, listPermissions, updateMenu } from '@/api/system';
import type { MenuPayload } from '@/api/system';
import Can from '@/components/can';
import type { MenuNode, Permission } from '@/types/api';
import { ApiRequestError } from '@/utils/api-error';
import { flattenTree } from '@/utils/tree';

type Editing = { mode: 'create' } | { mode: 'edit'; menu: MenuNode } | null;

type TreeOption = { title: string; value: number; children: TreeOption[] };

function toTreeData(nodes: MenuNode[]): TreeOption[] {
    return nodes.map((node) => ({
        title: node.name,
        value: node.id,
        children: toTreeData(node.children),
    }));
}

export default function Menus() {
    const { message } = App.useApp();
    const queryClient = useQueryClient();
    const [editing, setEditing] = useState<Editing>(null);
    const [expandedKeys, setExpandedKeys] = useState<Key[]>([]);
    const [form] = Form.useForm<MenuPayload>();

    const menus = useQuery({
        queryKey: ['system', 'menus'],
        queryFn: listMenus,
    });

    const permissions = useQuery({
        queryKey: ['system', 'permissions'],
        queryFn: listPermissions,
        enabled: editing !== null,
    });

    // The tree arrives with the query, after the table mounted, so the
    // expanded keys are driven by the data instead of the mount-time default.
    useEffect(() => {
        setExpandedKeys(flattenTree(menus.data ?? []).map((node) => node.id));
    }, [menus.data]);

    const close = () => setEditing(null);
    const showError = (error: unknown) =>
        message.error(error instanceof ApiRequestError ? error.message : '操作失败');

    const save = useMutation({
        mutationFn: async (values: MenuPayload) => {
            if (!editing) {
                throw new Error('未选择菜单');
            }

            return editing.mode === 'create'
                ? createMenu(values)
                : updateMenu(editing.menu.id, values);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['system', 'menus'] });
            message.success('已保存');
            close();
        },
        onError: showError,
    });

    const remove = useMutation({
        mutationFn: (id: number) => deleteMenu(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['system', 'menus'] });
            message.success('已删除');
        },
        onError: showError,
    });

    // A menu cannot be its own parent, and picking a descendant would build a
    // cycle, so the edited branch is removed from the parent options.
    const parentTree = useMemo(() => {
        const roots = menus.data ?? [];

        if (editing?.mode !== 'edit') {
            return toTreeData(roots);
        }

        const withoutBranch = roots.filter((node) => node.id !== editing.menu.id);

        return toTreeData(withoutBranch);
    }, [menus.data, editing]);

    const permissionChoices = (permissions.data ?? [])
        .filter((permission) => permission.type === 'menu')
        .map((permission) => ({
            label: `${permission.name}（${permission.code}）`,
            value: permission.id,
        }));

    const formValues =
        editing?.mode === 'edit'
            ? {
                  parent_id: editing.menu.parent_id,
                  permission_id: editing.menu.permission_id,
                  name: editing.menu.name,
                  path: editing.menu.path,
                  icon: editing.menu.icon,
                  sort: editing.menu.sort,
              }
            : { sort: 0 };

    return (
        <>
            <Space className="mb-4 w-full justify-between">
                <Typography.Title level={4} className="mb-0!">
                    菜单管理
                </Typography.Title>
                <Can code="button.system.menus.create">
                    <Button type="primary" onClick={() => setEditing({ mode: 'create' })}>
                        新增菜单
                    </Button>
                </Can>
            </Space>
            <Table<MenuNode>
                rowKey="id"
                loading={menus.isLoading}
                dataSource={menus.data}
                pagination={false}
                expandable={{
                    expandedRowKeys: expandedKeys,
                    onExpandedRowsChange: (keys) => setExpandedKeys([...keys]),
                }}
                columns={[
                    { title: '名称', dataIndex: 'name' },
                    {
                        title: '路径',
                        dataIndex: 'path',
                        render: (value: string | null) => value ?? '-',
                    },
                    {
                        title: '图标',
                        dataIndex: 'icon',
                        render: (value: string | null) =>
                            value ? <Tag>{value}</Tag> : '-',
                    },
                    { title: '排序', dataIndex: 'sort' },
                    {
                        title: '操作',
                        key: 'actions',
                        render: (_, menu) => (
                            <Space>
                                <Can code="button.system.menus.update">
                                    <Button type="link" onClick={() => setEditing({ mode: 'edit', menu })}>
                                        编辑
                                    </Button>
                                </Can>
                                <Can code="button.system.menus.delete">
                                    <Popconfirm
                                        title="删除该菜单？"
                                        description="存在子菜单时不能删除。"
                                        onConfirm={() => remove.mutate(menu.id)}
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
                title={editing?.mode === 'create' ? '新增菜单' : '编辑菜单'}
                confirmLoading={save.isPending}
                onCancel={close}
                onOk={() => form.submit()}
            >
                <Form
                    key={editing?.mode === 'create' ? 'create' : editing?.menu.id}
                    form={form}
                    layout="vertical"
                    initialValues={formValues}
                    onFinish={(values) => save.mutate(values)}
                >
                    <Form.Item name="name" label="名称" rules={[{ required: true }]}>
                        <Input />
                    </Form.Item>
                    <Form.Item name="parent_id" label="上级菜单">
                        <TreeSelect
                            allowClear
                            treeDefaultExpandAll
                            placeholder="顶级菜单"
                            treeData={parentTree}
                        />
                    </Form.Item>
                    <Form.Item name="path" label="路由路径">
                        <Input placeholder="/system/roles" />
                    </Form.Item>
                    <Form.Item name="icon" label="图标">
                        <Input placeholder="SettingOutlined" />
                    </Form.Item>
                    <Form.Item name="permission_id" label="绑定菜单权限">
                        <Select
                            allowClear
                            showSearch
                            optionFilterProp="label"
                            loading={permissions.isLoading}
                            placeholder="不绑定则只有超级管理员可见"
                            options={permissionChoices}
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
