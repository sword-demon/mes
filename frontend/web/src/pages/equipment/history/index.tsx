import { useQuery } from '@tanstack/react-query';
import {
    Alert,
    App,
    Card,
    Col,
    DatePicker,
    Empty,
    Row,
    Select,
    Space,
    Spin,
    Table,
    Typography,
} from 'antd';
import type { Dayjs } from 'dayjs';
import { useEffect, useState } from 'react';
import { getEquipmentHistory, getEquipmentMonitor } from '@/api/equipment';
import type { EquipmentHistorySample } from '@/api/equipment';
import EChart from '@/components/echart';
import { businessTime } from '@/utils/datetime';
import { useAuthStore } from '@/stores/auth';
import { ApiRequestError } from '@/utils/api-error';
import { can } from '@/utils/permission';
import { METRICS, formatMetric, metricChartOption } from '../metrics';

function defaultRange(): [Dayjs, Dayjs] {
    return [businessTime().subtract(6, 'hour'), businessTime()];
}

export default function EquipmentHistory() {
    const { message } = App.useApp();
    const user = useAuthStore((state) => state.user);
    const canHistory = can(user, 'equipment.history.index');
    const [selectedId, setSelectedId] = useState<number>();
    const [range, setRange] = useState<[Dayjs, Dayjs]>(() => defaultRange());

    const monitor = useQuery({
        queryKey: ['equipment', 'monitor'],
        queryFn: getEquipmentMonitor,
        enabled: canHistory,
    });
    const equipments = monitor.data?.items ?? [];

    const history = useQuery({
        queryKey: ['equipment', 'history', selectedId, range[0].toISOString(), range[1].toISOString()],
        queryFn: () => getEquipmentHistory(selectedId!, {
            start_at: range[0].toISOString(),
            end_at: range[1].toISOString(),
        }),
        enabled: canHistory && selectedId !== undefined,
    });

    useEffect(() => {
        if (selectedId === undefined && equipments.length > 0) {
            setSelectedId(equipments[0].id);
        }
    }, [equipments, selectedId]);

    if (!canHistory) {
        return <Alert type="warning" showIcon title="没有设备历史数据权限" />;
    }

    function changeRange(value: [Dayjs | null, Dayjs | null] | null) {
        if (!value?.[0] || !value[1]) {
            return;
        }

        const maxHistorySeconds = monitor.data?.max_history_seconds;

        if (maxHistorySeconds !== undefined && value[1].diff(value[0], 'second') > maxHistorySeconds) {
            message.warning(`查询时间区间不能超过 ${Math.round(maxHistorySeconds / 86400)} 天`);
            return;
        }

        setRange([value[0], value[1]]);
    }

    const samples = history.data?.items ?? [];
    const now = businessTime();

    return (
        <Space orientation="vertical" size="middle" className="w-full">
            <Typography.Title level={4} className="!mb-0">设备历史数据</Typography.Title>

            {monitor.isError ? (
                <Alert
                    type="error"
                    showIcon
                    title={monitor.error instanceof ApiRequestError ? monitor.error.message : '设备列表加载失败'}
                />
            ) : null}

            <Space className="w-full justify-between" wrap>
                <Select
                    className="w-72"
                    loading={monitor.isLoading}
                    placeholder="选择设备"
                    value={selectedId}
                    onChange={setSelectedId}
                    options={equipments.map((equipment) => ({
                        value: equipment.id,
                        label: `${equipment.code} ${equipment.name}`,
                    }))}
                />
                <DatePicker.RangePicker
                    showTime
                    allowClear={false}
                    value={range}
                    onChange={changeRange}
                    presets={[
                        { label: '最近 1 小时', value: [now.subtract(1, 'hour'), now] },
                        { label: '最近 6 小时', value: [now.subtract(6, 'hour'), now] },
                        { label: '最近 24 小时', value: [now.subtract(24, 'hour'), now] },
                        { label: '最近 7 天', value: [now.subtract(7, 'day'), now] },
                        { label: '最近 30 天', value: [now.subtract(30, 'day'), now] },
                    ]}
                />
            </Space>

            <Spin spinning={history.isLoading}>
                {history.isError ? (
                    <Alert
                        type="error"
                        showIcon
                        title={history.error instanceof ApiRequestError ? history.error.message : '设备历史数据加载失败'}
                    />
                ) : selectedId === undefined || (!history.isLoading && samples.length === 0) ? (
                    <Empty description="暂无历史数据" />
                ) : (
                    <Row gutter={[16, 16]}>
                        {METRICS.map((metric) => (
                            <Col xs={24} xl={12} key={metric.key}>
                                <Card
                                    title={
                                        <Space>
                                            <Typography.Text strong>
                                                {metric.label}（{metric.unit}）
                                            </Typography.Text>
                                            {history.data?.equipment ? (
                                                <Typography.Text type="secondary">
                                                    {history.data.equipment.code} {history.data.equipment.name}
                                                </Typography.Text>
                                            ) : null}
                                        </Space>
                                    }
                                >
                                    <EChart
                                        className="h-56 w-full"
                                        option={metricChartOption(samples, metric)}
                                    />
                                </Card>
                            </Col>
                        ))}
                    </Row>
                )}
            </Spin>

            <Card title="采样明细">
                <Table<EquipmentHistorySample>
                    rowKey="recorded_at"
                    size="small"
                    loading={history.isLoading}
                    dataSource={samples}
                    pagination={{ pageSize: 10, showSizeChanger: false }}
                    columns={[
                        {
                            title: '采样时间',
                            dataIndex: 'recorded_at',
                            render: (value: string) => businessTime(value).format('MM-DD HH:mm:ss'),
                        },
                        {
                            title: '温度（℃）',
                            dataIndex: 'temperature',
                            render: (value: number) => formatMetric(value, '℃'),
                        },
                        {
                            title: '转速（rpm）',
                            dataIndex: 'speed',
                            render: (value: number) => formatMetric(value, 'rpm'),
                        },
                        {
                            title: '压力（MPa）',
                            dataIndex: 'pressure',
                            render: (value: number) => formatMetric(value, 'MPa'),
                        },
                        {
                            title: '累计产量（件）',
                            dataIndex: 'output',
                            render: (value: number) => formatMetric(value, '件'),
                        },
                    ]}
                />
            </Card>
        </Space>
    );
}
