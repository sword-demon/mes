import { useQuery } from '@tanstack/react-query';
import {
    Alert,
    App,
    Card,
    Col,
    DatePicker,
    Descriptions,
    Empty,
    Row,
    Space,
    Spin,
    Statistic,
    Tag,
    Typography,
} from 'antd';
import type { Dayjs } from 'dayjs';
import { businessTime } from '@/utils/datetime';
import type { EChartsOption } from 'echarts';
import { useEffect, useState } from 'react';
import {
    EQUIPMENT_STATUSES,
    getEquipmentHistory,
    getEquipmentMonitor,
} from '@/api/equipment';
import type { EquipmentHistorySample, EquipmentMetric, MonitorEquipment } from '@/api/equipment';
import EChart from '@/components/echart';
import { useAuthStore } from '@/stores/auth';
import { ApiRequestError } from '@/utils/api-error';
import { can } from '@/utils/permission';

type MetricKey = 'temperature' | 'speed' | 'pressure' | 'output';

const METRICS: { key: MetricKey; label: string; unit: string; color: string }[] = [
    { key: 'temperature', label: '温度', unit: '℃', color: '#1677ff' },
    { key: 'speed', label: '转速', unit: 'rpm', color: '#52c41a' },
    { key: 'pressure', label: '压力', unit: 'MPa', color: '#faad14' },
    { key: 'output', label: '累计产量', unit: '件', color: '#eb2f96' },
];

const statusColor = Object.fromEntries(
    EQUIPMENT_STATUSES.map(({ value, color }) => [value, color]),
);

function defaultRange(): [Dayjs, Dayjs] {
    return [businessTime().subtract(6, 'hour'), businessTime()];
}

function formatMetric(value: number | null | undefined, unit: string): string {
    return value == null ? '-' : `${value} ${unit}`;
}

function formatRuntime(seconds: number | null): string {
    if (seconds == null) {
        return '-';
    }

    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const rest = seconds % 60;

    return `${hours}:${String(minutes).padStart(2, '0')}:${String(rest).padStart(2, '0')}`;
}

function chartOption(samples: EquipmentHistorySample[], metric: typeof METRICS[number]): EChartsOption {
    return {
        animation: false,
        tooltip: { trigger: 'axis' },
        grid: { left: 56, right: 20, top: 20, bottom: 32 },
        xAxis: { type: 'time', axisLabel: { hideOverlap: true } },
        yAxis: { type: 'value', scale: true, name: metric.unit },
        dataZoom: [{ type: 'inside' }],
        series: [{
            name: metric.label,
            type: 'line',
            showSymbol: false,
            sampling: 'lttb',
            lineStyle: { width: 1.5, color: metric.color },
            itemStyle: { color: metric.color },
            data: samples.map((sample) => [sample.recorded_at, sample[metric.key]]),
        }],
    };
}

function MetricDescriptions({ latest }: { latest: EquipmentMetric | null }) {
    if (latest === null) {
        return <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="暂无采集数据" />;
    }

    return (
        <Descriptions column={2} size="small">
            <Descriptions.Item label="温度">{formatMetric(latest.temperature, '℃')}</Descriptions.Item>
            <Descriptions.Item label="转速">{formatMetric(latest.speed, 'rpm')}</Descriptions.Item>
            <Descriptions.Item label="压力">{formatMetric(latest.pressure, 'MPa')}</Descriptions.Item>
            <Descriptions.Item label="累计产量">{formatMetric(latest.output, '件')}</Descriptions.Item>
            <Descriptions.Item label="运行时间">{formatRuntime(latest.runtime_seconds)}</Descriptions.Item>
            <Descriptions.Item label="采样时间">
                {businessTime(latest.recorded_at).format('MM-DD HH:mm:ss')}
            </Descriptions.Item>
        </Descriptions>
    );
}

