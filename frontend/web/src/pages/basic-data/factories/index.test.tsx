import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { App as AntdApp } from 'antd';
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import Factories from './index';
import { http } from '@/api/client';
import { useAuthStore } from '@/stores/auth';
import { currentUserFixture } from '@/test/fixtures';

(globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;

window.matchMedia ??= ((query: string) => ({
    matches: false,
    media: query,
    onchange: null,
    addListener: () => undefined,
    removeListener: () => undefined,
    addEventListener: () => undefined,
    removeEventListener: () => undefined,
    dispatchEvent: () => false,
})) as unknown as typeof window.matchMedia;

// antd's Popconfirm measures its anchor with ResizeObserver, which jsdom lacks.
globalThis.ResizeObserver ??= class {
    observe() {}
    unobserve() {}
    disconnect() {}
};

type Call = { method: string; url: string; body?: unknown };

let calls: Call[] = [];

async function renderPage() {
    const container = document.createElement('div');
    document.body.append(container);

    const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });

    await act(async () => {
        createRoot(container).render(
            <QueryClientProvider client={queryClient}>
                <AntdApp>
                    <Factories />
                </AntdApp>
            </QueryClientProvider>,
        );
    });

    // Let the list query resolve and paint.
    await act(async () => {
        await new Promise((resolve) => setTimeout(resolve, 0));
    });

    return container;
}

describe('工厂管理页', () => {
    beforeEach(() => {
        calls = [];
        http.defaults.adapter = async (config) => {
            calls.push({
                method: config.method ?? 'get',
                url: config.url ?? '',
                body: config.data ? JSON.parse(String(config.data)) : undefined,
            });

            const data = {
                items: [
                    {
                        id: 7,
                        code: 'FAC01',
                        name: '演示工厂',
                        address: null,
                        contact: null,
                        phone: null,
                        status: true,
                    },
                ],
                meta: { current_page: 1, per_page: 15, total: 1, last_page: 1 },
            };

            return {
                data: { code: 200, message: 'success', data, timestamp: 0 },
                status: 200,
                statusText: '',
                headers: {},
                config,
            };
        };
    });

    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('hides the create button and row actions without permissions', async () => {
        useAuthStore.setState({
            token: 'token',
            user: currentUserFixture({ permissions: ['basic.factories.index'] }),
        });

        await renderPage();

        expect(document.body.textContent).toContain('FAC01');
        expect(document.body.textContent).not.toContain('新增');
        expect(document.body.textContent).not.toContain('停用');
    });

    it('disables a factory through a PUT that only flips status', async () => {
        useAuthStore.setState({
            token: 'token',
            user: currentUserFixture({
                permissions: ['basic.factories.index', 'button.basic.factories.update'],
            }),
        });

        await renderPage();

        await act(async () => {
            document.body.querySelector<HTMLButtonElement>('button.ant-btn-dangerous')?.click();
            await new Promise((resolve) => setTimeout(resolve, 0));
        });

        await act(async () => {
            document.body.querySelector<HTMLButtonElement>('.ant-popconfirm-buttons .ant-btn-primary')?.click();
            await new Promise((resolve) => setTimeout(resolve, 0));
        });

        const put = calls.find((call) => call.method === 'put');

        expect(put?.url).toBe('/basic-data/factories/7');
        expect(put?.body).toEqual({ status: false });
    });
});
