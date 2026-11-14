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
    Switch,
    Table,
    Tag,
    Typography,
} from 'antd';
import type { ColumnsType } from 'antd/es/table';
import { useState } from 'react';
import type { ReactNode } from 'react';
import Can from '@/components/can';
import type { BasicDataParams } from '@/api/basic-data';
import type { Paginated } from '@/types/api';
import { ApiRequestError } from '@/utils/api-error';

export type ResourceField = {
    name: string;
    label: string;
    type?: 'text' | 'number' | 'switch' | 'select';
    required?: boolean;
    options?: { label: string; value: number }[];
    loading?: boolean;
};

type Entity = { id: number; status: boolean };

/**
 * A cleared select yields `undefined`, which JSON.stringify drops, so the API
 * would keep the old parent. Send `null` instead so "clear" reaches the server.
 */
export function withClearedSelects(values: Record<string, unknown>, fields: ResourceField[]) {
    const selectNames = fields.filter((field) => field.type === 'select').map((field) => field.name);

    return Object.fromEntries(
        Object.entries(values).map(([key, value]) => [
            key,
            value === undefined && selectNames.includes(key) ? null : value,
        ]),
    );
}

type Props<T extends Entity, P extends object> = {
    title: string;
    /** Query key prefix and permission namespace, e.g. `basic.factories`. */
    resource: string;
    list: (params: BasicDataParams) => Promise<Paginated<T>>;
    create: (payload: P) => Promise<T>;
    update: (id: number, payload: Partial<P> | { status: boolean }) => Promise<T>;
    columns: ColumnsType<T>;
    fields: ResourceField[];
    /** Parent selects rendered as toolbar filters; their values become query params. */
    filters?: ResourceField[];
};

export default function ResourcePage<T extends Entity, P extends object>({
    title,
    resource,
    list,
    create,
    update,
    columns,
    fields,
    filters = [],
}: Props<T, P>) {
    const { message } = App.useApp();
    const queryClient = useQueryClient();
    const [keyword, setKeyword] = useState('');
    const [page, setPage] = useState(1);
    const [filterValues, setFilterValues] = useState<Record<string, number | undefined>>({});
    const [editing, setEditing] = useState<{ mode: 'create' } | { mode: 'edit'; record: T } | null>(null);
    const [form] = Form.useForm();

    const query = useQuery({
        queryKey: [resource, page, keyword, filterValues],
        queryFn: () => list({ page, keyword, ...filterValues }),
    });

    const close = () => setEditing(null);
    const showError = (error: unknown) =>
        message.error(error instanceof ApiRequestError ? error.message : '操作失败');

    const save = useMutation({
        mutationFn: async (values: P) => {
            if (!editing) {
                throw new Error('未选择记录');
            }

            return editing.mode === 'create'
                ? create(values)
                : update(editing.record.id, values);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: [resource] });
            message.success('已保存');
            close();
        },
        onError: showError,
    });

    const disable = useMutation({
        mutationFn: (record: T) => update(record.id, { status: false }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: [resource] });
            message.success('已停用');
        },
        onError: showError,
    });

    const formValues = editing?.mode === 'edit' ? editing.record : { status: true };

    function fieldControl(field: ResourceField): ReactNode {
        if (field.type === 'number') {
            return <InputNumber min={0} className="w-full" />;
        }

        if (field.type === 'switch') {
            return <Switch />;
        }

        if (field.type === 'select') {
            return (
                <Select
                    options={field.options}
                    loading={field.loading}
                    allowClear={!field.required}
                    showSearch
                    optionFilterProp="label"
                />
            );
        }

        return <Input />;
    }

    return (
        <>
            <Space className="mb-4 w-full justify-between" wrap>
                <Typography.Title level={4} className="mb-0!">
                    {title}
                </Typography.Title>
                <Space wrap>
                    {filters.map((filter) => (
                        <Select
                            key={filter.name}
                            allowClear
                            placeholder={filter.label}
                            className="w-40"
                            options={filter.options}
                            loading={filter.loading}
                            onChange={(value?: number) => {
                                setFilterValues((current) => ({ ...current, [filter.name]: value }));
                                setPage(1);
                            }}
                        />
                    ))}
                    <Input.Search
                        allowClear
                        placeholder="编码 / 名称"
                        className="w-56"
                        onSearch={(value) => {
                            setKeyword(value);
                            setPage(1);
                        }}
                    />
                    <Can code={`button.${resource}.create`}>
                        <Button type="primary" onClick={() => setEditing({ mode: 'create' })}>
                            新增
                        </Button>
                    </Can>
                </Space>
            </Space>
            <Table<T>
                rowKey="id"
                loading={query.isLoading}
                dataSource={query.data?.items}
                pagination={{
                    current: page,
                    pageSize: query.data?.meta.per_page,
                    total: query.data?.meta.total,
                    onChange: setPage,
                }}
                columns={[
                    ...columns,
                    {
                        title: '状态',
                        dataIndex: 'status',
                        width: 90,
                        render: (status: boolean) => (
                            <Tag color={status ? 'green' : 'default'}>{status ? '启用' : '停用'}</Tag>
                        ),
                    },
                    {
                        title: '操作',
                        key: 'actions',
                        width: 140,
                        render: (_, record) => (
                            <Space>
                                <Can code={`button.${resource}.update`}>
                                    <Button type="link" onClick={() => setEditing({ mode: 'edit', record })}>
                                        编辑
                                    </Button>
                                </Can>
                                {record.status && (
                                    <Can code={`button.${resource}.update`}>
                                        <Popconfirm
                                            title="停用后不会出现在新业务的下拉中"
                                            okText="停用"
                                            cancelText="取消"
                                            onConfirm={() => disable.mutate(record)}
                                        >
                                            <Button type="link" danger>
                                                停用
                                            </Button>
                                        </Popconfirm>
                                    </Can>
                                )}
                            </Space>
                        ),
                    },
                ]}
            />
            <Modal
                open={editing !== null}
                title={editing?.mode === 'create' ? `新增${title}` : `编辑${title}`}
                confirmLoading={save.isPending}
                onCancel={close}
                onOk={() => form.submit()}
            >
                <Form
                    key={editing?.mode === 'create' ? 'create' : editing?.record.id}
                    form={form}
                    layout="vertical"
                    initialValues={formValues}
                    onFinish={(values) => save.mutate(withClearedSelects(values, fields) as P)}
                >
                    {fields.map((field) => (
                        <Form.Item
                            key={field.name}
                            name={field.name}
                            label={field.label}
                            rules={field.required ? [{ required: true, message: `请填写${field.label}` }] : []}
                            valuePropName={field.type === 'switch' ? 'checked' : 'value'}
                        >
                            {fieldControl(field)}
                        </Form.Item>
                    ))}
                </Form>
            </Modal>
        </>
    );
}
