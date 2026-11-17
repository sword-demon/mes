import { App, Button, Descriptions, Drawer, Image, Space, Table, Tag, Typography, Upload } from 'antd';
import type { RcFile } from 'antd/es/upload/interface';
import { businessTime } from '@/utils/datetime';
import Can from '@/components/can';
import type { InspectionTask } from '@/api/quality';
import { operationLabel } from './label';

export default function InspectionDetailDrawer({
    task,
    onClose,
    onUpload,
    uploading,
}: {
    task: InspectionTask | null;
    onClose: () => void;
    onUpload: (files: File[]) => void;
    uploading: boolean;
}) {
    const { message } = App.useApp();

    const beforeUpload = (file: RcFile) => {
        if (!file.type?.startsWith('image/')) {
            message.error('只能上传图片');
            return Upload.LIST_IGNORE;
        }

        onUpload([file]);

        return false;
    };

    return (
        <Drawer open={task !== null} width={720} title="检验详情" onClose={onClose}>
            {task && (
                <Space direction="vertical" className="w-full" size="large">
                    <Descriptions column={2} size="small" bordered>
                        <Descriptions.Item label="生产订单">{task.production_order.code}</Descriptions.Item>
                        <Descriptions.Item label="产品">{task.production_order.product.name}</Descriptions.Item>
                        <Descriptions.Item label="检验类型">{task.inspection_type_label}</Descriptions.Item>
                        <Descriptions.Item label="判定">
                            <Tag color={task.status === 'passed' ? 'green' : task.status === 'failed' ? 'red' : 'default'}>
                                {task.result_label}
                            </Tag>
                        </Descriptions.Item>
                        <Descriptions.Item label="工序" span={2}>
                            {operationLabel(task)}
                        </Descriptions.Item>
                        <Descriptions.Item label="检验员">{task.inspector?.name ?? '—'}</Descriptions.Item>
                        <Descriptions.Item label="检验时间">
                            {task.inspected_at ? businessTime(task.inspected_at).format('YYYY-MM-DD HH:mm') : '—'}
                        </Descriptions.Item>
                        <Descriptions.Item label="备注" span={2}>{task.remark ?? '—'}</Descriptions.Item>
                    </Descriptions>

                    <div>
                        <Typography.Title level={5}>检验项目</Typography.Title>
                        <Table
                            size="small"
                            rowKey="inspection_item_id"
                            pagination={false}
                            dataSource={task.items}
                            columns={[
                                { title: '项目', dataIndex: 'name' },
                                {
                                    title: '标准范围',
                                    render: (_, item) => `${item.min_value} ~ ${item.max_value} ${item.unit}`,
                                },
                                {
                                    title: '实测值',
                                    render: (_, item) => (
                                        <Typography.Text type={item.passed ? undefined : 'danger'}>
                                            {item.measured_value} {item.unit}
                                        </Typography.Text>
                                    ),
                                },
                                {
                                    title: '结果',
                                    width: 80,
                                    render: (_, item) => (
                                        <Tag color={item.passed ? 'green' : 'red'}>{item.passed ? '合格' : '不合格'}</Tag>
                                    ),
                                },
                            ]}
                        />
                    </div>

                    <div>
                        <Space className="mb-3 w-full justify-between">
                            <Typography.Title level={5} className="mb-0!">检验图片</Typography.Title>
                            <Can code="button.quality.inspections.record">
                                <Upload
                                    multiple
                                    showUploadList={false}
                                    accept="image/*"
                                    beforeUpload={beforeUpload}
                                    disabled={uploading}
                                >
                                    <Button loading={uploading}>上传图片</Button>
                                </Upload>
                            </Can>
                        </Space>
                        {task.images.length === 0 ? (
                            <Typography.Text type="secondary">暂无检验图片</Typography.Text>
                        ) : (
                            <Image.PreviewGroup>
                                <Space wrap>
                                    {task.images.map((image) => (
                                        <Image
                                            key={image.id}
                                            src={image.url}
                                            width={96}
                                            height={96}
                                            style={{ objectFit: 'cover' }}
                                        />
                                    ))}
                                </Space>
                            </Image.PreviewGroup>
                        )}
                    </div>
                </Space>
            )}
        </Drawer>
    );
}
