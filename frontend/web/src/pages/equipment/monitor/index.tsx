import { useQuery } from '@tanstack/react-query';
import {
    Alert,
    Card,
    Col,
    Descriptions,
    Empty,
    Row,
    Space,
    Spin,
    Statistic,
    Tag,
    Typography,
} from 'antd';
import { EQUIPMENT_STATUSES, getEquipmentMonitor } from '@/api/equipment';
import type { EquipmentMetric, MonitorEquipment } from '@/api/equipment';
import { businessTime } from '@/utils/datetime';
import { useAuthStore } from '@/stores/auth';
import { ApiRequestError } from '@/utils/api-error';
import { can } from '@/utils/permission';
import { formatMetric, formatRuntime } from '../metrics';

const statusColor = Object.fromEntries(
    EQUIPMENT_STATUSES.map(({ value, color }) => [value, color]),
);

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

function EquipmentCard({ equipment }: { equipment: MonitorEquipment }) {
    return (
        <Card
            className="h-full"
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
    const user = useAuthStore((state) => state.user);
    const canMonitor = can(user, 'equipment.monitor.index');
    const monitor = useQuery({
        queryKey: ['equipment', 'monitor'],
        queryFn: getEquipmentMonitor,
        enabled: canMonitor,
        refetchInterval: canMonitor ? 5000 : false,
    });
    const equipments = monitor.data?.items ?? [];

    if (!canMonitor) {
        return <Alert type="warning" showIcon title="没有设备监控权限" />;
    }

    return (
        <Space orientation="vertical" size="middle" className="w-full">
            <Typography.Title level={4} className="!mb-0">设备监控</Typography.Title>

            {monitor.isError ? (
                <Alert
                    type="error"
                    showIcon
                    title={monitor.error instanceof ApiRequestError ? monitor.error.message : '设备监控数据加载失败'}
                />
            ) : null}

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

            <Spin spinning={monitor.isLoading}>
                {equipments.length === 0 && !monitor.isLoading && !monitor.isError ? (
                    <Empty description="暂无启用设备" />
                ) : (
                    <Row gutter={[12, 12]}>
                        {equipments.map((equipment) => (
                            <Col xs={24} lg={12} xxl={8} key={equipment.id}>
                                <EquipmentCard equipment={equipment} />
                            </Col>
                        ))}
                    </Row>
                )}
            </Spin>
        </Space>
    );
}
