import { Button, Descriptions, Modal, Space, Typography } from 'antd';
import { businessTime } from '@/utils/datetime';
import type { ProductSnLabel } from '@/api/production';

export default function LabelModal({
    label,
    printing,
    onPrint,
    onClose,
}: {
    label: ProductSnLabel;
    printing: boolean;
    onPrint: () => void;
    onClose: () => void;
}) {
    return (
        <Modal
            open
            title="SN 标签"
            onCancel={onClose}
            footer={
                <Space>
                    <Button onClick={onClose}>关闭</Button>
                    <Button type="primary" loading={printing} onClick={onPrint}>
                        打印
                    </Button>
                </Space>
            }
        >
            <div className="sn-label rounded border-2 border-dashed border-gray-400 p-6">
                <Typography.Title level={3} className="mb-2! text-center">
                    {label.product_name}
                </Typography.Title>
                <Typography.Paragraph className="mb-4 text-center" type="secondary">
                    {label.product_code}
                </Typography.Paragraph>
                <div className="mb-4 text-center font-mono text-2xl tracking-widest">
                    {label.sn}
                </div>
                <Descriptions column={1} size="small">
                    <Descriptions.Item label="生产订单">{label.production_order}</Descriptions.Item>
                    <Descriptions.Item label="生产批次">{label.production_batch}</Descriptions.Item>
                    <Descriptions.Item label="生成时间">
                        {businessTime(label.generated_at).format('YYYY-MM-DD HH:mm')}
                    </Descriptions.Item>
                </Descriptions>
            </div>
        </Modal>
    );
}
