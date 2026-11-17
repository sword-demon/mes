import type { EChartsOption } from 'echarts';
import type { EquipmentHistorySample } from '@/api/equipment';

export type MetricKey = 'temperature' | 'speed' | 'pressure' | 'output';

export const METRICS: { key: MetricKey; label: string; unit: string; color: string }[] = [
    { key: 'temperature', label: '温度', unit: '℃', color: '#1677ff' },
    { key: 'speed', label: '转速', unit: 'rpm', color: '#52c41a' },
    { key: 'pressure', label: '压力', unit: 'MPa', color: '#faad14' },
    { key: 'output', label: '累计产量', unit: '件', color: '#eb2f96' },
];

export function formatMetric(value: number | null | undefined, unit: string): string {
    return value == null ? '-' : `${value} ${unit}`;
}

export function formatRuntime(seconds: number | null): string {
    if (seconds == null) {
        return '-';
    }

    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const rest = seconds % 60;

    return `${hours}:${String(minutes).padStart(2, '0')}:${String(rest).padStart(2, '0')}`;
}

export function metricChartOption(
    samples: EquipmentHistorySample[],
    metric: (typeof METRICS)[number],
): EChartsOption {
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
