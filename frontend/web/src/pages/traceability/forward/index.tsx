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
    Steps,
    Table,
    Tag,
    Timeline,
    Typography,
} from 'antd';
import dayjs from 'dayjs';
import { useState } from 'react';
import { useSearchParams } from 'react-router';
import { getForwardTrace } from '@/api/traceability';
import type {
    TraceDefect,
    TraceInspection,
    TraceMaterialUsage,
    TraceProcessRecord,
    ForwardTrace,
} from '@/api/traceability';
import type { DefectiveProduct, InspectionTask } from '@/api/quality';
import { ApiRequestError } from '@/utils/api-error';

const formatTime = (value: string | null | undefined): string =>
    value ? dayjs(value).format('YYYY-MM-DD HH:mm:ss') : '-';

const processStatusColor: Record<string, string> = {
    pending: 'default',
    processing: 'processing',
    inspecting: 'gold',
    inspected: 'green',
    pending_transfer: 'blue',
    transferred: 'green',
    reworking: 'red',
};

function stepStatus(status?: string): 'wait' | 'process' | 'finish' | 'error' {
    if (!status || status === 'pending') {
        return 'wait';
    }

    if (status === 'transferred' || status === 'inspected') {
        return 'finish';
    }

    if (status === 'reworking') {
        return 'error';
    }

    return 'process';
}

function processLabel(record: TraceProcessRecord): string {
    if (!record.route_operation) {
        return `工序 ${record.sequence}`;
    }

    return `${record.route_operation.sequence}. ${record.route_operation.process.name}`;
}

