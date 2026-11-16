import { Tag } from 'antd';
import type { TableProps } from 'antd';
import dayjs from 'dayjs';
import {
    EQUIPMENT_ALARM_LEVELS,
    EQUIPMENT_ALARM_STATUSES,
} from '@/api/equipment';
import type { EquipmentAlarm } from '@/api/equipment';
import {
    PRODUCTION_EXCEPTION_STATUSES,
    PRODUCTION_ORDER_STATUSES,
} from '@/api/production';
import type {
    ProductionException,
    ProductionOrder,
    ProductionReport,
} from '@/api/production';

function colorMap<T extends string>(items: readonly { value: T; color: string }[]): Record<T, string> {
    return Object.fromEntries(items.map(({ value, color }) => [value, color])) as Record<T, string>;
}

const orderStatusColors = colorMap(PRODUCTION_ORDER_STATUSES);
const exceptionStatusColors = colorMap(PRODUCTION_EXCEPTION_STATUSES);
const alarmLevelColors = colorMap(EQUIPMENT_ALARM_LEVELS);
const alarmStatusColors = colorMap(EQUIPMENT_ALARM_STATUSES);

const formatQuantity = (value: string | number): string =>
    Number(value).toLocaleString('zh-CN', { maximumFractionDigits: 3 });

const formatTime = (value: string | null | undefined): string =>
    value ? dayjs(value).format('MM-DD HH:mm') : '-';

export const orderColumns: TableProps<ProductionOrder>['columns'] = [
    { title: '订单编号', dataIndex: 'code', width: 160 },
    { title: '产品', width: 130, render: (_, order) => order.product?.name ?? '-' },
    { title: '产线', width: 110, render: (_, order) => order.production_line?.name ?? '-' },
    {
        title: '计划数量',
        width: 100,
        render: (_, order) => formatQuantity(order.planned_quantity),
    },
    {
        title: '状态',
        width: 90,
        render: (_, order) => (
            <Tag color={orderStatusColors[order.status]}>{order.status_label}</Tag>
        ),
    },
];

export const reportColumns: TableProps<ProductionReport>['columns'] = [
    { title: '报工编号', dataIndex: 'code', width: 170 },
    { title: '任务', width: 160, render: (_, report) => report.production_task?.code ?? '-' },
    { title: '产品', width: 130, render: (_, report) => report.product?.name ?? '-' },
    { title: '数量', width: 90, render: (_, report) => formatQuantity(report.quantity) },
    {
        title: '报工时间',
        width: 120,
        render: (_, report) => formatTime(report.reported_at),
    },
];

export const exceptionColumns: TableProps<ProductionException>['columns'] = [
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

export const alarmColumns: TableProps<EquipmentAlarm>['columns'] = [
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
