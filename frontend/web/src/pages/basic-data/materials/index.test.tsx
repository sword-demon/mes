import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { App as AntdApp } from 'antd';
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import Materials from './index';
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

globalThis.ResizeObserver ??= class {
    observe() {}
    unobserve() {}
    disconnect() {}
};

function pageText() {
    return document.body.textContent?.replace(/\s/g, '') ?? '';
}

async function renderPage() {
    const container = document.createElement('div');
    document.body.append(container);

    const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });

    await act(async () => {
        createRoot(container).render(
            <QueryClientProvider client={queryClient}>
                <AntdApp>
                    <Materials />
                </AntdApp>
            </QueryClientProvider>,
        );
    });

    await act(async () => {
        await new Promise((resolve) => setTimeout(resolve, 0));
    });
}

describe('物料管理页', () => {
    beforeEach(() => {
        http.defaults.adapter = async (config) => {
            const data = {
                items: [
                    {
                        id: 7,
                        code: 'M001',
                        name: '冷轧钢板',
                        type: '原材料',
                        specification: 'SPCC 1.0mm',
                        unit: 'kg',
                        min_stock: 200,
                        safety_stock: 500,
                        batch_managed: true,
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

    it('shows stock thresholds and batch management, and hides writes without permissions', async () => {
        useAuthStore.setState({
            token: 'token',
            user: currentUserFixture({ permissions: ['basic.materials.index'] }),
        });

        await renderPage();

        expect(pageText()).toContain('M001');
        expect(pageText()).toContain('原材料');
        expect(pageText()).toContain('500');
        expect(pageText()).toContain('批次');
        expect(pageText()).not.toContain('新增');
        expect(pageText()).not.toContain('编辑');
        expect(pageText()).not.toContain('停用');
        expect(pageText()).not.toContain('删除');
    });

    it('offers creation with the material create permission', async () => {
        useAuthStore.setState({
            token: 'token',
            user: currentUserFixture({
                permissions: ['basic.materials.index', 'button.basic.materials.create'],
            }),
        });

        await renderPage();

        expect(pageText()).toContain('新增');
    });
});
