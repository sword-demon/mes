import { useMutation } from '@tanstack/react-query';
import { Alert, Button, Card, Form, Input, Typography } from 'antd';
import { Navigate, useNavigate } from 'react-router';
import { login } from '@/api/auth';
import { useAuthStore } from '@/stores/auth';
import type { LoginPayload } from '@/types/api';
import { ApiRequestError } from '@/utils/api-error';

export default function Login() {
    const navigate = useNavigate();
    const token = useAuthStore((state) => state.token);
    const setToken = useAuthStore((state) => state.setToken);

    const { mutate, isPending, error } = useMutation({
        mutationFn: login,
        onSuccess: (result) => {
            setToken(result.token);
            navigate('/', { replace: true });
        },
    });

    if (token) {
        return <Navigate to="/" replace />;
    }

    function handleFinish(values: LoginPayload) {
        mutate({ ...values, device_name: 'web' });
    }

    const errorMessage =
        error instanceof ApiRequestError && error.status === 422
            ? '邮箱或密码错误'
            : error?.message;

    return (
        <main className="flex min-h-screen items-center justify-center bg-neutral-100">
            <Card className="w-96">
                <Typography.Title level={3} className="text-center!">
                    MES 管理系统
                </Typography.Title>
                {errorMessage && (
                    <Alert
                        type="error"
                        showIcon
                        message={errorMessage}
                        className="mb-4"
                    />
                )}
                <Form<LoginPayload> layout="vertical" onFinish={handleFinish}>
                    <Form.Item
                        name="email"
                        label="邮箱"
                        rules={[
                            { required: true, message: '请输入邮箱' },
                            { type: 'email', message: '邮箱格式不正确' },
                        ]}
                    >
                        <Input autoComplete="username" />
                    </Form.Item>
                    <Form.Item
                        name="password"
                        label="密码"
                        rules={[{ required: true, message: '请输入密码' }]}
                    >
                        <Input.Password autoComplete="current-password" />
                    </Form.Item>
                    <Button type="primary" htmlType="submit" block loading={isPending}>
                        登录
                    </Button>
                </Form>
            </Card>
        </main>
    );
}
