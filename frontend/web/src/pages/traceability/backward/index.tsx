import { useQuery } from '@tanstack/react-query';
import {
    Alert,
    Button,
    Card,
    Descriptions,
    Empty,
    Input,
    Space,
    Spin,
    Table,
    Tag,
    Typography,
} from 'antd';
import dayjs from 'dayjs';
import { useState } from 'react';
import { useSearchParams } from 'react-router';
import { getBackwardTrace } from '@/api/traceability';
import type {
    BackwardTrace,
    BackwardTraceBatch,
    BackwardTraceProductSn,
} from '@/api/traceability';
import { ApiRequestError } from '@/utils/api-error';

const formatTime = (value: string | null | undefined): string =>
    value ? dayjs(value).format('YYYY-MM-DD HH:mm:ss') : '-';

export default function BackwardTracePage() {
    const [searchParams, setSearchParams] = useSearchParams();
    const currentBatchNo = (searchParams.get('batch_no') ?? '').trim();
    const [keyword, setKeyword] = useState(currentBatchNo);

    const trace = useQuery({
        queryKey: ['traceability.backward', currentBatchNo],
        queryFn: () => getBackwardTrace(currentBatchNo),
        enabled: currentBatchNo !== '',
        retry: false,
    });

    const search = () => {
        const value = keyword.trim();

        if (value !== '') {
            setSearchParams({ batch_no: value });
        }
    };

    return (
        <>
            <Space className="mb-4 w-full justify-between" wrap>
                <Typography.Title level={4} className="mb-0!">
                    物料反向追溯
                </Typography.Title>
            </Space>
            <Space.Compact className="mb-6 w-full max-w-xl">
                <Input
                    allowClear
                    maxLength={80}
                    placeholder="输入物料批次号反查受影响的产品 SN"
                    value={keyword}
                    onChange={(event) => setKeyword(event.target.value)}
                    onPressEnter={search}
                />
                <Button type="primary" loading={trace.isFetching} onClick={search}>
                    查询
                </Button>
            </Space.Compact>

            {currentBatchNo === '' ? (
                <Empty description="输入物料批次号查看受影响的生产批次与产品 SN" />
            ) : trace.isLoading ? (
                <Spin className="mt-16 block" />
            ) : trace.isError ? (
                <Alert
                    type="error"
                    showIcon
                    message={
                        trace.error instanceof ApiRequestError
                            ? trace.error.message
                            : '追溯查询失败'
                    }
                />
            ) : trace.data ? (
                <TraceResult data={trace.data} />
            ) : null}
        </>
    );
}

function TraceResult({ data }: { data: BackwardTrace }) {
    return (
        <Space orientation="vertical" size={16} className="w-full">
            {!data.has_data && (
                <Alert
                    type="warning"
                    showIcon
                    message={data.message ?? '该物料批次未关联任何产品 SN'}
                />
            )}
            <Alert type="info" showIcon message="追溯口径" description={data.scope_note} />
            <Card size="small">
                <Descriptions column={{ xs: 1, md: 2, xl: 3 }} bordered size="small">
                    <Descriptions.Item label="物料批次">
                        {data.batch_no}
                    </Descriptions.Item>
                    <Descriptions.Item label="物料">
                        {data.materials.length > 0
                            ? data.materials
                                  .map((material) => `${material.code} ${material.name}`)
                                  .join('、')
                            : '-'}
                    </Descriptions.Item>
                    <Descriptions.Item label="生产订单">
                        {data.summary.production_order_count}
                    </Descriptions.Item>
                    <Descriptions.Item label="生产批次">
                        {data.summary.production_batch_count}
                    </Descriptions.Item>
                    <Descriptions.Item label="产品 SN">
                        {data.summary.sn_count}
                    </Descriptions.Item>
                </Descriptions>
            </Card>

            {data.has_data ? (
                <Card title="受影响生产批次" size="small">
                    <Table<BackwardTraceBatch>
                        rowKey={(record) => `${record.material.id}-${record.id}`}
                        size="small"
                        pagination={false}
                        dataSource={data.production_batches}
                        scroll={{ x: 1000 }}
                        expandable={{
                            expandedRowRender: (record) => (
                                <ProductSnTable rows={record.product_sns} />
                            ),
                        }}
                        columns={[
                            { title: '生产订单', dataIndex: ['production_order', 'code'] },
                            {
                                title: '订单状态',
                                render: (_, record) => (
                                    <Tag>{record.production_order.status_label}</Tag>
                                ),
                            },
                            {
                                title: '产品',
                                render: (_, record) =>
                                    `${record.production_order.product.code} ${record.production_order.product.name}`,
                            },
                            { title: '生产批次', dataIndex: 'code' },
                            {
                                title: '物料',
                                render: (_, record) =>
                                    `${record.material.code} ${record.material.name}`,
                            },
                            { title: '该订单领用', dataIndex: 'issued_quantity' },
                            {
                                title: '产品 SN',
                                render: (_, record) => record.product_sns.length,
                            },
                        ]}
                    />
                </Card>
            ) : (
                <Empty description="暂无关联产品 SN" />
            )}
        </Space>
    );
}

function ProductSnTable({ rows }: { rows: BackwardTraceProductSn[] }) {
    return (
        <Table<BackwardTraceProductSn>
            rowKey="id"
            size="small"
            pagination={false}
            dataSource={rows}
            columns={[
                { title: '产品 SN', dataIndex: 'sn' },
                {
                    title: '状态',
                    render: (_, record) => (
                        <Tag color={record.status === 'active' ? 'green' : 'red'}>
                            {record.status_label}
                        </Tag>
                    ),
                },
                {
                    title: '生成时间',
                    render: (_, record) => formatTime(record.created_at),
                },
                {
                    title: '作废信息',
                    render: (_, record) =>
                        record.status === 'voided'
                            ? `${formatTime(record.voided_at)} ${record.void_reason ?? ''}`.trim()
                            : '-',
                },
                {
                    title: '操作',
                    render: (_, record) => (
                        <Typography.Link
                            href={`/traceability/forward?sn=${encodeURIComponent(record.sn)}`}
                        >
                            正向追溯
                        </Typography.Link>
                    ),
                },
            ]}
        />
    );
}
