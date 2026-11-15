import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
    App,
    Button,
    Descriptions,
    Drawer,
    Form,
    Input,
    InputNumber,
    Modal,
    Select,
    Space,
    Table,
    Tag,
    Typography,
} from 'antd';
import dayjs from 'dayjs';
import { useState } from 'react';
import Can from '@/components/can';
import {
    bindProductSn,
    generateProductSns,
    getProductSn,
    getProductSnLabel,
    getProductSnOptions,
    listProductSns,
    printProductSn,
    PRODUCT_SN_STATUSES,
    voidProductSn,
} from '@/api/production';
import type {
    ProductSn,
    ProductSnBindPayload,
    ProductSnGeneratePayload,
    ProductSnLabel,
    ProductSnStatus,
} from '@/api/production';
import { useAuthStore } from '@/stores/auth';
import { ApiRequestError } from '@/utils/api-error';
import { can } from '@/utils/permission';

const statusColor = Object.fromEntries(
    PRODUCT_SN_STATUSES.map(({ value, color }) => [value, color]),
);

function GenerateSnModal({
    saving,
    onSave,
    onClose,
}: {
    saving: boolean;
    onSave: (payload: ProductSnGeneratePayload) => void;
    onClose: () => void;
}) {
    const [form] = Form.useForm();
    const options = useQuery({
        queryKey: ['production.sns', 'options'],
        queryFn: getProductSnOptions,
    });

    return (
        <Modal
            open
            title="生成 SN"
            confirmLoading={saving}
            width={520}
            onCancel={onClose}
            onOk={() => form.submit()}
        >
            <Form
                form={form}
                layout="vertical"
                initialValues={{ quantity: 1 }}
                onFinish={(values) =>
                    onSave({
                        production_order_id: values.production_order_id,
                        quantity: values.quantity,
                        employee_id: values.employee_id,
                    })
                }
            >
                <Form.Item
                    name="production_order_id"
                    label="生产订单"
                    rules={[{ required: true, message: '请选择生产订单' }]}
                >
                    <Select
                        showSearch
                        optionFilterProp="label"
                        loading={options.isLoading}
                        options={(options.data?.orders ?? []).map((order) => ({
                            value: order.id,
                            label: `${order.code}${order.product ? `（${order.product.name}）` : ''}`,
                        }))}
                    />
                </Form.Item>
                <Form.Item
                    name="quantity"
                    label="生成数量"
                    rules={[{ required: true, message: '请输入生成数量' }]}
                >
                    <InputNumber min={1} max={1000} className="w-full" />
                </Form.Item>
                <Form.Item name="employee_id" label="操作人员">
                    <Select
                        allowClear
                        showSearch
                        optionFilterProp="label"
                        options={(options.data?.employees ?? []).map((employee) => ({
                            value: employee.id,
                            label: `${employee.employee_no} ${employee.name}`,
                        }))}
                    />
                </Form.Item>
            </Form>
        </Modal>
    );
}

function BindSnModal({
    sn,
    saving,
    onSave,
    onClose,
}: {
    sn: ProductSn;
    saving: boolean;
    onSave: (payload: ProductSnBindPayload) => void;
    onClose: () => void;
}) {
    const [form] = Form.useForm();
    const options = useQuery({
        queryKey: ['production.sns', 'options'],
        queryFn: getProductSnOptions,
    });

    return (
        <Modal
            open
            title={`绑定信息 · ${sn.sn}`}
            confirmLoading={saving}
            width={520}
            onCancel={onClose}
            onOk={() => form.submit()}
        >
            <Form
                form={form}
                layout="vertical"
                initialValues={{
                    material_batch_no: sn.material_batch_no ?? undefined,
                    employee_id: sn.employee_id ?? undefined,
                }}
                onFinish={(values) =>
                    onSave({
                        material_batch_no: values.material_batch_no || undefined,
                        employee_id: values.employee_id,
                    })
                }
            >
                <Form.Item name="material_batch_no" label="物料批次">
                    <Input placeholder="扫描或输入物料批次号" maxLength={64} />
                </Form.Item>
                <Form.Item name="employee_id" label="操作人员">
                    <Select
                        allowClear
                        showSearch
                        optionFilterProp="label"
                        options={(options.data?.employees ?? []).map((employee) => ({
                            value: employee.id,
                            label: `${employee.employee_no} ${employee.name}`,
                        }))}
                    />
                </Form.Item>
            </Form>
        </Modal>
    );
}

