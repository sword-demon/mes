import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { App, Button, Form, Input, Modal, Select, Space, Table, Tag, Tooltip, Typography } from 'antd';
import { useState } from 'react';
import { listEmployees } from '@/api/basic-data';
import { getEquipmentOptions } from '@/api/equipment';
import {
    PROCESS_RECORD_STATUSES,
    completeProcessRecord,
    listProcessRecords,
    receiveProcessRecord,
    reportProcessRecordException,
    reworkProcessRecord,
    startProcessRecord,
    submitProcessRecord,
} from '@/api/production';
import type { ProcessRecord, ProcessRecordStartPayload, ProcessRecordStatus } from '@/api/production';
import Can from '@/components/can';
import { useAuthStore } from '@/stores/auth';
import { businessTime } from '@/utils/datetime';
import { ApiRequestError } from '@/utils/api-error';
import { can } from '@/utils/permission';

type ReasonAction = 'exception' | 'rework';

const statusColor = Object.fromEntries(
    PROCESS_RECORD_STATUSES.map(({ value, color }) => [value, color]),
);

function formatTime(value: string | null | undefined): string {
    return value ? businessTime(value).format('MM-DD HH:mm') : '-';
}

export default function ProcessFlows() {
    const { message } = App.useApp();
    const queryClient = useQueryClient();
    const user = useAuthStore((state) => state.user);
    const canRead = can(user, 'production.flows.index');
    const [page, setPage] = useState(1);
    const [status, setStatus] = useState<ProcessRecordStatus>();
    const [sn, setSn] = useState('');
    const [starting, setStarting] = useState<ProcessRecord | null>(null);
    const [reasoning, setReasoning] = useState<{ action: ReasonAction; record: ProcessRecord } | null>(null);
    const [startForm] = Form.useForm<ProcessRecordStartPayload>();
    const [reasonForm] = Form.useForm<{ reason: string }>();

    const records = useQuery({
        queryKey: ['production', 'process-records', page, status, sn],
        queryFn: () => listProcessRecords({ page, status, sn: sn || undefined }),
        enabled: canRead,
    });

    // The start form falls back to the task owner when nothing is picked, so
    // these lookups are a convenience instead of a hard requirement.
    const employees = useQuery({
        queryKey: ['basic.employees', 'options'],
        queryFn: () => listEmployees({ status: true, per_page: 100 }),
        enabled: starting !== null,
    });
    const equipment = useQuery({
        queryKey: ['equipments', 'options'],
        queryFn: getEquipmentOptions,
        enabled: starting !== null,
    });

    const showError = (error: unknown) =>
        message.error(error instanceof ApiRequestError ? error.message : '操作失败');
    const refresh = () => queryClient.invalidateQueries({ queryKey: ['production', 'process-records'] });

    const start = useMutation({
        mutationFn: ({ record, payload }: { record: ProcessRecord; payload: ProcessRecordStartPayload }) =>
            startProcessRecord(record.id, payload),
        onSuccess: () => {
            refresh();
            message.success('工序已开始');
            setStarting(null);
        },
        onError: showError,
    });

    const advance = useMutation({
        mutationFn: ({ record, action }: { record: ProcessRecord; action: 'complete' | 'submit' | 'receive' }) => {
            if (action === 'complete') {
                return completeProcessRecord(record.id);
            }

            if (action === 'submit') {
                return submitProcessRecord(record.id);
            }

            return receiveProcessRecord(record.id);
        },
        onSuccess: () => {
            refresh();
            message.success('已更新工序状态');
        },
        onError: showError,
    });

    const reason = useMutation({
        mutationFn: ({ record, action, text }: { record: ProcessRecord; action: ReasonAction; text: string }) =>
            action === 'exception'
                ? reportProcessRecordException(record.id, text)
                : reworkProcessRecord(record.id, text),
        onSuccess: () => {
            refresh();
            message.success('已提交');
            setReasoning(null);
        },
        onError: showError,
    });

    if (!canRead) {
        return <Typography.Text type="secondary">没有工序流转权限</Typography.Text>;
    }

    const actions = (record: ProcessRecord) => (
        <Space wrap>
            {(record.status === 'pending' || record.status === 'reworking') && (
                <Can code="button.production.flows.start">
                    <Button
                        type="link"
                        onClick={() => {
                            startForm.resetFields();
                            setStarting(record);
                        }}
                    >
                        开始加工
                    </Button>
                </Can>
            )}
            {record.status === 'processing' && (
                <Can code="button.production.flows.complete">
                    <Button type="link" onClick={() => advance.mutate({ record, action: 'complete' })}>
                        完成加工
                    </Button>
                </Can>
            )}
            {record.status === 'inspected' && (
                <Can code="button.production.flows.submit">
                    <Button type="link" onClick={() => advance.mutate({ record, action: 'submit' })}>
                        提交下道
                    </Button>
                </Can>
            )}
            {record.status === 'pending_transfer' && (
                <Can code="button.production.flows.receive">
                    <Button type="link" onClick={() => advance.mutate({ record, action: 'receive' })}>
                        接收流转
                    </Button>
                </Can>
            )}
            {['processing', 'inspecting', 'inspected', 'pending_transfer', 'reworking'].includes(record.status) && (
                <Can code="button.production.flows.exception">
                    <Button type="link" onClick={() => setReasoning({ action: 'exception', record })}>
                        上报异常
                    </Button>
                </Can>
            )}
            {['processing', 'inspecting', 'inspected', 'pending_transfer'].includes(record.status) && (
                <Can code="button.production.flows.rework">
                    <Button type="link" onClick={() => setReasoning({ action: 'rework', record })}>
                        返工
                    </Button>
                </Can>
            )}
        </Space>
    );

    return (
        <>
            <Space className="mb-4 w-full justify-between" wrap>
                <Typography.Title level={4} className="mb-0!">
                    工序流转
                </Typography.Title>
                <Space wrap>
                    <Select
                        allowClear
                        className="w-36"
                        placeholder="状态"
                        value={status}
                        onChange={(value?: ProcessRecordStatus) => {
                            setStatus(value);
                            setPage(1);
                        }}
                        options={PROCESS_RECORD_STATUSES.map((option) => ({
                            value: option.value,
                            label: option.label,
                        }))}
                    />
                    <Input.Search
                        allowClear
                        className="w-56"
                        placeholder="SN"
                        onSearch={(value) => {
                            setSn(value);
                            setPage(1);
                        }}
                    />
                </Space>
            </Space>
            <Table<ProcessRecord>
                rowKey="id"
                loading={records.isLoading}
                dataSource={records.data?.items}
                pagination={{
                    current: page,
                    pageSize: records.data?.meta.per_page,
                    total: records.data?.meta.total,
                    onChange: setPage,
                }}
                columns={[
                    {
                        title: '工序',
                        key: 'process',
                        render: (_, record) => (
                            <Space direction="vertical" size={0}>
                                <Typography.Text strong>
                                    {record.sequence}. {record.route_operation?.process.name ?? '-'}
                                </Typography.Text>
                                <Typography.Text type="secondary">
                                    {record.route_operation?.process.code ?? '-'}
                                </Typography.Text>
                            </Space>
                        ),
                    },
                    {
                        title: '生产任务',
                        key: 'task',
                        render: (_, record) => (
                            <Space direction="vertical" size={0}>
                                <Typography.Text>{record.production_task?.code ?? '-'}</Typography.Text>
                                <Typography.Text type="secondary">
                                    {record.production_order?.code ?? '-'}
                                </Typography.Text>
                            </Space>
                        ),
                    },
                    {
                        title: 'SN',
                        dataIndex: 'sn',
                        render: (value: string | null) => value ?? '-',
                    },
                    {
                        title: '执行人',
                        key: 'operator',
                        render: (_, record) => (
                            <Space direction="vertical" size={0}>
                                <Typography.Text>{record.employee?.name ?? '-'}</Typography.Text>
                                <Typography.Text type="secondary">
                                    {record.equipment?.name ?? record.team?.name ?? '-'}
                                </Typography.Text>
                            </Space>
                        ),
                    },
                    {
                        title: '状态',
                        dataIndex: 'status',
                        render: (value: ProcessRecordStatus, record) => (
                            <Space direction="vertical" size={0}>
                                <Tag color={statusColor[value]}>{record.status_label}</Tag>
                                {record.exception_reason ? (
                                    <Tooltip title={`异常：${record.exception_reason}`}>
                                        <Typography.Text type="secondary" className="text-xs">
                                            有异常记录
                                        </Typography.Text>
                                    </Tooltip>
                                ) : null}
                                {record.rework_count > 0 ? (
                                    <Typography.Text type="secondary" className="text-xs">
                                        返工 {record.rework_count} 次
                                    </Typography.Text>
                                ) : null}
                            </Space>
                        ),
                    },
                    {
                        title: '时间',
                        key: 'times',
                        render: (_, record) => (
                            <Space direction="vertical" size={0}>
                                <Typography.Text type="secondary">
                                    开始 {formatTime(record.started_at)}
                                </Typography.Text>
                                <Typography.Text type="secondary">
                                    完成 {formatTime(record.completed_at)}
                                </Typography.Text>
                            </Space>
                        ),
                    },
                    {
                        title: '操作',
                        key: 'actions',
                        render: (_, record) => actions(record),
                    },
                ]}
            />
            <Modal
                open={starting !== null}
                title={`开始加工${starting ? ` - ${starting.sequence}. ${starting.route_operation?.process.name ?? ''}` : ''}`}
                confirmLoading={start.isPending}
                onCancel={() => setStarting(null)}
                onOk={() => startForm.submit()}
            >
                <Form
                    key={starting?.id}
                    form={startForm}
                    layout="vertical"
                    onFinish={(values) => {
                        if (starting) {
                            start.mutate({ record: starting, payload: values });
                        }
                    }}
                >
                    <Form.Item name="employee_id" label="操作人员">
                        <Select
                            allowClear
                            showSearch
                            optionFilterProp="label"
                            placeholder="留空则使用任务负责人"
                            loading={employees.isLoading}
                            options={(employees.data?.items ?? []).map((employee) => ({
                                value: employee.id,
                                label: `${employee.employee_no} ${employee.name}`,
                            }))}
                        />
                    </Form.Item>
                    <Form.Item name="equipment_id" label="设备">
                        <Select
                            allowClear
                            showSearch
                            optionFilterProp="label"
                            placeholder="留空则沿用原记录"
                            loading={equipment.isLoading}
                            options={(equipment.data?.equipments ?? []).map((item) => ({
                                value: item.id,
                                label: `${item.code} ${item.name}`,
                            }))}
                        />
                    </Form.Item>
                    <Form.Item name="sn" label="SN">
                        <Input placeholder="留空则沿用原记录" />
                    </Form.Item>
                </Form>
            </Modal>
            <Modal
                open={reasoning !== null}
                title={reasoning?.action === 'rework' ? '工序返工' : '上报工序异常'}
                confirmLoading={reason.isPending}
                onCancel={() => setReasoning(null)}
                onOk={() => reasonForm.submit()}
            >
                <Form
                    key={reasoning?.record.id}
                    form={reasonForm}
                    layout="vertical"
                    onFinish={({ reason: text }) => {
                        if (reasoning) {
                            reason.mutate({ record: reasoning.record, action: reasoning.action, text });
                        }
                    }}
                >
                    <Form.Item
                        name="reason"
                        label="原因"
                        rules={[{ required: true, message: '请填写原因' }]}
                    >
                        <Input.TextArea rows={3} maxLength={255} showCount />
                    </Form.Item>
                </Form>
            </Modal>
        </>
    );
}
