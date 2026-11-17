import { useQuery } from '@tanstack/react-query';
import {
    CheckCircleOutlined,
    ClockCircleOutlined,
    DesktopOutlined,
    ExclamationCircleOutlined,
    FileTextOutlined,
    ThunderboltOutlined,
} from '@ant-design/icons';
import { Alert, Col, Row, Space, Typography } from 'antd';
import { businessTime } from '@/utils/datetime';
import {
    getDashboardEquipmentStatus,
    getDashboardLineComparison,
    getDashboardQuality,
    getDashboardToday,
    getDashboardTrend,
    getLatestAlarms,
    getLatestExceptions,
    getLatestOrders,
    getLatestReports,
} from '@/api/dashboard';
import type {
    DashboardEquipmentStatus,
    DashboardQuality,
    DashboardToday,
} from '@/api/dashboard';
import { useAuthStore } from '@/stores/auth';
import { can } from '@/utils/permission';
import {
    defectReasonOption,
    equipmentStatusOption,
    lineComparisonOption,
    qualifiedRateOption,
    trendOption,
} from './chart-options';
import { ChartCard, LatestList, StatisticsCard } from './components';
import {
    alarmColumns,
    exceptionColumns,
    orderColumns,
    reportColumns,
} from './table-columns';

export default function Dashboard() {
    const user = useAuthStore((state) => state.user);
    const canOverview = can(user, 'dashboard.overview');
    const canTrend = can(user, 'dashboard.trend');
    const canQuality = can(user, 'dashboard.quality');
    const canEquipment = can(user, 'dashboard.equipment');
    const today = useQuery({
        queryKey: ['dashboard', 'today'],
        queryFn: getDashboardToday,
        enabled: canOverview,
    });
    const trend = useQuery({
        queryKey: ['dashboard', 'production-trend'],
        queryFn: getDashboardTrend,
        enabled: canTrend,
    });
    const lines = useQuery({
        queryKey: ['dashboard', 'production-lines'],
        queryFn: getDashboardLineComparison,
        enabled: canTrend,
    });
    const quality = useQuery({
        queryKey: ['dashboard', 'quality'],
        queryFn: getDashboardQuality,
        enabled: canQuality,
    });
    const equipmentStatus = useQuery({
        queryKey: ['dashboard', 'equipment-status'],
        queryFn: getDashboardEquipmentStatus,
        enabled: canEquipment,
    });
    const latestOrders = useQuery({
        queryKey: ['dashboard', 'latest-orders'],
        queryFn: getLatestOrders,
        enabled: canOverview,
    });
    const latestReports = useQuery({
        queryKey: ['dashboard', 'latest-reports'],
        queryFn: getLatestReports,
        enabled: canOverview,
    });
    const latestExceptions = useQuery({
        queryKey: ['dashboard', 'latest-exceptions'],
        queryFn: getLatestExceptions,
        enabled: canOverview,
    });
    const latestAlarms = useQuery({
        queryKey: ['dashboard', 'latest-alarms'],
        queryFn: getLatestAlarms,
        enabled: canOverview,
    });

    const statistics: DashboardToday = today.data ?? {
        date: '',
        order_count: 0,
        planned_quantity: '0',
        produced_quantity: '0',
        qualified_quantity: '0',
        defective_quantity: '0',
        running_equipment_count: 0,
    };
    const qualityData: DashboardQuality = quality.data ?? {
        qualified_quantity: '0',
        defective_quantity: '0',
        total_quantity: '0',
        qualified_rate: '0',
        defect_reasons: [],
    };
    const equipmentStatusData: DashboardEquipmentStatus = equipmentStatus.data ?? { items: [] };
    const equipmentTotal = equipmentStatusData.items
        .reduce((total, item) => total + item.count, 0);
    const trendEmpty = (trend.data?.items.length ?? 0) === 0
        || (trend.data?.items.every((item) => Number(item.quantity) === 0) ?? false);
    const linesEmpty = (lines.data?.items.length ?? 0) === 0
        || (lines.data?.items.every((item) => Number(item.quantity) === 0) ?? false);
    const statValue = (value: number | string) => today.data ? value : '-';
    const errorMessage = (error: unknown): string =>
        error instanceof Error ? error.message : '加载失败';

    return (
        <>
            <Space className="mb-4 w-full justify-between" wrap>
                <div>
                    <Typography.Title level={4} className="mb-0!">生产数据总览</Typography.Title>
                    <Typography.Text type="secondary">
                        {statistics.date
                            ? `${businessTime(statistics.date).format('YYYY年MM月DD日')} 生产概况与最新动态`
                            : '今日生产概况与最新动态'}
                    </Typography.Text>
                </div>
            </Space>

            {today.isError ? (
                <Alert
                    className="mb-4"
                    type="error"
                    showIcon
                    title={`今日数据加载失败：${errorMessage(today.error)}`}
                />
            ) : null}

            <Row gutter={[16, 16]} className="mb-4">
                <Col xs={12} sm={8} xl={4}>
                    <StatisticsCard
                        title="今日订单"
                        value={statValue(statistics.order_count)}
                        icon={<FileTextOutlined />}
                        color="#1677ff"
                    />
                </Col>
                <Col xs={12} sm={8} xl={4}>
                    <StatisticsCard
                        title="计划数量"
                        value={statValue(Number(statistics.planned_quantity))}
                        icon={<ClockCircleOutlined />}
                        color="#722ed1"
                    />
                </Col>
                <Col xs={12} sm={8} xl={4}>
                    <StatisticsCard
                        title="实际产量"
                        value={statValue(Number(statistics.produced_quantity))}
                        icon={<ThunderboltOutlined />}
                        color="#1677ff"
                    />
                </Col>
                <Col xs={12} sm={8} xl={4}>
                    <StatisticsCard
                        title="合格数量"
                        value={statValue(Number(statistics.qualified_quantity))}
                        icon={<CheckCircleOutlined />}
                        color="#52c41a"
                    />
                </Col>
                <Col xs={12} sm={8} xl={4}>
                    <StatisticsCard
                        title="不良数量"
                        value={statValue(Number(statistics.defective_quantity))}
                        icon={<ExclamationCircleOutlined />}
                        color="#ff4d4f"
                    />
                </Col>
                <Col xs={12} sm={8} xl={4}>
                    <StatisticsCard
                        title="运行设备"
                        value={statValue(statistics.running_equipment_count)}
                        icon={<DesktopOutlined />}
                        color="#13c2c2"
                    />
                </Col>
            </Row>

            <Row gutter={[16, 16]} className="mb-4">
                <Col xs={24} xl={12}>
                    <ChartCard
                        title="近七天生产趋势"
                        loading={trend.isLoading}
                        error={trend.error}
                        empty={trendEmpty}
                        emptyText={canTrend ? '暂无生产趋势数据' : '无生产趋势查看权限'}
                        option={trendOption(trend.data ?? { days: 7, items: [] })}
                    />
                </Col>
                <Col xs={24} xl={12}>
                    <ChartCard
                        title="各产线产量对比"
                        loading={lines.isLoading}
                        error={lines.error}
                        empty={linesEmpty}
                        emptyText={canTrend ? '暂无产线对比数据' : '无生产趋势查看权限'}
                        option={lineComparisonOption(lines.data ?? { items: [] })}
                    />
                </Col>
                <Col xs={24} xl={8}>
                    <ChartCard
                        title="产品合格率"
                        loading={quality.isLoading}
                        error={quality.error}
                        empty={Number(qualityData.total_quantity) === 0}
                        emptyText={canQuality ? '暂无合格率数据' : '无质量统计查看权限'}
                        option={qualifiedRateOption(qualityData)}
                    />
                </Col>
                <Col xs={24} xl={8}>
                    <ChartCard
                        title="设备状态分布"
                        loading={equipmentStatus.isLoading}
                        error={equipmentStatus.error}
                        empty={equipmentTotal === 0}
                        emptyText={canEquipment ? '暂无设备状态数据' : '无设备状态查看权限'}
                        option={equipmentStatusOption(equipmentStatusData)}
                    />
                </Col>
                <Col xs={24} xl={8}>
                    <ChartCard
                        title="不良原因分布"
                        loading={quality.isLoading}
                        error={quality.error}
                        empty={qualityData.defect_reasons.length === 0}
                        emptyText={canQuality ? '暂无不良原因数据' : '无质量统计查看权限'}
                        option={defectReasonOption(qualityData)}
                    />
                </Col>
            </Row>

            <Row gutter={[16, 16]}>
                <Col xs={24} xl={12}>
                    <LatestList
                        title="最新订单"
                        path="/production/orders"
                        query={latestOrders}
                        columns={orderColumns}
                        emptyText="暂无最新订单"
                    />
                </Col>
                <Col xs={24} xl={12}>
                    <LatestList
                        title="最新报工"
                        path="/production/reports"
                        query={latestReports}
                        columns={reportColumns}
                        emptyText="暂无最新报工"
                    />
                </Col>
                <Col xs={24} xl={12}>
                    <LatestList
                        title="最新异常"
                        path="/execution/exceptions"
                        query={latestExceptions}
                        columns={exceptionColumns}
                        emptyText="暂无最新异常"
                    />
                </Col>
                <Col xs={24} xl={12}>
                    <LatestList
                        title="最新告警"
                        path="/equipment/alarms"
                        query={latestAlarms}
                        columns={alarmColumns}
                        emptyText="暂无最新告警"
                    />
                </Col>
            </Row>
        </>
    );
}
