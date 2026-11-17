import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
    App,
    Button,
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
import { createRole, deleteRole, listPermissions, listRoles, updateRole } from '@/api/system';
import type { RolePayload } from '@/api/system';
import Can from '@/components/can';
import type { Permission, Role } from '@/types/api';
import { ApiRequestError } from '@/utils/api-error';

type Editing = { mode: 'create' } | { mode: 'edit'; role: Role } | null;

const PERMISSION_TYPE_LABELS: Record<Permission['type'], string> = {
    menu: '菜单权限',
    page: '页面权限',
    button: '按钮权限',
    api: '接口权限',
    data: '数据权限',
};

export default function Roles() {
    const { message } = App.useApp();
    const queryClient = useQueryClient();
    const [page, setPage] = useState(1);
    const [editing, setEditing] = useState<Editing>(null);
    const [form] = Form.useForm<RolePayload>();

    const roles = useQuery({
        queryKey: ['system', 'roles', page],
        queryFn: () => listRoles({ page }),
    });

    // The permission picker is the only consumer of this list, so it is only
    // fetched while the form is open.
    const permissions = useQuery({
        queryKey: ['system', 'permissions'],
        queryFn: listPermissions,
        enabled: editing !== null,
    });

    const close = () => setEditing(null);
    const showError = (error: unknown) =>
        message.error(error instanceof ApiRequestError ? error.message : '操作失败');

    const save = useMutation({
        mutationFn: async (values: RolePayload) => {
            if (!editing) {
                throw new Error('未选择角色');
            }

            return editing.mode === 'create'
                ? createRole(values)
                : updateRole(editing.role.id, values);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['system', 'roles'] });
            message.success('已保存');
            close();
        },
        onError: showError,
    });

    const remove = useMutation({
        mutationFn: (id: number) => deleteRole(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['system', 'roles'] });
            message.success('已删除');
        },
        onError: showError,
    });

    const grouped = (permissions.data ?? []).reduce<
        Partial<Record<Permission['type'], { label: string; value: number }[]>>
    >((groups, permission) => {
        const items = groups[permission.type] ?? [];
        items.push({ label: `${permission.name}（${permission.code}）`, value: permission.id });
        groups[permission.type] = items;

        return groups;
    }, {});

    const permissionChoices = (Object.keys(PERMISSION_TYPE_LABELS) as Permission['type'][])
        .filter((type) => (grouped[type] ?? []).length > 0)
        .map((type) => ({
            label: PERMISSION_TYPE_LABELS[type],
            options: grouped[type] ?? [],
        }));

    const formValues =
        editing?.mode === 'edit'
            ? {
                  code: editing.role.code,
                  name: editing.role.name,
                  description: editing.role.description,
                  status: editing.role.status,
                  permission_ids: editing.role.permissions?.map((permission) => permission.id) ?? [],
              }
            : { status: true, permission_ids: [] as number[] };

    return (
        <>
            <Space className="mb-4 w-full justify-between">
                <Typography.Title level={4} className="mb-0!">
                    角色管理
                </Typography.Title>
                <Can code="button.system.roles.create">
                    <Button type="primary" onClick={() => setEditing({ mode: 'create' })}>
                        新增角色
                    </Button>
                </Can>
            </Space>
            <Table<Role>
                rowKey="id"
                loading={roles.isLoading}
                dataSource={roles.data?.items}
                pagination={{
                    current: page,
                    pageSize: roles.data?.meta.per_page,
                    total: roles.data?.meta.total,
                    onChange: setPage,
                }}
                columns={[
                    { title: '名称', dataIndex: 'name' },
                    { title: '编码', dataIndex: 'code' },
                    { title: '描述', dataIndex: 'description', render: (value: string | null) => value ?? '-' },
                    {
                        title: '权限数',
                        dataIndex: 'permissions',
                        render: (value: Role['permissions']) => value?.length ?? 0,
                    },
                    {
                        title: '状态',
                        dataIndex: 'status',
                        render: (status: boolean) => (
                            <Tag color={status ? 'green' : 'default'}>
                                {status ? '启用' : '停用'}
                            </Tag>
                        ),
                    },
                    {
                        title: '操作',
                        key: 'actions',
                        render: (_, role) => (
                            <Space>
                                <Can code="button.system.roles.update">
                                    <Button type="link" onClick={() => setEditing({ mode: 'edit', role })}>
                                        编辑
                                    </Button>
                                </Can>
                                <Can code="button.system.roles.delete">
                                    <Popconfirm
                                        title="删除该角色？"
                                        description="已分配给用户的角色不能删除。"
                                        onConfirm={() => remove.mutate(role.id)}
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
                title={editing?.mode === 'create' ? '新增角色' : '编辑角色'}
                confirmLoading={save.isPending}
                onCancel={close}
                onOk={() => form.submit()}
            >
                <Form
                    key={editing?.mode === 'create' ? 'create' : editing?.role.id}
                    form={form}
                    layout="vertical"
                    initialValues={formValues}
                    onFinish={(values) => save.mutate(values)}
                >
                    <Form.Item
                        name="code"
                        label="编码"
                        rules={[
                            { required: true },
                            {
                                pattern: /^[a-z][a-z0-9_]*$/,
                                message: '编码只能是小写字母、数字和下划线，且以字母开头',
                            },
                        ]}
                    >
                        <Input placeholder="quality_inspector" />
                    </Form.Item>
                    <Form.Item name="name" label="名称" rules={[{ required: true }]}>
                        <Input />
                    </Form.Item>
                    <Form.Item name="description" label="描述">
                        <Input.TextArea rows={2} />
                    </Form.Item>
                    <Form.Item
                        name="status"
                        label="启用"
                        valuePropName="checked"
                        getValueFromEvent={(checked: boolean) => checked}
                    >
                        <Switch />
                    </Form.Item>
                    <Form.Item name="permission_ids" label="权限">
                        <Select
                            mode="multiple"
                            allowClear
                            showSearch
                            optionFilterProp="label"
                            loading={permissions.isLoading}
                            placeholder="不选则该角色没有任何权限"
                            options={permissionChoices}
                        />
                    </Form.Item>
                </Form>
            </Modal>
        </>
    );
}
