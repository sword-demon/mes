import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { App as AntdApp } from 'antd';
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import type { Root } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import ProcessRoutes from './index';
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

const route = {
    id: 8,
    code: 'ROUTE-P001',
    product_id: 2,
    version: 'V1.0',
    audit_status: 'draft' as const,
    audited_at: null,
    remark: null,
    status: true,
    product: { id: 2, code: 'P001', name: '智能电表' },
};

let requested: string[] = [];
let root: Root;
let client: QueryClient;
const originalAdapter = http.defaults.adapter;

async function renderRoutes() {
    const container = document.createElement('div');
    document.body.append(container);
    client = new QueryClient({ defaultOptions: { queries: { retry: false } } });

    await act(async () => {
        root = createRoot(container);
        root.render(
            <QueryClientProvider client={client}>
                <AntdApp><ProcessRoutes /></AntdApp>
            </QueryClientProvider>,
        );
    });

    return container;
}

describe('工艺路线页', () => {
    beforeEach(() => {
        requested = [];
        http.defaults.adapter = async (config) => {
            requested.push(config.url ?? '');
            return {
                config,
                status: 200,
                statusText: 'OK',
                headers: {},
                data: {
                    code: 200,
                    message: 'success',
                    data: config.url?.endsWith('/operations') ? [{ id: 5, process_id: 1, sequence: 3, standard_hours: 2, parameters: null, process: { code: 'OP01', name: '装配', type: '加工', requires_inspection: true } }] : { items: [route], meta: { current_page: 1, per_page: 15, total: 1, last_page: 1 } },
                    timestamp: 0,
                },
            };
        };
    });

    afterEach(() => {
        act(() => root?.unmount());
        client?.clear();
        http.defaults.adapter = originalAdapter;
        document.body.innerHTML = '';
    });

    it('lists routes and opens operation configuration', async () => {
        useAuthStore.setState({ token: 'token', user: currentUserFixture({ permissions: ['process.routes.index'] }) });

        await renderRoutes();
        await act(async () => { await new Promise((resolve) => setTimeout(resolve, 0)); });

        expect(document.body.textContent).toContain('ROUTE-P001');
        expect(document.body.textContent).toContain('智能电表');
        expect(requested).toContain('/process/routes');
        await act(async () => {
            [...document.querySelectorAll('button')].find((button) => button.textContent === '工序配置')?.click();
        });
        await act(async () => { await new Promise((resolve) => setTimeout(resolve, 30)); });
        expect(requested).toContain('/process/routes/8/operations');
        expect(document.body.textContent).toContain('装配');
    });

    it('hides route mutations without their permissions', async () => {
        useAuthStore.setState({ token: 'token', user: currentUserFixture({ permissions: ['process.routes.index'] }) });

        await renderRoutes();
        await act(async () => { await new Promise((resolve) => setTimeout(resolve, 0)); });

        expect([...document.querySelectorAll('button')].some((button) => button.textContent?.includes('新增路线'))).toBe(false);
        expect([...document.querySelectorAll('button')].some((button) => button.textContent?.includes('编辑'))).toBe(false);
    });
});
