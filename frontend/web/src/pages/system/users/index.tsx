import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
    App,
    Button,
    Form,
    Input,
    Modal,
    Select,
    Space,
    Switch,
    Table,
    Tag,
    Typography,
} from 'antd';
import { useState } from 'react';
import { listEmployees } from '@/api/basic-data';
import {
    assignUserRoles,
    createUser,
    listRoles,
    listUsers,
    resetUserPassword,
    updateUser,
} from '@/api/system';
import type { UserPayload } from '@/api/system';
import Can from '@/components/can';
import { useAuthStore } from '@/stores/auth';
import type { User } from '@/types/api';
import { ApiRequestError } from '@/utils/api-error';
import { can } from '@/utils/permission';

type Editing = { mode: 'create' } | { mode: 'edit'; user: User } | null;

export default function Users() {
    const { message } = App.useApp();
    const queryClient = useQueryClient();
    const currentUser = useAuthStore((state) => state.user);
    const [keyword, setKeyword] = useState('');
    const [page, setPage] = useState(1);
    const [editing, setEditing] = useState<Editing>(null);
    const [resetting, setResetting] = useState<User | null>(null);
    const [form] = Form.useForm<UserPayload>();
    const [passwordForm] = Form.useForm<{ password: string }>();

    const users = useQuery({
        queryKey: ['system', 'users', page, keyword],
        queryFn: () => listUsers({ page, keyword }),
    });

    const roles = useQuery({
        queryKey: ['system', 'roles'],
        queryFn: () => listRoles(),
        // Only the role field consumes this list, so skip the request when the
        // field is hidden rather than firing one the API rejects with 403.
        enabled: editing !== null && can(currentUser, 'button.system.users.assign-roles'),
    });
    const employees = useQuery({
        queryKey: ['basic.employees', 'options'],
        queryFn: () => listEmployees({ status: true, per_page: 100 }),
        enabled: editing !== null,
    });

    const close = () => setEditing(null);
    const showError = (error: unknown) =>
        message.error(error instanceof ApiRequestError ? error.message : '操作失败');

    const save = useMutation({
        mutationFn: async (values: UserPayload) => {
            if (!editing) {
                throw new Error('未选择用户');
            }

            if (editing.mode === 'create') {
                return createUser(values);
            }

            const { password, role_ids, ...rest } = values;
            const saved = await updateUser(editing.user.id, rest);

            // The role field is hidden without the permission, so skip the
            // follow-up call rather than firing a request the API rejects.
            return role_ids && can(currentUser, 'button.system.users.assign-roles')
                ? assignUserRoles(editing.user.id, role_ids)
                : saved;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['system', 'users'] });
            message.success('已保存');
            close();
        },
        onError: showError,
    });

    const reset = useMutation({
        mutationFn: ({ id, password }: { id: number; password: string }) =>
            resetUserPassword(id, password),
        onSuccess: () => {
            message.success('密码已重置');
            setResetting(null);
        },
        onError: showError,
    });

    function openPasswordReset(user: User) {
        setResetting(user);
        passwordForm.resetFields();
    }

    const formValues =
        editing?.mode === 'edit'
            ? {
                  name: editing.user.name,
                  email: editing.user.email,
                  username: editing.user.username,
                  phone: editing.user.phone,
                  employee_id: editing.user.employee_id,
                  status: editing.user.status,
                  role_ids: editing.user.roles?.map((role) => role.id) ?? [],
              }
            : { status: true, role_ids: [] as number[] };

    const roleOptions = (roles.data?.items ?? []).map((role) => ({
        label: role.name,
        value: role.id,
    }));

    return (
        <>
            <Space className="mb-4 w-full justify-between">
                <Typography.Title level={4} className="mb-0!">
                    用户管理
                </Typography.Title>
                <Space>
                    <Input.Search
                        allowClear
                        placeholder="姓名 / 邮箱 / 手机号"
                        onSearch={(value) => {
                            setKeyword(value);
                            setPage(1);
                        }}
                        className="w-64"
                    />
                    <Can code="button.system.users.create">
                        <Button type="primary" onClick={() => setEditing({ mode: 'create' })}>
                            新增用户
                        </Button>
                    </Can>
                </Space>
            </Space>
            <Table<User>
                rowKey="id"
                loading={users.isLoading}
                dataSource={users.data?.items}
                pagination={{
                    current: page,
                    pageSize: users.data?.meta.per_page,
                    total: users.data?.meta.total,
                    onChange: setPage,
                }}
                columns={[
                    { title: '姓名', dataIndex: 'name' },
                    { title: '邮箱', dataIndex: 'email' },
                    { title: '手机号', dataIndex: 'phone' },
                    {
                        title: '角色',
                        dataIndex: 'roles',
                        render: (value: User['roles']) =>
                            value?.map((role) => <Tag key={role.id}>{role.name}</Tag>),
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
                        render: (_, user) => (
                            <Space>
                                <Can code="button.system.users.update">
                                    <Button type="link" onClick={() => setEditing({ mode: 'edit', user })}>
                                        编辑
                                    </Button>
                                </Can>
                                <Can code="button.system.users.reset-password">
                                    <Button type="link" onClick={() => openPasswordReset(user)}>
                                        重置密码
                                    </Button>
                                </Can>
                            </Space>
                        ),
                    },
                ]}
            />
            <Modal
                open={editing !== null}
                title={editing?.mode === 'create' ? '新增用户' : '编辑用户'}
                confirmLoading={save.isPending}
                onCancel={close}
                onOk={() => form.submit()}
            >
                <Form
                    key={editing?.mode === 'create' ? 'create' : editing?.user.id}
                    form={form}
                    layout="vertical"
                    initialValues={formValues}
                    onFinish={(values) => save.mutate(values)}
                >
                    <Form.Item name="name" label="姓名" rules={[{ required: true }]}>
                        <Input />
                    </Form.Item>
                    <Form.Item
                        name="email"
                        label="邮箱"
                        rules={[{ required: true, type: 'email' }]}
                    >
                        <Input />
                    </Form.Item>
                    <Form.Item name="username" label="登录名">
                        <Input />
                    </Form.Item>
                    <Form.Item name="phone" label="手机号">
                        <Input />
                    </Form.Item>
                    <Form.Item name="employee_id" label="关联员工档案">
                        <Select
                            allowClear
                            showSearch
                            optionFilterProp="label"
                            loading={employees.isLoading}
                            options={(employees.data?.items ?? []).map((employee) => ({
                                value: employee.id,
                                label: `${employee.employee_no} ${employee.name}`,
                            }))}
                        />
                    </Form.Item>
                    {editing?.mode === 'create' && (
                        <Form.Item
                            name="password"
                            label="初始密码"
                            rules={[{ required: true, min: 8 }]}
                        >
                            <Input.Password autoComplete="new-password" />
                        </Form.Item>
                    )}
                    <Can code="button.system.users.assign-roles">
                        <Form.Item name="role_ids" label="角色">
                            <Select mode="multiple" options={roleOptions} loading={roles.isLoading} />
                        </Form.Item>
                    </Can>
                    <Form.Item
                        name="status"
                        label="启用"
                        valuePropName="checked"
                        getValueFromEvent={(checked: boolean) => checked}
                    >
                        <Switch />
                    </Form.Item>
                </Form>
            </Modal>
            <Modal
                open={resetting !== null}
                title={`重置 ${resetting?.name ?? ''} 的密码`}
                confirmLoading={reset.isPending}
                onCancel={() => setResetting(null)}
                onOk={() => passwordForm.submit()}
            >
                <Form
                    key={resetting?.id}
                    form={passwordForm}
                    layout="vertical"
                    onFinish={({ password }) => {
                        if (resetting) {
                            reset.mutate({ id: resetting.id, password });
                        }
                    }}
                >
                    <Form.Item name="password" label="新密码" rules={[{ required: true, min: 8 }]}>
                        <Input.Password autoComplete="new-password" />
                    </Form.Item>
                </Form>
            </Modal>
        </>
    );
}
