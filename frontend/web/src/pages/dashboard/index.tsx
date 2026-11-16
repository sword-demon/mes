import { useQuery } from '@tanstack/react-query';
import type { UseQueryResult } from '@tanstack/react-query';
import {
    CheckCircleOutlined,
    ClockCircleOutlined,
    DesktopOutlined,
    ExclamationCircleOutlined,
    FileTextOutlined,
    ThunderboltOutlined,
} from '@ant-design/icons';
import {
    Alert,
    Button,
    Card,
    Col,
    Empty,
    Row,
    Space,
    Spin,
    Statistic,
    Table,
    Tag,
    Typography,
} from 'antd';
import type { TableProps } from 'antd';
import dayjs from 'dayjs';
import type { EChartsOption } from 'echarts';
import type { ReactNode } from 'react';
import { useNavigate } from 'react-router';
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
    DashboardEquipmentStatusItem,
    DashboardLineComparison,
    DashboardList,
    DashboardQuality,
    DashboardToday,
    DashboardTrend,
} from '@/api/dashboard';
import {
    EQUIPMENT_ALARM_LEVELS,
    EQUIPMENT_ALARM_STATUSES,
} from '@/api/equipment';
import type { EquipmentAlarm, EquipmentStatus } from '@/api/equipment';
import {
    PRODUCTION_EXCEPTION_STATUSES,
    PRODUCTION_ORDER_STATUSES,
} from '@/api/production';
import type {
    ProductionException,
    ProductionOrder,
    ProductionReport,
} from '@/api/production';
import EChart from '@/components/echart';
import { useAuthStore } from '@/stores/auth';
import { can, findMenu } from '@/utils/permission';

const equipmentStatusColors: Record<EquipmentStatus, string> = {
    running: '#52c41a',
    idle: '#1677ff',
    fault: '#ff4d4f',
    repair: '#faad14',
    offline: '#8c8c8c',
};

const orderStatusColors = Object.fromEntries(
    PRODUCTION_ORDER_STATUSES.map(({ value, color }) => [value, color]),
);
const exceptionStatusColors = Object.fromEntries(
    PRODUCTION_EXCEPTION_STATUSES.map(({ value, color }) => [value, color]),
);
const alarmLevelColors = Object.fromEntries(
    EQUIPMENT_ALARM_LEVELS.map(({ value, color }) => [value, color]),
);
const alarmStatusColors = Object.fromEntries(
    EQUIPMENT_ALARM_STATUSES.map(({ value, color }) => [value, color]),
);

const quantity = (value: string | number): string =>
    Number(value).toLocaleString('zh-CN', { maximumFractionDigits: 3 });

const formatTime = (value: string | null | undefined): string =>
    value ? dayjs(value).format('MM-DD HH:mm') : '-';

const errorMessage = (error: unknown): string =>
    error instanceof Error ? error.message : '加载失败';

function ChartCard({
    title,
    loading,
    error,
    empty,
    emptyText,
    option,
}: {
    title: string;
    loading: boolean;
    error: unknown;
    empty: boolean;
    emptyText: string;
    option: EChartsOption;
}) {
    return (
        <Card title={title} className="h-full">
            {loading ? (
                <div className="flex h-64 items-center justify-center">
                    <Spin />
                </div>
            ) : error ? (
                <Alert type="error" showIcon title={errorMessage(error)} />
            ) : empty ? (
                <Empty
                    className="my-16"
                    image={Empty.PRESENTED_IMAGE_SIMPLE}
                    description={emptyText}
                />
            ) : (
                <EChart className="h-64 w-full" option={option} />
            )}
        </Card>
    );
}

function LatestList<T extends { id: number }>({
    title,
    path,
    query,
    columns,
    emptyText,
}: {
    title: string;
    path: string;
    query: UseQueryResult<DashboardList<T>>;
    columns: TableProps<T>['columns'];
    emptyText: string;
}) {
    const navigate = useNavigate();
    const user = useAuthStore((state) => state.user);
    const accessible = user !== null && findMenu(user.menus, path) !== null;

    return (
        <Card
            title={title}
            className="h-full"
            extra={accessible ? (
                <Button type="link" className="px-0" onClick={() => navigate(path)}>
                    查看全部
                </Button>
            ) : undefined}
        >
            {query.isError ? (
                <Alert type="error" showIcon title={errorMessage(query.error)} />
            ) : (
                <Table<T>
                    rowKey="id"
                    size="small"
                    loading={query.isLoading}
                    columns={columns}
                    dataSource={query.data?.items ?? []}
                    pagination={false}
                    scroll={{ x: 640 }}
                    locale={{ emptyText }}
                    onRow={accessible ? () => ({
                        onClick: () => navigate(path),
                        style: { cursor: 'pointer' },
                    }) : undefined}
                />
            )}
        </Card>
    );
}

function StatisticsCard({
    title,
    value,
    icon,
    color,
}: {
    title: string;
    value: number | string;
    icon: ReactNode;
    color: string;
}) {
    return (
        <Card>
            <Statistic
                title={title}
                value={value}
                prefix={<span style={{ color }}>{icon}</span>}
            />
        </Card>
    );
}

function trendOption(data: DashboardTrend): EChartsOption {
    return {
        tooltip: { trigger: 'axis' },
        grid: { left: 48, right: 16, top: 24, bottom: 32 },
        xAxis: {
            type: 'category',
            boundaryGap: false,
            data: data.items.map((item) => dayjs(item.date).format('MM-DD')),
        },
        yAxis: { type: 'value', name: '数量' },
        series: [{
            name: '生产数量',
            type: 'line',
            smooth: true,
            symbol: 'circle',
            symbolSize: 6,
            areaStyle: { opacity: 0.14 },
            data: data.items.map((item) => Number(item.quantity)),
        }],
    };
}

