import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
    App,
    Button,
    DatePicker,
    Empty,
    Form,
    Input,
    Modal,
    Select,
    Space,
    Table,
    Tag,
    Typography,
} from 'antd';
import dayjs from 'dayjs';
import type { Dayjs } from 'dayjs';
import { useState } from 'react';
import Can from '@/components/can';
import {
    closeEquipmentAlarm,
    confirmEquipmentAlarm,
    EQUIPMENT_ALARM_LEVELS,
    EQUIPMENT_ALARM_STATUSES,
    getEquipmentAlarmOptions,
    listEquipmentAlarms,
    processEquipmentAlarm,
} from '@/api/equipment';
import type {
    EquipmentAlarm,
    EquipmentAlarmLevel,
    EquipmentAlarmStatus,
} from '@/api/equipment';
import { useAuthStore } from '@/stores/auth';
import { ApiRequestError } from '@/utils/api-error';
import { can } from '@/utils/permission';

const levelColor = Object.fromEntries(
    EQUIPMENT_ALARM_LEVELS.map(({ value, color }) => [value, color]),
);
const statusColor = Object.fromEntries(
    EQUIPMENT_ALARM_STATUSES.map(({ value, color }) => [value, color]),
);

type HandlingAction = 'process' | 'close';

function HandlingModal({
    alarm,
    action,
    onClose,
}: {
    alarm: EquipmentAlarm;
    action: HandlingAction;
    onClose: () => void;
}) {
    const { message } = App.useApp();
    const client = useQueryClient();
    const [form] = Form.useForm<{ handling_note?: string }>();
    const save = useMutation({
        mutationFn: (note: string) => action === 'process'
            ? processEquipmentAlarm(alarm.id, note)
            : closeEquipmentAlarm(alarm.id, note),
        onSuccess: async (updated) => {
            await client.invalidateQueries({ queryKey: ['equipment', 'alarms'] });
            message.success(`设备告警${updated.status_label}`);
            onClose();
        },
        onError: (error) => message.error(
            error instanceof ApiRequestError ? error.message : '操作失败',
        ),
    });

    return (
        <Modal
            open
            title={action === 'process' ? '处理设备告警' : '关闭设备告警'}
            okText={action === 'process' ? '提交处理' : '确认关闭'}
            confirmLoading={save.isPending}
            onCancel={onClose}
            onOk={() => form.submit()}
        >
            <Form
                form={form}
                layout="vertical"
                initialValues={{ handling_note: alarm.handling_note ?? undefined }}
                onFinish={(values) => save.mutate(values.handling_note ?? '')}
            >
                <Form.Item
                    name="handling_note"
                    label="处理说明"
                    rules={action === 'process'
                        ? [{ required: true, message: '请填写处理说明' }]
                        : []}
                >
                    <Input.TextArea rows={4} maxLength={1000} showCount />
                </Form.Item>
            </Form>
        </Modal>
    );
}

