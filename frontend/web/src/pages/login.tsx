import { type FormEvent, useState } from 'react';
import { useNavigate } from 'react-router';
import { api } from '@/lib/api';
import { setToken } from '@/lib/auth';

export default function Login() {
    const [error, setError] = useState<string | null>(null);
    const navigate = useNavigate();

    async function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setError(null);

        const form = new FormData(event.currentTarget);

        try {
            const response = await api.post<{ token: string }>('/auth/tokens', {
                email: form.get('email'),
                password: form.get('password'),
                device_name: 'web',
            });

            setToken(response.token);
            navigate('/');
        } catch {
            setError('邮箱或密码错误');
        }
    }

    return (
        <main className="mx-auto max-w-sm p-8">
            <h1 className="mb-6 text-2xl font-semibold">登录</h1>
            <form className="space-y-4" onSubmit={handleSubmit}>
                <input
                    name="email"
                    type="email"
                    required
                    placeholder="邮箱"
                    className="w-full rounded border px-3 py-2"
                />
                <input
                    name="password"
                    type="password"
                    required
                    placeholder="密码"
                    className="w-full rounded border px-3 py-2"
                />
                {error && <p className="text-sm text-red-600">{error}</p>}
                <button
                    type="submit"
                    className="w-full rounded bg-neutral-900 px-3 py-2 text-white"
                >
                    登录
                </button>
            </form>
        </main>
    );
}
