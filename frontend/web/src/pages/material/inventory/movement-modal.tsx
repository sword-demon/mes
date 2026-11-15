import { Form, Input, InputNumber, Modal, Select, Typography } from 'antd';
import { useState } from 'react';
import type {
    Inventory,
    InventoryAdjustPayload,
    InventoryBusinessType,
    InventoryMaterialRef,
    InventoryMovementPayload,
    InventoryOptions,
} from '@/api/inventory';

const META: Record<InventoryBusinessType, { title: string; okText: string }> = {
    inbound: { title: '入库', okText: '确认入库' },
    outbound: { title: '出库', okText: '确认出库' },
    adjustment: { title: '库存调整', okText: '确认调整' },
};

type FormValues = {
    warehouse_id: number;
    location_id: number;
    material_id: number;
    batch_no?: string | null;
    quantity?: number;
    current_quantity?: number;
    reference_no?: string | null;
    remark?: string | null;
};

export default function MovementModal({
    type,
    record,
    options,
    saving,
    onSave,
    onClose,
}: {
    type: InventoryBusinessType;
    record?: Inventory;
    options: InventoryOptions;
    saving: boolean;
    onSave: (payload: InventoryMovementPayload | InventoryAdjustPayload) => void;
    onClose: () => void;
}) {
    const [form] = Form.useForm<FormValues>();
    const warehouseId = Form.useWatch<number | undefined>('warehouse_id', form);
    const materialId = Form.useWatch<number | undefined>('material_id', form);
    const [warehouse, setWarehouse] = useState<number | undefined>(
        record?.location?.warehouse?.id,
    );
    const locationOptions = options.warehouses.find((item) => item.id === warehouse)?.locations ?? [];
    const material: InventoryMaterialRef | undefined = options.materials.find(
        (item) => item.id === materialId,
    );
    const selectLabel = (item: { code: string; name: string }) => item.code + ' ' + item.name;

    return (
        <Modal
            open
            title={META[type].title}
            okText={META[type].okText}
            confirmLoading={saving}
            onCancel={onClose}
            onOk={() => form.submit()}
        >
            <Form
                form={form}
                layout="vertical"
                initialValues={record
                    ? {
                        warehouse_id: record.location?.warehouse?.id,
                        location_id: record.location_id,
                        material_id: record.material_id,
                        batch_no: record.batch_no || undefined,
                        current_quantity: type === 'adjustment' ? record.current_quantity : undefined,
                    }
                    : undefined}
                onValuesChange={(changed) => {
                    if ('warehouse_id' in changed) {
                        setWarehouse(changed.warehouse_id);
                        form.setFieldValue('location_id', undefined);
                    }
                }}
                onFinish={(values) => {
                    const base = {
                        material_id: values.material_id,
                        warehouse_id: values.warehouse_id,
                        location_id: values.location_id,
                        batch_no: values.batch_no ?? null,
                        reference_no: values.reference_no ?? null,
                        remark: values.remark ?? null,
                    };

                    if (type === 'adjustment') {
                        onSave({ ...base, current_quantity: values.current_quantity ?? 0 });

                        return;
                    }

                    onSave({ ...base, quantity: values.quantity ?? 0 });
                }}
            >
                <Form.Item
                    name="warehouse_id"
                    label="仓库"
                    rules={[{ required: true, message: '请选择仓库' }]}
                >
                    <Select
                        disabled={record !== undefined}
                        placeholder="请选择仓库"
                        options={options.warehouses.map((item) => ({
                            value: item.id,
                            label: selectLabel(item),
                        }))}
                    />
                </Form.Item>
                <Form.Item
                    name="location_id"
                    label="库位"
                    rules={[{ required: true, message: '请选择库位' }]}
                >
                    <Select
                        disabled={record !== undefined || warehouseId === undefined}
                        placeholder={warehouseId === undefined ? '请先选择仓库' : '请选择库位'}
                        options={locationOptions.map((item) => ({
                            value: item.id,
                            label: selectLabel(item),
                        }))}
                    />
                </Form.Item>
                <Form.Item
                    name="material_id"
                    label="物料"
                    rules={[{ required: true, message: '请选择物料' }]}
                >
                    <Select
                        showSearch
                        optionFilterProp="label"
                        disabled={record !== undefined}
                        placeholder="请选择物料"
                        options={options.materials.map((item) => ({
                            value: item.id,
                            label: selectLabel(item),
                        }))}
                    />
                </Form.Item>
                <Form.Item
                    name="batch_no"
                    label="批次号"
                    rules={material?.batch_managed
                        ? [{ required: true, message: '批次管理物料必须填写批次号' }]
                        : []}
                >
                    <Input
                        disabled={record !== undefined}
                        maxLength={80}
                        placeholder={material?.batch_managed ? '批次管理物料必填' : '选填'}
                    />
                </Form.Item>
                {type === 'adjustment' ? (
                    <Form.Item
                        name="current_quantity"
                        label="调整后库存"
                        rules={[{ required: true, message: '请填写调整后库存' }]}
                    >
                        <InputNumber min={0} precision={3} step={0.001} className="w-full" />
                    </Form.Item>
                ) : (
                    <Form.Item
                        name="quantity"
                        label={type === 'inbound' ? '入库数量' : '出库数量'}
                        rules={[{ required: true, message: '请填写数量' }]}
                    >
                        <InputNumber min={0.001} precision={3} step={0.001} className="w-full" />
                    </Form.Item>
                )}
                {type === 'outbound' && record && (
                    <Typography.Text type="secondary">
                        当前可用库存：{record.available_quantity} {record.material?.unit}
                    </Typography.Text>
                )}
                <Form.Item name="reference_no" label="关联单号">
                    <Input maxLength={80} placeholder="选填，如采购单、领料单号" />
                </Form.Item>
                <Form.Item name="remark" label="备注">
                    <Input.TextArea rows={2} maxLength={255} />
                </Form.Item>
            </Form>
        </Modal>
    );
}
