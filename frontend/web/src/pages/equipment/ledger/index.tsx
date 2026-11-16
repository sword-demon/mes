import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
    Alert,
    App,
    Button,
    Form,
    Input,
    Modal,
    Popconfirm,
    Select,
    Space,
    Table,
    Tag,
    Typography,
} from 'antd';
import { useState } from 'react';
import Can from '@/components/can';
import {
    createEquipment,
    EQUIPMENT_STATUSES,
    EQUIPMENT_TYPES,
    getEquipmentOptions,
    listEquipments,
    updateEquipment,
} from '@/api/equipment';
import type { Equipment, EquipmentPayload, EquipmentStatus, EquipmentType } from '@/api/equipment';
import { useAuthStore } from '@/stores/auth';
import { ApiRequestError } from '@/utils/api-error';
import { can } from '@/utils/permission';

const statusColor = Object.fromEntries(
    EQUIPMENT_STATUSES.map(({ value, color }) => [value, color]),
);

function EquipmentModal({
    record,
    onClose,
}: {
    record?: Equipment;
    onClose: () => void;
}) {
    const { message } = App.useApp();
    const client = useQueryClient();
    const [form] = Form.useForm<EquipmentPayload>();
    const workshopId = Form.useWatch('workshop_id', form);
    const options = useQuery({ queryKey: ['equipment', 'options'], queryFn: getEquipmentOptions });
    const lineOptions = (options.data?.production_lines ?? [])
        .filter((line) => line.workshop_id === workshopId)
        .map((line) => ({ value: line.id, label: `${line.code} ${line.name}` }));

    const save = useMutation({
        mutationFn: (payload: EquipmentPayload) => record
            ? updateEquipment(record.id, payload)
            : createEquipment(payload),
        onSuccess: async () => {
            await client.invalidateQueries({ queryKey: ['equipment'] });
            message.success('设备已保存');
            onClose();
        },
        onError: (error) => message.error(error instanceof ApiRequestError ? error.message : '保存失败'),
    });

    return (
        <Modal
            open
            title={record ? '编辑设备' : '新增设备'}
            okText="保存"
            confirmLoading={save.isPending}
            onCancel={onClose}
            onOk={() => form.submit()}
        >
            <Form
                form={form}
                layout="vertical"
                initialValues={record ?? { status: 'idle' }}
                onValuesChange={(changed) => {
                    if ('workshop_id' in changed) {
                        form.setFieldValue('production_line_id', undefined);
                    }
                }}
                onFinish={(values) => save.mutate(values)}
            >
                <Form.Item name="code" label="设备编码" rules={[{ required: true, message: '请填写设备编码' }]}>
                    <Input maxLength={64} />
                </Form.Item>
                <Form.Item name="name" label="设备名称" rules={[{ required: true, message: '请填写设备名称' }]}>
                    <Input maxLength={128} />
                </Form.Item>
                <Form.Item name="type" label="设备类型" rules={[{ required: true, message: '请选择设备类型' }]}>
                    <Select options={EQUIPMENT_TYPES.map((type) => ({ value: type, label: type }))} />
                </Form.Item>
                <Form.Item name="workshop_id" label="所属车间" rules={[{ required: true, message: '请选择所属车间' }]}>
                    <Select
                        showSearch
                        optionFilterProp="label"
                        options={(options.data?.workshops ?? []).map((workshop) => ({
                            value: workshop.id,
                            label: `${workshop.code} ${workshop.name}`,
                        }))}
                    />
                </Form.Item>
                <Form.Item name="production_line_id" label="所属产线" rules={[{ required: true, message: '请选择所属产线' }]}>
                    <Select showSearch optionFilterProp="label" disabled={workshopId === undefined} options={lineOptions} />
                </Form.Item>
                <Form.Item name="model" label="设备型号" rules={[{ required: true, message: '请填写设备型号' }]}>
                    <Input maxLength={64} />
                </Form.Item>
                <Form.Item name="status" label="设备状态" rules={[{ required: true, message: '请选择设备状态' }]}>
                    <Select options={EQUIPMENT_STATUSES.map(({ value, label }) => ({ value, label }))} />
                </Form.Item>
                <Form.Item name="owner_id" label="设备负责人" rules={[{ required: true, message: '请选择设备负责人' }]}>
                    <Select
                        showSearch
                        optionFilterProp="label"
                        options={(options.data?.employees ?? []).map((employee) => ({
                            value: employee.id,
                            label: `${employee.employee_no} ${employee.name}`,
                        }))}
                    />
                </Form.Item>
            </Form>
        </Modal>
    );
}