function VoidSnModal({
    sn,
    saving,
    onSave,
    onClose,
}: {
    sn: ProductSn;
    saving: boolean;
    onSave: (reason: string) => void;
    onClose: () => void;
}) {
    const [form] = Form.useForm();

    return (
        <Modal
            open
            title={`作废 SN · ${sn.sn}`}
            confirmLoading={saving}
            width={480}
            onCancel={onClose}
            onOk={() => form.submit()}
        >
            <Form form={form} layout="vertical" onFinish={(values) => onSave(values.reason)}>
                <Form.Item
                    name="reason"
                    label="作废原因"
                    rules={[{ required: true, message: '请填写作废原因' }]}
                >
                    <Input.TextArea rows={3} maxLength={255} placeholder="如：产品报废" />
                </Form.Item>
            </Form>
        </Modal>
    );
}

function LabelModal({
    label,
    printing,
    onPrint,
    onClose,
}: {
    label: ProductSnLabel;
    printing: boolean;
    onPrint: () => void;
    onClose: () => void;
}) {
    return (
        <Modal
            open
            title="SN 标签"
            onCancel={onClose}
            footer={
                <Space>
                    <Button onClick={onClose}>关闭</Button>
                    <Button type="primary" loading={printing} onClick={onPrint}>
                        打印
                    </Button>
                </Space>
            }
        >
            <div className="sn-label rounded border-2 border-dashed border-gray-400 p-6">
                <Typography.Title level={3} className="mb-2! text-center">
                    {label.product_name}
                </Typography.Title>
                <Typography.Paragraph className="mb-4 text-center" type="secondary">
                    {label.product_code}
                </Typography.Paragraph>
                <div className="mb-4 text-center font-mono text-2xl tracking-widest">
                    {label.sn}
                </div>
                <Descriptions column={1} size="small">
                    <Descriptions.Item label="生产订单">{label.production_order}</Descriptions.Item>
                    <Descriptions.Item label="生产批次">{label.production_batch}</Descriptions.Item>
                    <Descriptions.Item label="生成时间">
                        {dayjs(label.generated_at).format('YYYY-MM-DD HH:mm')}
                    </Descriptions.Item>
                </Descriptions>
            </div>
        </Modal>
    );
}

