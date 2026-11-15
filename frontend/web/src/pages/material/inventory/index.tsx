import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { App, Button, Empty, Input, Select, Space, Table, Tabs, Tag, Typography } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import dayjs from 'dayjs';
import { useState } from 'react';
import Can from '@/components/can';
import {
    adjustInventory,
    getInventoryOptions,
    inboundInventory,
    listInventory,
    listInventoryTransactions,
    outboundInventory,
} from '@/api/inventory';
import type {
    Inventory,
    InventoryAdjustPayload,
    InventoryBusinessType,
    InventoryMovementPayload,
    InventoryTransaction,
} from '@/api/inventory';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import { useAuthStore } from '@/stores/auth';
import { ApiRequestError } from '@/utils/api-error';
import { can } from '@/utils/permission';
import MovementModal from './movement-modal';

export type InventoryPageMode = 'stock' | 'inbound' | 'outbound';

const TYPE_META: Record<InventoryBusinessType, { color: string; label: string }> = {
    inbound: { color: 'green', label: '入库' },
    outbound: { color: 'orange', label: '出库' },
    adjustment: { color: 'purple', label: '库存调整' },
};

const TITLES: Record<InventoryPageMode, { title: string; description: string }> = {
    stock: { title: '库存查询', description: '按仓库、库位、物料与批次查看现存量' },
    inbound: { title: '入库管理', description: '登记入库并保留完整流水' },
    outbound: { title: '出库管理', description: '出库前校验可用库存并保留流水' },
};

type MovementInput =
    | { type: 'inbound'; payload: InventoryMovementPayload }
    | { type: 'outbound'; payload: InventoryMovementPayload }
    | { type: 'adjustment'; payload: InventoryAdjustPayload };

