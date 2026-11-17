import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { App, Button, Input, Select, Space, Table, Tag, Typography } from 'antd';
import { businessTime } from '@/utils/datetime';
import { useState } from 'react';
import Can from '@/components/can';
import {
    bindProductSn,
    generateProductSns,
    getProductSn,
    getProductSnLabel,
    listProductSns,
    printProductSn,
    PRODUCT_SN_STATUSES,
    voidProductSn,
} from '@/api/production';
import type {
    ProductSn,
    ProductSnBindPayload,
    ProductSnLabel,
    ProductSnStatus,
} from '@/api/production';
import { useAuthStore } from '@/stores/auth';
import { ApiRequestError } from '@/utils/api-error';
import { can } from '@/utils/permission';
import BindSnModal from './bind-sn-modal';
import DetailDrawer from './detail-drawer';
import GenerateSnModal from './generate-sn-modal';
import LabelModal from './label-modal';
import { snStatusColor } from './status-color';
import VoidSnModal from './void-sn-modal';

export default function ProductSnPage() {
    const { message } = App.useApp();
    const queryClient = useQueryClient();
    const user = useAuthStore((state) => state.user);
    const canRead = can(user, 'production.sns.index');
    const canCreate = can(user, 'button.production.sns.create');
    const canBind = can(user, 'button.production.sns.bind');
    const canPrint = can(user, 'button.production.sns.print');
    const canVoid = can(user, 'button.production.sns.void');
    const canTrace = can(user, 'traceability.forward.show');
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
                            <Tag color={snStatusColor[record.status]}>{record.status_label}</Tag>
                        ),
                    },
                    {
                        title: '打印时间',
                        width: 160,
                        render: (_, record) =>
                            record.printed_at
                                ? businessTime(record.printed_at).format('YYYY-MM-DD HH:mm')
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
                                {canTrace && (
                                    <Button
                                        type="link"
                                        href={`/traceability/forward?sn=${encodeURIComponent(record.sn)}`}
                                    >
                                        追溯
                                    </Button>
                                )}
                            </Space>
                        ),
                    },
                ]}
            />
            <DetailDrawer
                sn={detailSn}
                data={detail.data}
                onClose={() => setDetailSn(null)}
            />
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
