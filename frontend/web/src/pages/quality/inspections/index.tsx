import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { App, Button, DatePicker, Empty, Select, Space, Table, Tag, Typography } from 'antd';
import dayjs from 'dayjs';
import type { Dayjs } from 'dayjs';
import { useState } from 'react';
import Can from '@/components/can';
import {
    createInspectionTask,
    getInspectionOptions,
    INSPECTION_RESULTS,
    INSPECTION_TYPES,
    listInspectionTasks,
    recordInspectionTask,
    uploadInspectionImages,
} from '@/api/quality';
import type {
    InspectionRecordPayload,
    InspectionStatus,
    InspectionTask,
    InspectionTaskPayload,
    InspectionType,
} from '@/api/quality';
import { useAuthStore } from '@/stores/auth';
import { ApiRequestError } from '@/utils/api-error';
import { can } from '@/utils/permission';
import CreateInspectionModal from './create-modal';
import InspectionDetailDrawer from './detail-drawer';
import { operationLabel } from './label';
import RecordInspectionModal from './record-modal';

const typeColor = (type: InspectionType) => INSPECTION_TYPES.find((item) => item.value === type)?.color;
const resultColor = (status: InspectionStatus) => INSPECTION_RESULTS.find((item) => item.value === status)?.color;

export default function InspectionsPage({ recordsOnly = false }: { recordsOnly?: boolean }) {
    const { message } = App.useApp();
    const queryClient = useQueryClient();
    const user = useAuthStore((state) => state.user);
    const canRead = can(user, 'quality.inspections.index');
    const canRecord = can(user, 'button.quality.inspections.record');
    const [page, setPage] = useState(1);
    const [orderId, setOrderId] = useState<number>();
    const [operationId, setOperationId] = useState<number>();
    const [type, setType] = useState<InspectionType>();
    const [result, setResult] = useState<InspectionStatus>();
    const [range, setRange] = useState<[Dayjs, Dayjs]>();
    const [creating, setCreating] = useState(false);
    const [recording, setRecording] = useState<InspectionTask | null>(null);
    const [detail, setDetail] = useState<InspectionTask | null>(null);

    const options = useQuery({
        queryKey: ['quality.inspections', 'options'],
        queryFn: () => getInspectionOptions(),
        enabled: canRead,
    });
    const operationOptions = useQuery({
        queryKey: ['quality.inspections', 'options', orderId],
        queryFn: () => getInspectionOptions(orderId),
        enabled: canRead && orderId !== undefined,
    });
    const tasks = useQuery({
        queryKey: ['quality.inspections', recordsOnly, page, orderId, operationId, type, result, range?.[0]?.format(), range?.[1]?.format()],
        queryFn: () => listInspectionTasks({
            page,
            production_order_id: orderId,
            route_operation_id: operationId,
            inspection_type: type,
            result,
            judged: recordsOnly ? 1 : undefined,
            start_date: range?.[0]?.format('YYYY-MM-DD'),
            end_date: range?.[1]?.format('YYYY-MM-DD'),
        }),
        enabled: canRead,
    });

    const invalidate = () => queryClient.invalidateQueries({ queryKey: ['quality.inspections'] });
    const showError = (error: unknown) =>
        message.error(error instanceof ApiRequestError ? error.message : '操作失败');

    const create = useMutation({
        mutationFn: createInspectionTask,
        onSuccess: async () => {
            await invalidate();
            message.success('检验任务已创建');
            setCreating(false);
        },
        onError: showError,
    });
    const record = useMutation({
        mutationFn: ({ id, payload }: { id: number; payload: InspectionRecordPayload }) =>
            recordInspectionTask(id, payload),
        onSuccess: async (task) => {
            await invalidate();
            message.success(`判定结果：${task.result_label}`);
            setRecording(null);
            setDetail(task);
        },
        onError: showError,
    });
    const upload = useMutation({
        mutationFn: ({ id, files }: { id: number; files: File[] }) => uploadInspectionImages(id, files),
        onSuccess: async (task) => {
            await invalidate();
            setDetail(task);
            message.success('图片已上传');
        },
        onError: showError,
    });
    if (!canRead) {
        return <Empty description="暂无检验任务查看权限" />;
    }

    const resultOptions = recordsOnly
        ? INSPECTION_RESULTS.filter((item) => item.value !== 'pending')
        : INSPECTION_RESULTS;

    return (
        <>
            <Space className="mb-4 w-full justify-between" wrap>
                <div>
                    <Typography.Title level={4} className="mb-0!">
                        {recordsOnly ? '检验记录' : '检验任务'}
                    </Typography.Title>
                    <Typography.Text type="secondary">
                        {recordsOnly ? '已判定检验的实测值与证据' : '为 IPQC / OQC 创建任务并录入实测值'}
                    </Typography.Text>
                </div>
                {!recordsOnly && (
                    <Can code="button.quality.inspections.create">
                        <Button type="primary" onClick={() => setCreating(true)}>新建检验任务</Button>
                    </Can>
                )}
            </Space>

            <Space className="mb-4" wrap>
                <Select
                    allowClear
                    showSearch
                    optionFilterProp="label"
                    placeholder="生产订单"
                    style={{ width: 220 }}
                    loading={options.isLoading}
                    value={orderId}
                    onChange={(value) => {
                        setOrderId(value);
                        setOperationId(undefined);
                        setPage(1);
                    }}
                    options={(options.data?.orders ?? []).map((order) => ({
                        value: order.id,
                        label: `${order.code} ${order.product.name}`,
                    }))}
                />
                <Select
                    allowClear
                    showSearch
                    optionFilterProp="label"
                    placeholder="工序"
                    style={{ width: 180 }}
                    loading={operationOptions.isLoading}
                    disabled={orderId === undefined}
                    value={operationId}
                    onChange={(value) => { setOperationId(value); setPage(1); }}
                    options={(operationOptions.data?.operations ?? []).map((operation) => ({
                        value: operation.id,
                        label: `${operation.sequence}. ${operation.process.name}`,
                    }))}
                />
                <Select
                    allowClear
                    placeholder="检验类型"
                    style={{ width: 150 }}
                    value={type}
                    onChange={(value) => { setType(value); setPage(1); }}
                    options={INSPECTION_TYPES.map(({ value, label }) => ({ value, label }))}
                />
                <Select
                    allowClear
                    placeholder="判定结果"
                    style={{ width: 130 }}
                    value={result}
                    onChange={(value) => { setResult(value); setPage(1); }}
                    options={resultOptions.map(({ value, label }) => ({ value, label }))}
                />
                <DatePicker.RangePicker
                    value={range}
                    onChange={(value) => {
                        setRange(value?.[0] && value[1] ? [value[0], value[1]] : undefined);
                        setPage(1);
                    }}
                />
            </Space>

            <Table<InspectionTask>
                rowKey="id"
                loading={tasks.isLoading}
                dataSource={tasks.data?.items ?? []}
                pagination={{
                    current: page,
                    pageSize: tasks.data?.meta.per_page ?? 15,
                    total: tasks.data?.meta.total ?? 0,
                    onChange: setPage,
                    showSizeChanger: false,
                }}
                columns={[
                    {
                        title: '类型',
                        dataIndex: 'inspection_type',
                        width: 140,
                        render: (value: InspectionType) => (
                            <Tag color={typeColor(value)}>{INSPECTION_TYPES.find((item) => item.value === value)?.label}</Tag>
                        ),
                    },
                    {
                        title: '生产订单',
                        render: (_, task) => (
                            <Space direction="vertical" size={0}>
                                <Typography.Text>{task.production_order.code}</Typography.Text>
                                <Typography.Text type="secondary">{task.production_order.product.name}</Typography.Text>
                            </Space>
                        ),
                    },
                    {
                        title: '工序',
                        render: (_, task) => operationLabel(task),
                    },
                    {
                        title: '判定',
                        dataIndex: 'status',
                        width: 100,
                        render: (value: InspectionStatus, task) => (
                            <Tag color={resultColor(value)}>{task.result_label}</Tag>
                        ),
                    },
                    { title: '检验员', render: (_, task) => task.inspector?.name ?? '—' },
                    {
                        title: '检验时间',
                        render: (_, task) => task.inspected_at
                            ? dayjs(task.inspected_at).format('YYYY-MM-DD HH:mm')
                            : '—',
                    },
                    {
                        title: '操作',
                        width: 160,
                        render: (_, task) => (
                            <Space>
                                {task.status === 'pending' && canRecord && (
                                    <Button size="small" type="link" onClick={() => setRecording(task)}>录入结果</Button>
                                )}
                                <Button size="small" type="link" onClick={() => setDetail(task)}>详情</Button>
                            </Space>
                        ),
                    },
                ]}
            />

            {creating && (
                <CreateInspectionModal
                    saving={create.isPending}
                    onSave={(payload: InspectionTaskPayload) => create.mutate(payload)}
                    onClose={() => setCreating(false)}
                />
            )}
            {recording && (
                <RecordInspectionModal
                    task={recording}
                    saving={record.isPending}
                    onSave={(payload) => record.mutate({ id: recording.id, payload })}
                    onClose={() => setRecording(null)}
                />
            )}
            <InspectionDetailDrawer
                task={detail}
                uploading={upload.isPending}
                onClose={() => setDetail(null)}
                onUpload={(files) => detail && upload.mutate({ id: detail.id, files })}
            />
        </>
    );
}