export default function InventoryPage({ mode = 'stock' }: { mode?: InventoryPageMode } = {}) {
    const { message } = App.useApp();
    const queryClient = useQueryClient();
    const user = useAuthStore((state) => state.user);
    const canRead = can(user, 'inventory.index');
    const canInbound = can(user, 'button.inventory.inbound');
    const canOutbound = can(user, 'button.inventory.outbound');
    const canAdjust = can(user, 'button.inventory.adjust');
    const canSee = canRead || canInbound || canOutbound || canAdjust;
    const [tab, setTab] = useState<'stock' | 'transactions'>('stock');
    const [page, setPage] = useState(1);
    const [transactionPage, setTransactionPage] = useState(1);
    const [warehouseId, setWarehouseId] = useState<number>();
    const [locationId, setLocationId] = useState<number>();
    const [materialId, setMaterialId] = useState<number>();
    const [batchNo, setBatchNo] = useState('');
    const debouncedBatchNo = useDebouncedValue(batchNo);
    const [movement, setMovement] = useState<{ type: InventoryBusinessType; record?: Inventory } | null>(null);

    const options = useQuery({
        queryKey: ['inventory', 'options'],
        queryFn: getInventoryOptions,
        enabled: canSee,
    });
    const stock = useQuery({
        queryKey: ['inventory', 'stock', page, warehouseId, locationId, materialId, debouncedBatchNo],
        queryFn: () => listInventory({
            page,
            warehouse_id: warehouseId,
            location_id: locationId,
            material_id: materialId,
            batch_no: debouncedBatchNo || undefined,
        }),
        enabled: canRead && mode === 'stock' && tab === 'stock',
    });
    const transactions = useQuery({
        queryKey: ['inventory', 'transactions', mode, tab, transactionPage, warehouseId, locationId, materialId, debouncedBatchNo],
        queryFn: () => listInventoryTransactions({
            page: transactionPage,
            business_type: mode === 'stock' ? undefined : mode,
            warehouse_id: warehouseId,
            location_id: locationId,
            material_id: materialId,
            batch_no: debouncedBatchNo || undefined,
        }),
        enabled: canSee && (mode !== 'stock' || tab === 'transactions'),
    });

    const selectedWarehouse = options.data?.warehouses.find((item) => item.id === warehouseId);
    const showError = (error: unknown) =>
        message.error(error instanceof ApiRequestError ? error.message : '操作失败');
    const resetPages = () => {
        setPage(1);
        setTransactionPage(1);
    };

    const save = useMutation({
        mutationFn: (input: MovementInput) => {
            if (input.type === 'adjustment') {
                return adjustInventory(input.payload);
            }

            return input.type === 'inbound'
                ? inboundInventory(input.payload)
                : outboundInventory(input.payload);
        },
        onSuccess: async () => {
            await queryClient.invalidateQueries({ queryKey: ['inventory'] });
            message.success('库存已更新');
            setMovement(null);
        },
        onError: showError,
    });

    if (!canSee) {
        return <Empty description="暂无库存查看权限" />;
    }

    const stockColumns: ColumnsType<Inventory> = [
        {
            title: '物料',
            render: (_, record) => (
                <Space direction="vertical" size={0}>
                    <Typography.Text>{record.material?.code} {record.material?.name}</Typography.Text>
                    <Typography.Text type="secondary">{record.material?.unit}</Typography.Text>
                </Space>
            ),
        },
        {
            title: '仓库 / 库位',
            render: (_, record) => (
                <Space direction="vertical" size={0}>
                    <Typography.Text>{record.location?.warehouse?.name ?? '—'}</Typography.Text>
                    <Typography.Text type="secondary">{record.location?.code ?? '—'}</Typography.Text>
                </Space>
            ),
        },
        { title: '批次', dataIndex: 'batch_no', width: 140, render: (value: string) => value || '—' },
        { title: '当前库存', dataIndex: 'current_quantity', width: 110 },
        {
            title: '可用库存',
            dataIndex: 'available_quantity',
            width: 110,
            render: (value: number) => <Typography.Text type={value <= 0 ? 'danger' : undefined}>{value}</Typography.Text>,
        },
        { title: '冻结库存', dataIndex: 'frozen_quantity', width: 110 },
        {
            title: '更新时间',
            width: 170,
            render: (_, record) => dayjs(record.updated_at).format('YYYY-MM-DD HH:mm'),
        },
        {
            title: '操作',
            width: 190,
            render: (_, record) => (
                <Space>
                    {canInbound && (
                        <Button size="small" type="link" onClick={() => setMovement({ type: 'inbound', record })}>
                            入库
                        </Button>
                    )}
                    {canOutbound && (
                        <Button size="small" type="link" onClick={() => setMovement({ type: 'outbound', record })}>
                            出库
                        </Button>
                    )}
                    {canAdjust && (
                        <Button size="small" type="link" onClick={() => setMovement({ type: 'adjustment', record })}>
                            调整
                        </Button>
                    )}
                </Space>
            ),
        },
    ];

    const transactionColumns: ColumnsType<InventoryTransaction> = [
        {
            title: '时间',
            width: 170,
            render: (_, record) => dayjs(record.created_at).format('YYYY-MM-DD HH:mm'),
        },
        {
            title: '物料',
            render: (_, record) => record.material
                ? record.material.code + ' ' + record.material.name
                : '—',
        },
        {
            title: '仓库 / 库位',
            render: (_, record) => record.inventory?.location
                ? (record.inventory.location.warehouse?.name ?? '') + ' / ' + record.inventory.location.code
                : '—',
        },
        {
            title: '批次',
            width: 140,
            render: (_, record) => record.inventory?.batch_no || '—',
        },
        {
            title: '业务类型',
            width: 110,
            render: (_, record) => {
                const meta = TYPE_META[record.business_type];

                return <Tag color={meta.color}>{record.business_type_label || meta.label}</Tag>;
            },
        },
        {
            title: '变动数量',
            width: 110,
            render: (_, record) => (
                <Typography.Text type={record.quantity >= 0 ? 'success' : 'danger'}>
                    {record.quantity > 0 ? '+' : ''}{record.quantity}
                </Typography.Text>
            ),
        },
        { title: '变动前', dataIndex: 'before_quantity', width: 100 },
        { title: '变动后', dataIndex: 'after_quantity', width: 100 },
        {
            title: '关联单号',
            width: 150,
            render: (_, record) => record.reference_no || '—',
        },
    ];

    const filters = (
        <Space className="mb-4" wrap>
            <Select
                allowClear
                placeholder="仓库"
                style={{ width: 170 }}
                loading={options.isLoading}
                value={warehouseId}
                onChange={(value) => {
                    setWarehouseId(value);
                    setLocationId(undefined);
                    resetPages();
                }}
                options={(options.data?.warehouses ?? []).map((item) => ({
                    value: item.id,
                    label: item.code + ' ' + item.name,
                }))}
            />
            <Select
                allowClear
                placeholder="库位"
                style={{ width: 170 }}
                disabled={warehouseId === undefined}
                value={locationId}
                onChange={(value) => {
                    setLocationId(value);
                    resetPages();
                }}
                options={(selectedWarehouse?.locations ?? []).map((item) => ({
                    value: item.id,
                    label: item.code + ' ' + item.name,
                }))}
            />
            <Select
                allowClear
                showSearch
                optionFilterProp="label"
                placeholder="物料"
                style={{ width: 240 }}
                loading={options.isLoading}
                value={materialId}
                onChange={(value) => {
                    setMaterialId(value);
                    resetPages();
                }}
                options={(options.data?.materials ?? []).map((item) => ({
                    value: item.id,
                    label: item.code + ' ' + item.name,
                }))}
            />
            <Input
                allowClear
                placeholder="批次号"
                style={{ width: 160 }}
                value={batchNo}
                onChange={(event) => {
                    setBatchNo(event.target.value);
                    resetPages();
                }}
            />
        </Space>
    );

    const header = TITLES[mode];
    const stockTable = (
        <>
            {filters}
            <Table<Inventory>
                rowKey="id"
                loading={stock.isLoading}
                dataSource={stock.data?.items ?? []}
                columns={stockColumns}
                pagination={{
                    current: page,
                    pageSize: stock.data?.meta.per_page ?? 15,
                    total: stock.data?.meta.total ?? 0,
                    onChange: setPage,
                    showSizeChanger: false,
                }}
            />
        </>
    );
    const transactionTable = (
        <>
            {filters}
            <Table<InventoryTransaction>
                rowKey="id"
                loading={transactions.isLoading}
                dataSource={transactions.data?.items ?? []}
                columns={transactionColumns}
                pagination={{
                    current: transactionPage,
                    pageSize: transactions.data?.meta.per_page ?? 15,
                    total: transactions.data?.meta.total ?? 0,
                    onChange: setTransactionPage,
                    showSizeChanger: false,
                }}
            />
        </>
    );

    return (
        <>
            <Space className="mb-4 w-full justify-between" wrap>
                <div>
                    <Typography.Title level={4} className="mb-0!">{header.title}</Typography.Title>
                    <Typography.Text type="secondary">{header.description}</Typography.Text>
                </div>
                <Space>
                    {(mode === 'stock' || mode === 'inbound') && (
                        <Can code="button.inventory.inbound">
                            <Button type="primary" onClick={() => setMovement({ type: 'inbound' })}>入库</Button>
                        </Can>
                    )}
                    {(mode === 'stock' || mode === 'outbound') && (
                        <Can code="button.inventory.outbound">
                            <Button onClick={() => setMovement({ type: 'outbound' })}>出库</Button>
                        </Can>
                    )}
                    {mode === 'stock' && (
                        <Can code="button.inventory.adjust">
                            <Button onClick={() => setMovement({ type: 'adjustment' })}>库存调整</Button>
                        </Can>
                    )}
                </Space>
            </Space>

            {mode === 'stock' ? (
                <Tabs
                    activeKey={tab}
                    onChange={(key) => setTab(key as 'stock' | 'transactions')}
                    items={[
                        { key: 'stock', label: '库存', children: stockTable },
                        { key: 'transactions', label: '流水', children: transactionTable },
                    ]}
                />
            ) : transactionTable}

            {movement && options.data && (
                <MovementModal
                    type={movement.type}
                    record={movement.record}
                    options={options.data}
                    saving={save.isPending}
                    onClose={() => setMovement(null)}
                    onSave={(payload) => {
                        if (movement.type === 'adjustment') {
                            save.mutate({ type: 'adjustment', payload: payload as InventoryAdjustPayload });

                            return;
                        }

                        save.mutate({
                            type: movement.type,
                            payload: payload as InventoryMovementPayload,
                        });
                    }}
                />
            )}
        </>
    );
}
