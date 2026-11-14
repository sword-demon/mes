import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Alert, Button, Card, Form, Input, Typography } from 'antd';
import { Navigate, useNavigate } from 'react-router';
import { login, loginErrorMessage } from '@/api/auth';
import { useAuthStore } from '@/stores/auth';
import type { LoginPayload } from '@/types/api';

export default function Login() {
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const token = useAuthStore((state) => state.token);
    const signIn = useAuthStore((state) => state.signIn);

    const { mutate, isPending, error } = useMutation({
        mutationFn: login,
        onSuccess: (result) => {
            signIn(result.token, result.user);
            queryClient.setQueryData(['me'], result.user);
            navigate('/', { replace: true });
        },
    });

    if (token) {
        return <Navigate to="/" replace />;
    }

    function handleFinish(values: LoginPayload) {
        mutate({ ...values, device_name: 'web' });
    }

    const errorMessage = error ? loginErrorMessage(error) : undefined;

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
                        title={errorMessage}
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