function EquipmentCard({
    equipment,
    selected,
    onSelect,
}: {
    equipment: MonitorEquipment;
    selected: boolean;
    onSelect: () => void;
}) {
    return (
        <Card
            hoverable
            role="button"
            tabIndex={0}
            onClick={onSelect}
            onKeyDown={(event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    onSelect();
                }
            }}
            className={selected ? 'ring-2 ring-blue-500' : undefined}
            title={<Space><span>{equipment.code}</span><span>{equipment.name}</span></Space>}
            extra={<Tag color={statusColor[equipment.status]}>{equipment.status_label}</Tag>}
        >
            <Typography.Text type="secondary" className="mb-3 block">
                {equipment.production_line?.name ?? '-'} · {equipment.type}
            </Typography.Text>
            {equipment.latest_data !== undefined ? (
                <MetricDescriptions latest={equipment.latest_data} />
            ) : null}
        </Card>
    );
}

export default function EquipmentMonitor() {
    const { message } = App.useApp();
    const user = useAuthStore((state) => state.user);
    const canMonitor = can(user, 'equipment.monitor.index');
    const canHistory = can(user, 'equipment.history.index');
    const canRead = canMonitor || canHistory;
    const [selectedId, setSelectedId] = useState<number>();
    const [range, setRange] = useState<[Dayjs, Dayjs]>(() => defaultRange());
    const monitor = useQuery({
        queryKey: ['equipment', 'monitor'],
        queryFn: getEquipmentMonitor,
        enabled: canRead,
        refetchInterval: canMonitor ? 5000 : false,
    });
    const equipments = monitor.data?.items ?? [];
    const selected = equipments.find((equipment) => equipment.id === selectedId);
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

    if (!canRead) {
        return <Alert type="warning" showIcon title="没有设备监控或历史数据权限" />;
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
            <Typography.Title level={4} className="!mb-0">设备监控与历史曲线</Typography.Title>

            {monitor.isError ? (
                <Alert
                    type="error"
                    showIcon
                    title={monitor.error instanceof ApiRequestError ? monitor.error.message : '设备监控数据加载失败'}
                />
            ) : null}

            {canMonitor ? (
                <Row gutter={[12, 12]}>
                    {EQUIPMENT_STATUSES.map(({ value, label }) => (
                        <Col xs={12} sm={8} xl={4} key={value}>
                            <Card size="small">
                                <div data-testid={`status-count-${value}`}>
                                    <Statistic title={label} value={monitor.data?.status_counts?.[value] ?? 0} />
                                </div>
                            </Card>
                        </Col>
                    ))}
                </Row>
            ) : null}

            <Spin spinning={monitor.isLoading}>
                {equipments.length === 0 && !monitor.isLoading && !monitor.isError ? (
                    <Empty description="暂无启用设备" />
                ) : (
                    <Row gutter={[12, 12]}>
                        {equipments.map((equipment) => (
                            <Col xs={24} lg={12} xxl={8} key={equipment.id}>
                                <EquipmentCard
                                    equipment={equipment}
                                    selected={selected?.id === equipment.id}
                                    onSelect={() => setSelectedId(equipment.id)}
                                />
                            </Col>
                        ))}
                    </Row>
                )}
            </Spin>

            {canHistory ? (
                <Card>
                    <Space orientation="vertical" size="middle" className="w-full">
                        <Space className="w-full justify-between" wrap>
                            <Space>
                                <Typography.Title level={5} className="!mb-0">历史曲线</Typography.Title>
                                {selected ? (
                                    <Typography.Text type="secondary">
                                        {selected.code} {selected.name}
                                    </Typography.Text>
                                ) : null}
                            </Space>
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
                                            <Typography.Text strong>
                                                {metric.label}（{metric.unit}）
                                            </Typography.Text>
                                            <EChart
                                                className="h-56 w-full"
                                                option={chartOption(samples, metric)}
                                            />
                                        </Col>
                                    ))}
                                </Row>
                            )}
                        </Spin>
                    </Space>
                </Card>
            ) : (
                <Alert type="info" showIcon title="没有设备历史数据权限，仅展示实时状态" />
            )}
        </Space>
    );
}
