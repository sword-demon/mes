import { useQuery } from '@tanstack/react-query';
import { DatePicker, Select, Space, Table, Tag, Typography } from 'antd';
import dayjs from 'dayjs';
import type { Dayjs } from 'dayjs';
import { useState } from 'react';
import { getOperationLogOptions, listOperationLogs } from '@/api/system';
import type { OperationLog } from '@/types/api';

export function formatChangeValue(value: unknown): string {
    if (value === null || value === undefined) {
        return '空';
    }

    if (typeof value === 'boolean') {
        return value ? '是' : '否';
    }

    if (Array.isArray(value)) {
        return value.length > 0 ? value.map(formatChangeValue).join('、') : '空';
    }

    return String(value);
}

export function OperationLogsPanel() {
    const [page, setPage] = useState(1);
    const [userId, setUserId] = useState<number>();
    const [action, setAction] = useState<string>();
    const [dates, setDates] = useState<[Dayjs, Dayjs] | null>(null);

    const options = useQuery({
        queryKey: ['system', 'operation-logs', 'options'],
        queryFn: getOperationLogOptions,
    });
    const logs = useQuery({
        queryKey: ['system', 'operation-logs', page, userId, action, dates?.[0].valueOf(), dates?.[1].valueOf()],
        queryFn: () => listOperationLogs({
            page,
            user_id: userId,
            action,
            start_date: dates?.[0].format('YYYY-MM-DD'),
            end_date: dates?.[1].format('YYYY-MM-DD'),
        }),
    });

    return (
        <>
            <Space className="mb-4 w-full justify-end" wrap>
                <Select
                    allowClear
                    showSearch
                    optionFilterProp="label"
                    placeholder="操作人"
                    className="w-40"
                    options={(options.data?.users ?? []).map((item) => ({
                        label: item.name,
                        value: item.id,
                    }))}
                    onChange={(value) => {
                        setUserId(value);
                        setPage(1);
                    }}
                />
                <Select
                    allowClear
                    showSearch
                    optionFilterProp="label"
                    placeholder="动作"
                    className="w-40"
                    options={(options.data?.actions ?? []).map((item) => ({
                        label: item.action_name,
                        value: item.action,
                    }))}
                    onChange={(value) => {
                        setAction(value);
                        setPage(1);
                    }}
                />
                <DatePicker.RangePicker
                    onChange={(value) => {
                        setDates(value?.[0] && value[1] ? [value[0], value[1]] : null);
                        setPage(1);
                    }}
                />
            </Space>
            <Table<OperationLog>
                rowKey="id"
                loading={logs.isLoading}
                dataSource={logs.data?.items ?? []}
                scroll={{ x: 1100 }}
                locale={{ emptyText: '暂无操作日志' }}
                pagination={{
                    current: page,
                    pageSize: logs.data?.meta.per_page ?? 15,
                    total: logs.data?.meta.total,
                    onChange: setPage,
                }}
                expandable={{
                    rowExpandable: (record) => Object.keys(record.changes ?? {}).length > 0,
                    expandedRowRender: (record) => (
                        <Space orientation="vertical" size={2}>
                            {Object.entries(record.changes ?? {}).map(([field, change]) => (
                                <Typography.Text key={field}>
                                    <Typography.Text type="secondary">{field}：</Typography.Text>
                                    {formatChangeValue(change.before)} → {formatChangeValue(change.after)}
                                </Typography.Text>
                            ))}
                        </Space>
                    ),
                }}
                columns={[
                    {
                        title: '时间',
                        dataIndex: 'created_at',
                        width: 170,
                        render: (value: string) => dayjs(value).format('YYYY-MM-DD HH:mm:ss'),
                    },
                    { title: '操作人', dataIndex: 'user_name', width: 110 },
                    {
                        title: '动作',
                        dataIndex: 'action_name',
                        width: 130,
                        render: (value: string) => <Tag color="blue">{value}</Tag>,
                    },
                    {
                        title: '对象',
                        width: 220,
                        render: (_, record) => `${record.object_type} ${record.object_name}`,
                    },
                    { title: '摘要', dataIndex: 'summary' },
                    { title: 'IP', dataIndex: 'ip', width: 140 },
                ]}
            />
        </>
    );
}
