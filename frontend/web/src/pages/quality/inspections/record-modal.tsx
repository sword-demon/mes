import { useQuery } from '@tanstack/react-query';
import { Alert, App, Empty, Input, InputNumber, Modal, Table, Tag, Typography } from 'antd';
import { useState } from 'react';
import { listInspectionItems } from '@/api/quality';
import type { InspectionRecordPayload, InspectionTask } from '@/api/quality';
import { operationLabel } from './label';

/**
 * Every enabled inspection item applies to the task; the backend judges each
 * measured value against the standard and fails the whole task on one miss.
 */
export default function RecordInspectionModal({
    task,
    saving,
    onSave,
    onClose,
}: {
    task: InspectionTask;
    saving: boolean;
    onSave: (payload: InspectionRecordPayload) => void;
    onClose: () => void;
}) {
    const { message } = App.useApp();
    const [values, setValues] = useState<Record<number, number | null>>({});
    const [remark, setRemark] = useState('');
    const items = useQuery({
        queryKey: ['quality.standards', 'enabled'],
        queryFn: () => listInspectionItems({ status: true, per_page: 100 }),
    });
    const rows = items.data?.items ?? [];

    const submit = () => {
        const missing = rows.filter((item) => values[item.id] === undefined || values[item.id] === null);

        if (rows.length === 0) {
            message.warning('请先在检验标准中维护启用的检验项目');
            return;
        }

        if (missing.length > 0) {
            message.warning(`请录入「${missing[0].name}」的实测值`);
            return;
        }

        onSave({
            items: rows.map((item) => ({
                inspection_item_id: item.id,
                measured_value: values[item.id] as number,
            })),
            remark: remark.trim() === '' ? null : remark,
        });
    };

    return (
        <Modal
            open
            width={720}
            title={`录入实测值 · ${task.production_order.code}`}
            okText="提交判定"
            confirmLoading={saving}
            onCancel={onClose}
            onOk={submit}
        >
            {rows.length === 0 && !items.isLoading ? (
                <Empty description="没有启用的检验项目" />
            ) : (
                <>
                    <Table
                        size="small"
                        rowKey="id"
                        pagination={false}
                        loading={items.isLoading}
                        dataSource={rows}
                        columns={[
                            { title: '检验项目', dataIndex: 'name' },
                            {
                                title: '标准范围',
                                render: (_, item) => (
                                    <Typography.Text>
                                        {item.min_value} ~ {item.max_value} {item.unit}
                                    </Typography.Text>
                                ),
                            },
                            {
                                title: '实测值',
                                width: 200,
                                render: (_, item) => (
                                    <InputNumber
                                        className="w-full"
                                        precision={3}
                                        aria-label={`${item.name}实测值`}
                                        addonAfter={item.unit}
                                        value={values[item.id] ?? null}
                                        onChange={(value) => setValues({ ...values, [item.id]: value })}
                                    />
                                ),
                            },
                        ]}
                    />
                    <Alert
                        className="mt-3"
                        type="info"
                        showIcon
                        message="实测值落在标准范围内判定合格，任一项超出范围则整单不合格。"
                    />
                    <Input.TextArea
                        className="mt-3"
                        rows={2}
                        maxLength={255}
                        placeholder="检验备注（不合格时会作为返工原因）"
                        value={remark}
                        onChange={(event) => setRemark(event.target.value)}
                    />
                    <Tag className="mt-3" color="blue">
                        关联工序：{operationLabel(task)}
                    </Tag>
                </>
            )}
        </Modal>
    );
}