export default function ProductSnPage() {
    const { message } = App.useApp();
    const queryClient = useQueryClient();
    const user = useAuthStore((state) => state.user);
    const canRead = can(user, 'production.sns.index');
    const canCreate = can(user, 'button.production.sns.create');
    const canBind = can(user, 'button.production.sns.bind');
    const canPrint = can(user, 'button.production.sns.print');
    const canVoid = can(user, 'button.production.sns.void');
    const [page, setPage] = useState(1);
    const [keyword, setKeyword] = useState('');
    const [status, setStatus] = useState<ProductSnStatus>();
    const [generating, setGenerating] = useState(false);
    const [detailSn, setDetailSn] = useState<string | null>(null);
    const [bindTarget, setBindTarget] = useState<ProductSn | null>(null);
    const [voidTarget, setVoidTarget] = useState<ProductSn | null>(null);
    const [label, setLabel] = useState<ProductSnLabel | null>(null);

    const sns = useQuery({
        queryKey: ['production.sns', page, keyword, status],
        queryFn: () => listProductSns({ page, keyword, status }),
        enabled: canRead,
    });
    const detail = useQuery({
        queryKey: ['production.sns', 'detail', detailSn],
        queryFn: () => getProductSn(detailSn as string),
        enabled: detailSn !== null,
    });

    const showError = (error: unknown) =>
        message.error(error instanceof ApiRequestError ? error.message : '操作失败');
    const invalidate = async () => {
        await Promise.all([
            queryClient.invalidateQueries({ queryKey: ['production.sns'] }),
            queryClient.invalidateQueries({ queryKey: ['production.orders'] }),
        ]);
    };
    const generate = useMutation({
        mutationFn: generateProductSns,
        onSuccess: async (result) => {
            await invalidate();
            message.success(`已生成批次 ${result.batch.code}，共 ${result.batch.quantity} 个 SN`);
            setGenerating(false);
        },
        onError: showError,
    });
    const bind = useMutation({
        mutationFn: ({ sn, payload }: { sn: string; payload: ProductSnBindPayload }) =>
            bindProductSn(sn, payload),
        onSuccess: async () => {
            await invalidate();
            message.success('绑定信息已更新');
            setBindTarget(null);
        },
        onError: showError,
    });
    const fetchLabel = useMutation({
        mutationFn: getProductSnLabel,
        onSuccess: ({ label: fetched }) => setLabel(fetched),
        onError: showError,
    });
    const print = useMutation({
        mutationFn: printProductSn,
        onSuccess: async () => {
            await invalidate();
            window.print();
            setLabel(null);
            message.success('标签已打印');
        },
        onError: showError,
    });
    const voidSn = useMutation({
        mutationFn: ({ sn, reason }: { sn: string; reason: string }) => voidProductSn(sn, reason),
        onSuccess: async () => {
            await invalidate();
            message.success('SN 已作废');
            setVoidTarget(null);
        },
        onError: showError,
    });

    return (
        <>
            <Space className="mb-4 w-full justify-between" wrap>
                <Typography.Title level={4} className="mb-0!">
                    SN 管理
                </Typography.Title>
                <Can code="button.production.sns.create">
                    <Button type="primary" onClick={() => setGenerating(true)}>
                        生成 SN
                    </Button>
                </Can>
            </Space>
            <Space className="mb-4" wrap>
                <Select
                    allowClear
                    placeholder="SN 状态"
                    className="w-32"
                    options={PRODUCT_SN_STATUSES.map(({ value, label: text }) => ({
                        value,
                        label: text,
                    }))}
                    onChange={(value?: ProductSnStatus) => {
                        setStatus(value);
                        setPage(1);
                    }}
                />
                <Input.Search
                    allowClear
                    placeholder="SN / 订单 / 批次 / 物料批次"
                    className="w-64"
                    onSearch={(value) => {
                        setKeyword(value);
                        setPage(1);
                    }}
                />
            </Space>
            <Table<ProductSn>
                rowKey="id"
                loading={sns.isLoading}
                dataSource={sns.data?.items}
                scroll={{ x: 1280 }}
                pagination={{
                    current: page,
                    pageSize: sns.data?.meta.per_page,
                    total: sns.data?.meta.total,
                    onChange: setPage,
                }}
                columns={[
                    {
                        title: 'SN',
                        dataIndex: 'sn',
                        width: 190,
                        render: (value: string) => <Typography.Text copyable>{value}</Typography.Text>,
                    },
                    {
                        title: '产品',
                        width: 140,
                        render: (_, record) => record.production_order?.product?.name ?? '-',
                    },
                    {
                        title: '生产订单',
                        width: 140,
                        render: (_, record) => record.production_order?.code ?? '-',
                    },
                    {
                        title: '生产批次',
                        width: 150,
                        render: (_, record) => record.production_batch?.code ?? '-',
                    },
                    {
                        title: '物料批次',
                        width: 150,
                        render: (_, record) => record.material_batch_no ?? '-',
                    },
                    {
                        title: '操作人员',
                        width: 150,
                        render: (_, record) =>
                            record.employee
                                ? `${record.employee.employee_no} ${record.employee.name}`
                                : '-',
                    },
                    {
                        title: '状态',
                        width: 90,
                        render: (_, record) => (
                            <Tag color={statusColor[record.status]}>{record.status_label}</Tag>
                        ),
                    },
                    {
                        title: '打印时间',
                        width: 160,
                        render: (_, record) =>
                            record.printed_at
                                ? dayjs(record.printed_at).format('YYYY-MM-DD HH:mm')
                                : '未打印',
                    },
                    {
                        title: '操作',
                        width: 230,
                        fixed: 'right' as const,
                        render: (_, record) => (
                            <Space size="small" wrap>
                                <Button type="link" onClick={() => setDetailSn(record.sn)}>
                                    详情
                                </Button>
                                {canBind && record.status === 'active' && (
                                    <Button type="link" onClick={() => setBindTarget(record)}>
                                        绑定
                                    </Button>
                                )}
                                {canPrint && record.status === 'active' && (
                                    <Button
                                        type="link"
                                        loading={fetchLabel.isPending}
                                        onClick={() => fetchLabel.mutate(record.sn)}
                                    >
                                        标签
                                    </Button>
                                )}
                                {canVoid && record.status === 'active' && (
                                    <Button type="link" danger onClick={() => setVoidTarget(record)}>
                                        作废
                                    </Button>
                                )}
                            </Space>
                        ),
                    },
                ]}
            />
            <Drawer
                open={detailSn !== null}
                title={detailSn ?? 'SN 详情'}
                size={760}
                onClose={() => setDetailSn(null)}
            >
                {detail.data && (
                    <Space orientation="vertical" className="w-full" size="large">
                        <Descriptions column={2} bordered size="small">
                            <Descriptions.Item label="状态">
                                <Tag color={statusColor[detail.data.status]}>
                                    {detail.data.status_label}
                                </Tag>
                            </Descriptions.Item>
                            <Descriptions.Item label="物料批次">
                                {detail.data.material_batch_no ?? '-'}
                            </Descriptions.Item>
                            <Descriptions.Item label="生产订单">
                                {detail.data.production_order?.code ?? '-'}
                            </Descriptions.Item>
                            <Descriptions.Item label="生产批次">
                                {detail.data.production_batch?.code ?? '-'}
                            </Descriptions.Item>
                            <Descriptions.Item label="产品">
                                {detail.data.production_order?.product?.name ?? '-'}
                            </Descriptions.Item>
                            <Descriptions.Item label="操作人员">
                                {detail.data.employee
                                    ? `${detail.data.employee.employee_no} ${detail.data.employee.name}`
                                    : '-'}
                            </Descriptions.Item>
                            {detail.data.void_reason && (
                                <Descriptions.Item label="作废原因" span={2}>
                                    {detail.data.void_reason}
                                </Descriptions.Item>
                            )}
                        </Descriptions>
                        <div>
                            <Typography.Title level={5}>工序执行状态</Typography.Title>
                            <Table
                                rowKey="id"
                                size="small"
                                pagination={false}
                                dataSource={detail.data.process_records ?? []}
                                columns={[
                                    { title: '顺序', dataIndex: 'sequence', width: 70 },
                                    {
                                        title: '工序',
                                        render: (_, record) =>
                                            record.route_operation
                                                ? `${record.route_operation.sequence}. ${record.route_operation.process.name}`
                                                : '-',
                                    },
                                    {
                                        title: '操作人员',
                                        width: 130,
                                        render: (_, record) =>
                                            record.employee
                                                ? `${record.employee.employee_no} ${record.employee.name}`
                                                : '-',
                                    },
                                    {
                                        title: '状态',
                                        width: 100,
                                        render: (_, record) => <Tag>{record.status_label}</Tag>,
                                    },
                                    {
                                        title: '开始时间',
                                        width: 150,
                                        render: (_, record) =>
                                            record.started_at
                                                ? dayjs(record.started_at).format('MM-DD HH:mm')
                                                : '-',
                                    },
                                ]}
                            />
                        </div>
                    </Space>
                )}
            </Drawer>
            {generating && (
                <GenerateSnModal
                    saving={generate.isPending}
                    onSave={(payload) => generate.mutate(payload)}
                    onClose={() => setGenerating(false)}
                />
            )}
            {bindTarget && (
                <BindSnModal
                    sn={bindTarget}
                    saving={bind.isPending}
                    onSave={(payload) => bind.mutate({ sn: bindTarget.sn, payload })}
                    onClose={() => setBindTarget(null)}
                />
            )}
            {voidTarget && (
                <VoidSnModal
                    sn={voidTarget}
                    saving={voidSn.isPending}
                    onSave={(reason) => voidSn.mutate({ sn: voidTarget.sn, reason })}
                    onClose={() => setVoidTarget(null)}
                />
            )}
            {label && (
                <LabelModal
                    label={label}
                    printing={print.isPending}
                    onPrint={() => print.mutate(label.sn)}
                    onClose={() => setLabel(null)}
                />
            )}
        </>
    );
}