function lineComparisonOption(data: DashboardLineComparison): EChartsOption {
    return {
        tooltip: { trigger: 'axis' },
        grid: { left: 48, right: 16, top: 24, bottom: 48 },
        xAxis: {
            type: 'category',
            data: data.items.map((item) => item.name),
            axisLabel: { interval: 0, rotate: data.items.length > 5 ? 30 : 0 },
        },
        yAxis: { type: 'value', name: '数量' },
        series: [{
            name: '生产数量',
            type: 'bar',
            barMaxWidth: 40,
            itemStyle: { color: '#1677ff', borderRadius: [4, 4, 0, 0] },
            data: data.items.map((item) => Number(item.quantity)),
        }],
    };
}

function qualifiedRateOption(data: DashboardQuality): EChartsOption {
    return {
        series: [{
            type: 'gauge',
            startAngle: 210,
            endAngle: -30,
            min: 0,
            max: 100,
            progress: { show: true, width: 14, itemStyle: { color: '#52c41a' } },
            axisLine: { lineStyle: { width: 14 } },
            axisTick: { show: false },
            splitLine: { show: false },
            axisLabel: { show: false },
            pointer: { show: false },
            detail: {
                valueAnimation: true,
                formatter: '{value}%',
                fontSize: 26,
                offsetCenter: [0, 0],
            },
            data: [{ value: Number(data.qualified_rate) }],
        }],
    };
}

function equipmentStatusOption(data: DashboardEquipmentStatus): EChartsOption {
    return {
        tooltip: { trigger: 'item' },
        legend: { bottom: 0 },
        series: [{
            name: '设备状态',
            type: 'pie',
            radius: ['42%', '68%'],
            center: ['50%', '44%'],
            label: { formatter: '{b}\n{c} 台' },
            data: data.items.map((item: DashboardEquipmentStatusItem) => ({
                name: item.label,
                value: item.count,
                itemStyle: { color: equipmentStatusColors[item.status] },
            })),
        }],
    };
}

function defectReasonOption(data: DashboardQuality): EChartsOption {
    return {
        tooltip: { trigger: 'item' },
        legend: { type: 'scroll', bottom: 0 },
        series: [{
            name: '不良数量',
            type: 'pie',
            radius: ['42%', '68%'],
            center: ['50%', '44%'],
            label: { formatter: '{b}\n{c}' },
            data: data.defect_reasons.map((item) => ({
                name: item.name,
                value: Number(item.quantity),
            })),
        }],
    };
}

const orderColumns: TableProps<ProductionOrder>['columns'] = [
    { title: '订单编号', dataIndex: 'code', width: 160 },
    { title: '产品', width: 130, render: (_, order) => order.product?.name ?? '-' },
    { title: '产线', width: 110, render: (_, order) => order.production_line?.name ?? '-' },
    {
        title: '计划数量',
        width: 100,
        render: (_, order) => quantity(order.planned_quantity),
    },
    {
        title: '状态',
        width: 90,
        render: (_, order) => (
            <Tag color={orderStatusColors[order.status]}>{order.status_label}</Tag>
        ),
    },
];

const reportColumns: TableProps<ProductionReport>['columns'] = [
    { title: '报工编号', dataIndex: 'code', width: 170 },
    { title: '任务', width: 160, render: (_, report) => report.production_task?.code ?? '-' },
    { title: '产品', width: 130, render: (_, report) => report.product?.name ?? '-' },
    { title: '数量', width: 90, render: (_, report) => quantity(report.quantity) },
    {
        title: '报工时间',
        width: 120,
        render: (_, report) => formatTime(report.reported_at),
    },
];

const exceptionColumns: TableProps<ProductionException>['columns'] = [
    { title: '异常编号', dataIndex: 'code', width: 160 },
    { title: '类型', dataIndex: 'type_label', width: 100 },
    { title: '订单', width: 150, render: (_, exception) => exception.production_order?.code ?? '-' },
    {
        title: '状态',
        width: 90,
        render: (_, exception) => (
            <Tag color={exceptionStatusColors[exception.status]}>{exception.status_label}</Tag>
        ),
    },
    {
        title: '上报时间',
        width: 120,
        render: (_, exception) => formatTime(exception.reported_at),
    },
];

const alarmColumns: TableProps<EquipmentAlarm>['columns'] = [
    { title: '告警编号', dataIndex: 'code', width: 160 },
    { title: '设备', width: 160, render: (_, alarm) => `${alarm.equipment.code} ${alarm.equipment.name}` },
    {
        title: '等级',
        width: 90,
        render: (_, alarm) => (
            <Tag color={alarmLevelColors[alarm.level]}>{alarm.level_label}</Tag>
        ),
    },
    {
        title: '状态',
        width: 90,
        render: (_, alarm) => (
            <Tag color={alarmStatusColors[alarm.status]}>{alarm.status_label}</Tag>
        ),
    },
    {
        title: '触发时间',
        width: 120,
        render: (_, alarm) => formatTime(alarm.triggered_at),
    },
];

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

    return (
        <>
            <Space className="mb-4 w-full justify-between" wrap>
                <div>
                    <Typography.Title level={4} className="mb-0!">生产数据总览</Typography.Title>
                    <Typography.Text type="secondary">
                        {statistics.date
                            ? `${dayjs(statistics.date).format('YYYY年MM月DD日')} 生产概况与最新动态`
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
