import { BarChart, GaugeChart, LineChart, PieChart } from 'echarts/charts';
import {
    DataZoomComponent,
    GridComponent,
    LegendComponent,
    TooltipComponent,
} from 'echarts/components';
import * as echarts from 'echarts/core';
import { CanvasRenderer } from 'echarts/renderers';
import type { EChartsOption } from 'echarts';
import { useEffect, useRef } from 'react';

echarts.use([
    BarChart,
    GaugeChart,
    LineChart,
    PieChart,
    DataZoomComponent,
    GridComponent,
    LegendComponent,
    TooltipComponent,
    CanvasRenderer,
]);

export default function EChart({ option, className }: { option: EChartsOption; className?: string }) {
    const container = useRef<HTMLDivElement>(null);
    const chart = useRef<ReturnType<typeof echarts.init> | null>(null);

    useEffect(() => {
        if (!container.current) {
            return;
        }

        chart.current = echarts.init(container.current);
        const observer = new ResizeObserver(() => chart.current?.resize());
        observer.observe(container.current);

        return () => {
            observer.disconnect();
            chart.current?.dispose();
            chart.current = null;
        };
    }, []);

    useEffect(() => {
        chart.current?.setOption(option, true);
    }, [option]);

    return <div ref={container} className={className} />;
}
