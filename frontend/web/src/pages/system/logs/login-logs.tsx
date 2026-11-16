import { useQuery } from '@tanstack/react-query';
import { DatePicker, Input, Select, Space, Table, Tag } from 'antd';
import dayjs from 'dayjs';
import type { Dayjs } from 'dayjs';
import { useState } from 'react';
import { listLoginLogs } from '@/api/system';
import type { LoginLog } from '@/types/api';

const RESULT_OPTIONS = [
    { label: '成功', value: '1' },
    { label: '失败', value: '0' },
];

export function LoginLogsPanel() {
    const [page, setPage] = useState(1);
    const [account, setAccount] = useState('');
    const [success, setSuccess] = useState<string>();
    const [dates, setDates] = useState<[Dayjs, Dayjs] | null>(null);

    const logs = useQuery({
        queryKey: ['system', 'login-logs', page, account, success, dates?.[0].valueOf(), dates?.[1].valueOf()],
        queryFn: () => listLoginLogs({
            page,
            account: account || undefined,
            success: success === undefined ? undefined : success === '1',
            start_date: dates?.[0].format('YYYY-MM-DD'),
            end_date: dates?.[1].format('YYYY-MM-DD'),
        }),
    });

    return (
        <>
            <Space className="mb-4 w-full justify-end" wrap>
                <Input.Search
                    allowClear
                    placeholder="账号"
                    className="w-64"
                    onChange={(event) => {
                        if (event.target.value === '') {
                            setAccount('');
                            setPage(1);
                        }
                    }}
                    onSearch={(value) => {
                        setAccount(value);
                        setPage(1);
                    }}
                />
                <Select
                    allowClear
                    placeholder="结果"
                    className="w-32"
                    options={RESULT_OPTIONS}
                    onChange={(value) => {
                        setSuccess(value);
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
            <Table<LoginLog>
                rowKey="id"
                loading={logs.isLoading}
                dataSource={logs.data?.items ?? []}
                scroll={{ x: 900 }}
                locale={{ emptyText: '暂无登录日志' }}
                pagination={{
                    current: page,
                    pageSize: logs.data?.meta.per_page ?? 15,
                    total: logs.data?.meta.total,
                    onChange: setPage,
                }}
                columns={[
                    {
                        title: '时间',
                        dataIndex: 'created_at',
                        width: 170,
                        render: (value: string) => dayjs(value).format('YYYY-MM-DD HH:mm:ss'),
                    },
                    { title: '账号', dataIndex: 'account' },
                    {
                        title: '结果',
                        dataIndex: 'success',
                        width: 90,
                        render: (value: boolean) => (
                            <Tag color={value ? 'green' : 'red'}>{value ? '成功' : '失败'}</Tag>
                        ),
                    },
                    { title: '说明', dataIndex: 'message', width: 160 },
                    { title: 'IP', dataIndex: 'ip', width: 140 },
                ]}
            />
        </>
    );
}
