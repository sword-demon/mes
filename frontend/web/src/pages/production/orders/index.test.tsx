import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { App as AntdApp } from 'antd';
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import ProductionOrders from './index';
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

type Call = { method: string; url: string };

let calls: Call[] = [];

const meta = { current_page: 1, per_page: 15, total: 1, last_page: 1 };
const baseOrder = {
    id: 11,
    code: 'PO-2026-001',
    product_id: 1,
    product_version_id: 1,
    bom_id: 1,
    route_id: 1,
    production_line_id: 2,
    bom_version: 'B1.0',
    route_version: 'R1.0',
    planned_quantity: '100.000',
    produced_quantity: '0.000',
    qualified_quantity: '0.000',
    defective_quantity: '0.000',
    planned_start_at: '2026-11-16T08:00:00Z',
    planned_end_at: null,
    status: 'approved',
    status_label: '已审核',
    allowed_actions: ['release', 'cancel'],
    editable: false,
    can_split: false,
    can_requisition: false,
    remark: null,
    product: { id: 1, code: 'P001', name: '智能电表' },
    production_line: { id: 2, code: 'PL01', name: '一号线' },
};
let order = { ...baseOrder };

async function renderPage() {
    const container = document.createElement('div');
    document.body.append(container);
    const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });

    await act(async () => {
        createRoot(container).render(
            <QueryClientProvider client={queryClient}>
                <AntdApp>
                    <ProductionOrders />
                </AntdApp>
            </QueryClientProvider>,
        );
    });

    await act(async () => {
        await new Promise((resolve) => setTimeout(resolve, 0));
    });
}

describe('生产订单页', () => {
    beforeEach(() => {
        calls = [];
        order = { ...baseOrder };
        http.defaults.adapter = async (config) => {
            calls.push({ method: config.method ?? 'get', url: config.url ?? '' });

            const data = config.url === '/basic-data/products'
                ? { items: [{ id: 1, code: 'P001', name: '智能电表' }], meta }
                : config.url === '/basic-data/production-lines'
                  ? { items: [{ id: 2, code: 'PL01', name: '一号线' }], meta }
                  : { items: [order], meta };

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

    it('shows the order without actions the user cannot perform', async () => {
        useAuthStore.setState({
            token: 'token',
            user: currentUserFixture({ permissions: ['production.orders.index'] }),
        });

        await renderPage();

        expect(document.body.textContent).toContain('PO-2026-001');
        expect(document.body.textContent).not.toContain('新增');
        expect(document.body.textContent).not.toContain('下达');
        expect(document.body.textContent).not.toContain('编辑');
    });

    it('releases an approved order through its dedicated endpoint', async () => {
        useAuthStore.setState({
            token: 'token',
            user: currentUserFixture({
                permissions: ['production.orders.index', 'button.production.orders.release'],
            }),
        });

        await renderPage();

        await act(async () => {
            [...document.querySelectorAll<HTMLButtonElement>('button')]
                .find((button) => button.textContent === '下达')
                ?.click();
            await new Promise((resolve) => setTimeout(resolve, 0));
        });

        expect(calls).toContainEqual({ method: 'post', url: '/production/orders/11/release' });
    });

    it('renders actions from allowed_actions instead of the raw status', async () => {
        order = {
            ...baseOrder,
            allowed_actions: ['submit'],
            editable: true,
        };
        useAuthStore.setState({
            token: 'token',
            user: currentUserFixture({
                permissions: [
                    'production.orders.index',
                    'button.production.orders.submit',
                    'button.production.orders.release',
                    'button.production.orders.update',
                ],
            }),
        });

        await renderPage();

        expect(document.body.textContent).toContain('提交审核');
        expect(document.body.textContent).toContain('编辑');
        expect(document.body.textContent).not.toContain('下达');
    });
});