export default function EquipmentLedger() {
    const { message } = App.useApp();
    const client = useQueryClient();
    const user = useAuthStore((state) => state.user);
    const [page, setPage] = useState(1);
    const [keyword, setKeyword] = useState('');
    const [workshopId, setWorkshopId] = useState<number>();
    const [lineId, setLineId] = useState<number>();
    const [type, setType] = useState<EquipmentType>();
    const [status, setStatus] = useState<EquipmentStatus>();
    const [editing, setEditing] = useState<Equipment | 'create'>();
    const canRead = can(user, 'equipment.ledger.index');
    const options = useQuery({
        queryKey: ['equipment', 'options'],
        queryFn: getEquipmentOptions,
        enabled: canRead,
    });
    const equipments = useQuery({
        queryKey: ['equipment', page, keyword, workshopId, lineId, type, status],
        queryFn: () => listEquipments({
            page,
            keyword,
            workshop_id: workshopId,
            production_line_id: lineId,
            type,
            status,
        }),
        enabled: canRead,
    });
    const stop = useMutation({
        mutationFn: (record: Equipment) => updateEquipment(record.id, { enabled: false, status: 'offline' }),
        onSuccess: async () => {
            await client.invalidateQueries({ queryKey: ['equipment'] });
            message.success('设备已停用');
        },
        onError: (error) => message.error(error instanceof ApiRequestError ? error.message : '停用失败'),
    });
    const enable = useMutation({
        mutationFn: (record: Equipment) => updateEquipment(record.id, { enabled: true, status: 'idle' }),
        onSuccess: async () => {
            await client.invalidateQueries({ queryKey: ['equipment'] });
            message.success('设备已启用');
        },
        onError: (error) => message.error(error instanceof ApiRequestError ? error.message : '启用失败'),
    });
    const lineOptions = (options.data?.production_lines ?? [])
        .filter((line) => workshopId === undefined || line.workshop_id === workshopId)
        .map((line) => ({ value: line.id, label: `${line.code} ${line.name}` }));

    if (!canRead) {
        return <Alert type="warning" showIcon title="没有设备台账查询权限" />;
    }

    return (
        <Space orientation="vertical" size="middle" className="w-full">
            <Space className="w-full justify-between" wrap>
                <Typography.Title level={4} className="!mb-0">设备台账</Typography.Title>
                <Can code="button.equipment.ledger.create">
                    <Button type="primary" onClick={() => setEditing('create')}>新增设备</Button>
                </Can>
            </Space>
            <Space wrap>
                <Input.Search
                    allowClear
                    placeholder="编码 / 名称 / 型号"
                    className="w-56"
                    onSearch={(value) => { setKeyword(value); setPage(1); }}
                />
                <Select
                    allowClear
                    showSearch
                    optionFilterProp="label"
                    placeholder="所属车间"
                    className="w-44"
                    options={(options.data?.workshops ?? []).map((workshop) => ({
                        value: workshop.id,
                        label: `${workshop.code} ${workshop.name}`,
                    }))}
                    onChange={(value?: number) => {
                        setWorkshopId(value);
                        setLineId(undefined);
                        setPage(1);
                    }}
                />
                <Select
                    allowClear
                    showSearch
                    optionFilterProp="label"
                    placeholder="所属产线"
                    className="w-40"
                    options={lineOptions}
                    onChange={(value?: number) => { setLineId(value); setPage(1); }}
                />
                <Select
                    allowClear
                    placeholder="设备类型"
                    className="w-44"
                    options={EQUIPMENT_TYPES.map((item) => ({ value: item, label: item }))}
                    onChange={(value?: EquipmentType) => { setType(value); setPage(1); }}
                />
                <Select
                    allowClear
                    placeholder="设备状态"
                    className="w-32"
                    options={EQUIPMENT_STATUSES.map(({ value, label }) => ({ value, label }))}
                    onChange={(value?: EquipmentStatus) => { setStatus(value); setPage(1); }}
                />
            </Space>
            <Table<Equipment>
                rowKey="id"
                loading={equipments.isLoading}
                dataSource={equipments.data?.items ?? []}
                pagination={{
                    current: page,
                    pageSize: equipments.data?.meta.per_page,
                    total: equipments.data?.meta.total,
                    onChange: setPage,
                }}
                columns={[
                    { title: '设备编码', dataIndex: 'code', width: 150 },
                    { title: '设备名称', dataIndex: 'name' },
                    { title: '类型', dataIndex: 'type', width: 150 },
                    { title: '车间', width: 120, render: (_, record) => record.workshop?.name ?? '-' },
                    { title: '产线', width: 130, render: (_, record) => record.production_line?.name ?? '-' },
                    { title: '型号', dataIndex: 'model', width: 120 },
                    {
                        title: '状态',
                        width: 90,
                        render: (_, record) => record.enabled
                            ? <Tag color={statusColor[record.status]}>{record.status_label}</Tag>
                            : <Tag>已停用</Tag>,
                    },
                    {
                        title: '负责人',
                        width: 140,
                        render: (_, record) => record.owner ? `${record.owner.employee_no} ${record.owner.name}` : '-',
                    },
                    {
                        title: '操作',
                        width: 140,
                        render: (_, record) => (
                            <Space>
                                <Can code="button.equipment.ledger.update">
                                    <Button type="link" onClick={() => setEditing(record)}>编辑</Button>
                                </Can>
                                {record.enabled ? (
                                    <Can code="button.equipment.ledger.update">
                                        <Popconfirm
                                            title="停用后设备将变为离线，不再出现在报工与工序下拉中"
                                            okText="停用"
                                            cancelText="取消"
                                            onConfirm={() => stop.mutate(record)}
                                        >
                                            <Button type="link" danger>停用</Button>
                                        </Popconfirm>
                                    </Can>
                                ) : (
                                    <Can code="button.equipment.ledger.update">
                                        <Popconfirm
                                            title="启用后设备将恢复为待机状态"
                                            okText="启用"
                                            cancelText="取消"
                                            onConfirm={() => enable.mutate(record)}
                                        >
                                            <Button type="link">启用</Button>
                                        </Popconfirm>
                                    </Can>
                                )}
                            </Space>
                        ),
                    },
                ]}
            />
            {editing && (
                <EquipmentModal
                    record={editing === 'create' ? undefined : editing}
                    onClose={() => setEditing(undefined)}
                />
            )}
        </Space>
    );
}
