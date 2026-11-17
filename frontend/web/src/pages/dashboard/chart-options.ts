import { businessTime } from '@/utils/datetime';
import type { EChartsOption } from 'echarts';
import type {
    DashboardEquipmentStatus,
    DashboardEquipmentStatusItem,
    DashboardLineComparison,
    DashboardQuality,
    DashboardTrend,
} from '@/api/dashboard';
import type { EquipmentStatus } from '@/api/equipment';

const equipmentStatusColors: Record<EquipmentStatus, string> = {
    running: '#52c41a',
    idle: '#1677ff',
    fault: '#ff4d4f',
    repair: '#faad14',
    offline: '#8c8c8c',
};

export function trendOption(data: DashboardTrend): EChartsOption {
    return {
        tooltip: { trigger: 'axis' },
        grid: { left: 48, right: 16, top: 24, bottom: 32 },
        xAxis: {
            type: 'category',
            boundaryGap: false,
            data: data.items.map((item) => businessTime(item.date).format('MM-DD')),
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

export function lineComparisonOption(data: DashboardLineComparison): EChartsOption {
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

export function qualifiedRateOption(data: DashboardQuality): EChartsOption {
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

export function equipmentStatusOption(data: DashboardEquipmentStatus): EChartsOption {
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

export function defectReasonOption(data: DashboardQuality): EChartsOption {
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