export default function EquipmentAlarmsPage() {
    const { message } = App.useApp();
    const queryClient = useQueryClient();
    const user = useAuthStore((state) => state.user);
    const canRead = can(user, 'equipment.alarms.index');
    const [page, setPage] = useState(1);
    const [equipmentId, setEquipmentId] = useState<number>();
    const [level, setLevel] = useState<EquipmentAlarmLevel>();
    const [status, setStatus] = useState<EquipmentAlarmStatus>();
    const [dates, setDates] = useState<[Dayjs, Dayjs] | null>(null);
    const [handling, setHandling] = useState<{ alarm: EquipmentAlarm; action: HandlingAction }>();
    const startDate = dates?.[0].format('YYYY-MM-DD');
    const endDate = dates?.[1].format('YYYY-MM-DD');
    const options = useQuery({
        queryKey: ['equipment', 'alarms', 'options'],
        queryFn: getEquipmentAlarmOptions,
        enabled: canRead,
    });
    const alarms = useQuery({
        queryKey: ['equipment', 'alarms', page, equipmentId, level, status, startDate, endDate],
        queryFn: () => listEquipmentAlarms({
            page,
            equipment_id: equipmentId,
            level,
            status,
            start_date: startDate,
            end_date: endDate,
        }),
        enabled: canRead,
    });
    const confirm = useMutation({
        mutationFn: confirmEquipmentAlarm,
        onSuccess: async (updated) => {
            await queryClient.invalidateQueries({ queryKey: ['equipment', 'alarms'] });
            message.success(`设备告警${updated.status_label}`);
        },
        onError: (error) => message.error(
            error instanceof ApiRequestError ? error.message : '操作失败',
        ),
    });

    if (!canRead) {
        return <Empty description="暂无设备告警查看权限" />;
    }

    return (
        <>
            <Space className="mb-4 w-full justify-between" wrap>
                <div>
                    <Typography.Title level={4} className="mb-0!">设备告警</Typography.Title>
                    <Typography.Text type="secondary">
                        按设备、等级、状态与时间查询告警，完成确认、处理与关闭
                    </Typography.Text>
                </div>
            </Space>
            <Space className="mb-4" wrap>
                <Select
                    allowClear
                    showSearch
                    optionFilterProp="label"
                    placeholder="筛选设备"
                    className="w-56"
                    value={equipmentId}
                    loading={options.isLoading}
                    options={(options.data?.equipments ?? []).map((equipment) => ({
                        value: equipment.id,
                        label: `${equipment.code} ${equipment.name}`,
                    }))}
                    onChange={(value?: number) => {
                        setEquipmentId(value);
                        setPage(1);
                    }}
                />
                <Select
                    allowClear
                    placeholder="告警等级"
                    className="w-32"
                    options={EQUIPMENT_ALARM_LEVELS.map(({ value, label }) => ({ value, label }))}
                    onChange={(value?: EquipmentAlarmLevel) => {
                        setLevel(value);
                        setPage(1);
                    }}
                />
                <Select
                    allowClear
                    placeholder="告警状态"
                    className="w-32"
                    options={EQUIPMENT_ALARM_STATUSES.map(({ value, label }) => ({ value, label }))}
                    onChange={(value?: EquipmentAlarmStatus) => {
                        setStatus(value);
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
            <Table<EquipmentAlarm>
                rowKey="id"
                loading={alarms.isLoading}
                dataSource={alarms.data?.items ?? []}
                scroll={{ x: 1280 }}
                locale={{ emptyText: '暂无设备告警' }}
                pagination={{
                    current: page,
                    pageSize: alarms.data?.meta.per_page ?? 15,
                    total: alarms.data?.meta.total ?? 0,
                    onChange: setPage,
                    showSizeChanger: false,
                }}
                columns={[
                    { title: '告警编号', dataIndex: 'code', width: 170 },
                    {
                        title: '设备',
                        width: 180,
                        render: (_, alarm) => (
                            <Space orientation="vertical" size={0}>
                                <Typography.Text>{alarm.equipment.code} {alarm.equipment.name}</Typography.Text>
                                <Typography.Text type="secondary">{alarm.equipment.status_label}</Typography.Text>
                            </Space>
                        ),
                    },
                    {
                        title: '指标',
                        width: 170,
                        render: (_, alarm) => alarm.metric === null
                            ? (alarm.message ?? '—')
                            : `${alarm.metric_label} ${alarm.measured_value} / 阈值 ${alarm.threshold} ${alarm.metric_unit}`,
                    },
                    {
                        title: '等级',
                        dataIndex: 'level',
                        width: 90,
                        render: (_, alarm) => (
                            <Tag color={levelColor[alarm.level]}>{alarm.level_label}</Tag>
                        ),
                    },
                    {
                        title: '状态',
                        dataIndex: 'status',
                        width: 100,
                        render: (_, alarm) => (
                            <Tag color={statusColor[alarm.status]}>{alarm.status_label}</Tag>
                        ),
                    },
                    {
                        title: '触发时间',
                        width: 170,
                        render: (_, alarm) => dayjs(alarm.triggered_at).format('YYYY-MM-DD HH:mm'),
                    },
                    {
                        title: '处理人 / 说明',
                        width: 240,
                        render: (_, alarm) => alarm.handled_by ? (
                            <Space orientation="vertical" size={0}>
                                <Typography.Text>
                                    {alarm.handled_by.name} / {dayjs(alarm.handled_at).format('YYYY-MM-DD HH:mm')}
                                </Typography.Text>
                                <Typography.Text type="secondary">{alarm.handling_note ?? '—'}</Typography.Text>
                            </Space>
                        ) : '—',
                    },
                    {
                        title: '操作',
                        width: 110,
                        fixed: 'right',
                        render: (_, alarm) => (
                            <Space>
                                {alarm.status === 'pending' && (
                                    <Can code="button.equipment.alarms.confirm">
                                        <Button
                                            size="small"
                                            type="link"
                                            loading={confirm.isPending}
                                            onClick={() => confirm.mutate(alarm.id)}
                                        >
                                            确认
                                        </Button>
                                    </Can>
                                )}
                                {alarm.status === 'confirmed' && (
                                    <Can code="button.equipment.alarms.process">
                                        <Button
                                            size="small"
                                            type="link"
                                            onClick={() => setHandling({ alarm, action: 'process' })}
                                        >
                                            处理
                                        </Button>
                                    </Can>
                                )}
                                {alarm.status === 'processing' && (
                                    <Can code="button.equipment.alarms.close">
                                        <Button
                                            size="small"
                                            type="link"
                                            danger
                                            onClick={() => setHandling({ alarm, action: 'close' })}
                                        >
                                            关闭
                                        </Button>
                                    </Can>
                                )}
                            </Space>
                        ),
                    },
                ]}
            />
            {handling && (
                <HandlingModal
                    alarm={handling.alarm}
                    action={handling.action}
                    onClose={() => setHandling(undefined)}
                />
            )}
        </>
    );
}
