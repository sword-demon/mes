import { Typography } from 'antd';

export default function Placeholder({ title }: { title: string }) {
    return (
        <>
            <Typography.Title level={4}>{title}</Typography.Title>
            <Typography.Paragraph type="secondary">
                该功能将在后续迭代接入。
            </Typography.Paragraph>
        </>
    );
}
