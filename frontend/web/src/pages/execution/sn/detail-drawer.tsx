import { Descriptions, Drawer, Space, Table, Tag, Typography } from 'antd';
import { businessTime } from '@/utils/datetime';
import type { ProductSn } from '@/api/production';
import { snStatusColor } from './status-color';

export default function DetailDrawer({
    sn,
    data,
    onClose,
}: {
    sn: string | null;
    data: ProductSn | undefined;
    onClose: () => void;
}) {
    return (
        <Drawer open={sn !== null} title={sn ?? 'SN 详情'} size={760} onClose={onClose}>
            {data && (
                <Space orientation="vertical" className="w-full" size="large">
                    <Descriptions column={2} bordered size="small">
                        <Descriptions.Item label="状态">
                            <Tag color={snStatusColor[data.status]}>{data.status_label}</Tag>
                        </Descriptions.Item>
                        <Descriptions.Item label="物料批次">
                            {data.material_batch_no ?? '-'}
                        </Descriptions.Item>
                        <Descriptions.Item label="生产订单">
                            {data.production_order?.code ?? '-'}
                        </Descriptions.Item>
                        <Descriptions.Item label="生产批次">
                            {data.production_batch?.code ?? '-'}
                        </Descriptions.Item>
                        <Descriptions.Item label="产品">
                            {data.production_order?.product?.name ?? '-'}
                        </Descriptions.Item>
                        <Descriptions.Item label="操作人员">
                            {data.employee
                                ? `${data.employee.employee_no} ${data.employee.name}`
                                : '-'}
                        </Descriptions.Item>
                        {data.void_reason && (
                            <Descriptions.Item label="作废原因" span={2}>
                                {data.void_reason}
                            </Descriptions.Item>
                        )}
                    </Descriptions>
                    <div>
                        <Typography.Title level={5}>工序执行状态</Typography.Title>
                        <Table
                            rowKey="id"
                            size="small"
                            pagination={false}
                            dataSource={data.process_records ?? []}
                            columns={[
                                { title: '顺序', dataIndex: 'sequence', width: 70 },
                                {
                                    title: '工序',
                                    render: (_, record) =>
                                        record.route_operation
                                            ? `${record.route_operation.sequence}. ${record.route_operation.process.name}`
                                            : '-',
                                },
                                {
                                    title: '操作人员',
                                    width: 130,
                                    render: (_, record) =>
                                        record.employee
                                            ? `${record.employee.employee_no} ${record.employee.name}`
                                            : '-',
                                },
                                {
                                    title: '状态',
                                    width: 100,
                                    render: (_, record) => <Tag>{record.status_label}</Tag>,
                                },
                                {
                                    title: '开始时间',
                                    width: 150,
                                    render: (_, record) =>
                                        record.started_at
                                            ? businessTime(record.started_at).format('MM-DD HH:mm')
                                            : '-',
                                },
                            ]}
                        />
                    </div>
                </Space>
            )}
        </Drawer>
    );
}