export default function ForwardTracePage() {
    const [searchParams, setSearchParams] = useSearchParams();
    const currentSn = (searchParams.get('sn') ?? '').trim().toUpperCase();
    const [keyword, setKeyword] = useState(currentSn);

    const trace = useQuery({
        queryKey: ['traceability.forward', currentSn],
        queryFn: () => getForwardTrace(currentSn),
        enabled: currentSn !== '',
        retry: false,
    });

    const search = () => {
        const value = keyword.trim().toUpperCase();

        if (value !== '') {
            setSearchParams({ sn: value });
        }
    };

    return (
        <>
            <Space className="mb-4 w-full justify-between" wrap>
                <Typography.Title level={4} className="mb-0!">
                    产品正向追溯
                </Typography.Title>
            </Space>
            <Space.Compact className="mb-6 w-full max-w-xl">
                <Input
                    allowClear
                    placeholder="输入产品 SN 查询完整生产历史"
                    value={keyword}
                    onChange={(event) => setKeyword(event.target.value)}
                    onPressEnter={search}
                />
                <Button type="primary" loading={trace.isFetching} onClick={search}>
                    查询
                </Button>
            </Space.Compact>

            {currentSn === '' ? (
                <Empty description="输入产品 SN 查看从订单到检验的完整生产历史" />
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

function TraceResult({ data }: { data: ForwardTrace }) {
    const inspectionsByRecord = new Map<number, InspectionTask[]>();

    for (const inspection of data.inspections) {
        if (inspection.process_record) {
            const rows = inspectionsByRecord.get(inspection.process_record.id) ?? [];
            rows.push(inspection);
            inspectionsByRecord.set(inspection.process_record.id, rows);
        }
    }

    const defectsByOperation = new Map<number, DefectiveProduct[]>();

    for (const defect of data.defects) {
        const rows = defectsByOperation.get(defect.route_operation.id) ?? [];
        rows.push(defect);
        defectsByOperation.set(defect.route_operation.id, rows);
    }

    const recordsByOperation = new Map(
        data.process_records
            .filter((record) => record.route_operation !== null)
            .map((record) => [record.route_operation?.id as number, record]),
    );
    const orderInspections = data.inspections.filter(
        (inspection) => inspection.scope === 'order',
    );
    const timelineItems = [
        ...data.process_records.map((record) => {
            const inspections = inspectionsByRecord.get(record.id) ?? [];
            const defects =
                defectsByOperation.get(record.route_operation?.id ?? -1) ?? [];

            return {
                color: processStatusColor[record.status] ?? 'gray',
                children: (
                    <Space orientation="vertical" size={4}>
                        <Space wrap>
                            <Typography.Text strong>
                                {processLabel(record)}
                            </Typography.Text>
                            <Tag color={processStatusColor[record.status] ?? 'default'}>
                                {record.status_label}
                            </Tag>
                            {record.rework_count > 0 && (
                                <Tag color="orange">
                                    返工 {record.rework_count} 次
                                </Tag>
                            )}
                        </Space>
                        <Typography.Text type="secondary">
                            操作人员：
                            {record.employee
                                ? `${record.employee.employee_no} ${record.employee.name}`
                                : '-'}
                            ；设备：{record.equipment?.code ?? '-'}
                        </Typography.Text>
                        <Typography.Text type="secondary">
                            开始：{formatTime(record.started_at)}；完成：
                            {formatTime(record.completed_at)}；流转：
                            {formatTime(record.received_at)}
                        </Typography.Text>
                        {record.exception_reason && (
                            <Typography.Text type="danger">
                                异常：{record.exception_reason}（
                                {formatTime(record.exception_at)}）
                            </Typography.Text>
                        )}
                        {record.rework_reason && (
                            <Typography.Text type="warning">
                                返工原因：{record.rework_reason}（
                                {formatTime(record.rework_at)}）
                            </Typography.Text>
                        )}
                        {inspections.map((inspection) => (
                            <Typography.Text key={inspection.id}>
                                <Tag color={inspection.status === 'passed' ? 'green' : 'red'}>
                                    {inspection.inspection_type_label}{' '}
                                    {inspection.result_label}
                                </Tag>
                                {inspection.inspector
                                    ? `检验员 ${inspection.inspector.name}`
                                    : '待检验'}
                                {inspection.inspected_at
                                    ? ` · ${formatTime(inspection.inspected_at)}`
                                    : ''}
                            </Typography.Text>
                        ))}
                        {defects.map((defect) => (
                            <Typography.Text key={defect.id}>
                                <Tag color="red">不良 {defect.quantity}</Tag>
                                {defect.defect_code.name} · {defect.status_label}
                            </Typography.Text>
                        ))}
                    </Space>
                ),
            };
        }),
        ...orderInspections.map((inspection) => ({
            color:
                inspection.status === 'passed'
                    ? 'green'
                    : inspection.status === 'failed'
                      ? 'red'
                      : 'gray',
            children: (
                <Space orientation="vertical" size={4}>
                    <Space wrap>
                        <Typography.Text strong>
                            订单级检验：{inspection.inspection_type_label}
                        </Typography.Text>
                        <Tag color={inspection.status === 'passed' ? 'green' : 'red'}>
                            {inspection.result_label}
                        </Tag>
                    </Space>
                    <Typography.Text type="secondary">
                        {inspection.inspector
                            ? `检验员 ${inspection.inspector.name}`
                            : '待检验'}
                        {inspection.inspected_at
                            ? ` · ${formatTime(inspection.inspected_at)}`
                            : ''}
                    </Typography.Text>
                    {inspection.remark && (
                        <Typography.Text type="secondary">
                            备注：{inspection.remark}
                        </Typography.Text>
                    )}
                </Space>
            ),
        })),
    ];

    return (
        <Space orientation="vertical" className="w-full" size="large">
            {!data.has_data && (
                <Alert
                    type="warning"
                    showIcon
                    message={data.message ?? '该 SN 暂无生产数据'}
                />
            )}

            <Descriptions title="产品与批次" bordered size="small" column={{ xs: 1, md: 2, xl: 3 }}>
                <Descriptions.Item label="产品">
                    {data.product.code} {data.product.name}
                </Descriptions.Item>
                <Descriptions.Item label="产品型号">{data.product.model ?? '-'}</Descriptions.Item>
                <Descriptions.Item label="规格">{data.product.specification ?? '-'}</Descriptions.Item>
                <Descriptions.Item label="SN">{data.sn.sn}</Descriptions.Item>
                <Descriptions.Item label="SN 状态">
                    <Tag color={data.sn.status === 'active' ? 'green' : 'red'}>
                        {data.sn.status_label}
                    </Tag>
                </Descriptions.Item>
                <Descriptions.Item label="物料批次">{data.sn.material_batch_no ?? '-'}</Descriptions.Item>
                <Descriptions.Item label="生产订单">{data.production_order.code}</Descriptions.Item>
                <Descriptions.Item label="订单状态">
                    <Tag>{data.production_order.status_label}</Tag>
                </Descriptions.Item>
                <Descriptions.Item label="生产批次">{data.production_batch.code}</Descriptions.Item>
                <Descriptions.Item label="生产线">
                    {data.production_order.production_line.name}
                </Descriptions.Item>
                <Descriptions.Item label="产品版本">
                    {data.production_order.product_version.version}
                </Descriptions.Item>
                <Descriptions.Item label="BOM 版本">{data.bom.version}</Descriptions.Item>
                <Descriptions.Item label="工艺路线">
                    {data.route.code} / {data.route.version}
                </Descriptions.Item>
                <Descriptions.Item label="绑定操作人员">
                    {data.sn.employee
                        ? `${data.sn.employee.employee_no} ${data.sn.employee.name}`
                        : '-'}
                </Descriptions.Item>
                <Descriptions.Item label="绑定设备">
                    {data.sn.equipment
                        ? `${data.sn.equipment.code} ${data.sn.equipment.name}`
                        : '-'}
                </Descriptions.Item>
                <Descriptions.Item label="计划数量">
                    {data.production_order.planned_quantity}
                </Descriptions.Item>
                <Descriptions.Item label="已生产">
                    {data.production_order.produced_quantity}
                </Descriptions.Item>
                <Descriptions.Item label="合格 / 不良">
                    {data.production_order.qualified_quantity} /{' '}
                    {data.production_order.defective_quantity}
                </Descriptions.Item>
                <Descriptions.Item label="计划开始">
                    {formatTime(data.production_order.planned_start_at)}
                </Descriptions.Item>
                <Descriptions.Item label="计划结束">
                    {formatTime(data.production_order.planned_end_at)}
                </Descriptions.Item>
            </Descriptions>

            <Card title="工艺路线" size="small">
                <Steps
                    size="small"
                    labelPlacement="vertical"
                    items={data.route.operations.map((operation) => ({
                        title: operation.process.name,
                        description: `工序 ${operation.sequence}`,
                        status: stepStatus(
                            recordsByOperation.get(operation.id)?.status,
                        ),
                    }))}
                />
            </Card>

            <Card title="生产时间轴" size="small">
                {timelineItems.length === 0 ? (
                    <Empty description="暂无工序执行记录" />
                ) : (
                    <Timeline items={timelineItems} />
                )}
            </Card>

            <Card title="使用物料（BOM 单耗与订单领料合计）" size="small">
                <Table<TraceMaterialUsage>
                    rowKey={(record) => record.material.id}
                    size="small"
                    pagination={false}
                    dataSource={data.materials}
                    columns={[
                        { title: '物料编码', render: (_, record) => record.material.code },
                        { title: '物料名称', render: (_, record) => record.material.name },
                        { title: 'BOM 单耗', dataIndex: 'quantity' },
                        { title: '损耗率（%）', dataIndex: 'loss_rate' },
                        { title: '订单已领料', dataIndex: 'issued_quantity' },
                        { title: '单位', render: (_, record) => record.material.unit },
                    ]}
                />
            </Card>

            <Card title="质量检验记录" size="small">
                <Table<TraceInspection>
                    rowKey="id"
                    size="small"
                    pagination={false}
                    dataSource={data.inspections}
                    expandable={{
                        rowExpandable: (record) => record.items.length > 0,
                        expandedRowRender: (record) => (
                            <Table
                                rowKey="inspection_item_id"
                                size="small"
                                pagination={false}
                                dataSource={record.items}
                                columns={[
                                    { title: '检验项目', dataIndex: 'name' },
                                    { title: '实测值', dataIndex: 'measured_value' },
                                    { title: '标准值', dataIndex: 'standard_value' },
                                    { title: '范围', render: (_, item) => `${item.min_value} ~ ${item.max_value}` },
                                    {
                                        title: '判定',
                                        render: (_, item) => (
                                            <Tag color={item.passed ? 'green' : 'red'}>
                                                {item.passed ? '合格' : '不合格'}
                                            </Tag>
                                        ),
                                    },
                                ]}
                            />
                        ),
                    }}
                    columns={[
                        { title: '检验类型', dataIndex: 'inspection_type_label' },
                        {
                            title: '归属',
                            render: (_, record) => (
                                <Tag>{record.scope === 'order' ? '订单级' : 'SN 级'}</Tag>
                            ),
                        },
                        {
                            title: '工序',
                            render: (_, record) =>
                                record.route_operation?.process.name ?? '-',
                        },
                        {
                            title: '结果',
                            render: (_, record) => (
                                <Tag color={record.status === 'passed' ? 'green' : record.status === 'failed' ? 'red' : 'default'}>
                                    {record.result_label}
                                </Tag>
                            ),
                        },
                        {
                            title: '检验员',
                            render: (_, record) => record.inspector?.name ?? '-',
                        },
                        {
                            title: '检验时间',
                            render: (_, record) => formatTime(record.inspected_at),
                        },
                        { title: '备注', dataIndex: 'remark', render: (value: string | null) => value ?? '-' },
                    ]}
                />
            </Card>

            <Card title="不良处理记录（批次级）" size="small">
                <Table<TraceDefect>
                    rowKey="id"
                    size="small"
                    pagination={false}
                    dataSource={data.defects}
                    columns={[
                        {
                            title: '工序',
                            render: (_, record) => record.route_operation.process.name,
                        },
                        {
                            title: '缺陷代码',
                            render: (_, record) =>
                                `${record.defect_code.code} ${record.defect_code.name}`,
                        },
                        { title: '不良数量', dataIndex: 'quantity' },
                        {
                            title: '状态',
                            render: (_, record) => <Tag>{record.status_label}</Tag>,
                        },
                        {
                            title: '处理人',
                            render: (_, record) => record.handled_by?.name ?? '-',
                        },
                        {
                            title: '处置',
                            render: (_, record) => {
                                if (record.scrap_reason) {
                                    return `报废：${record.scrap_reason}`;
                                }

                                const rework = record.rework_orders.at(-1);

                                return rework ? `返工：${rework.reason}` : '-';
                            },
                        },
                        {
                            title: '登记时间',
                            render: (_, record) => formatTime(record.created_at),
                        },
                    ]}
                />
            </Card>
        </Space>
    );
}
