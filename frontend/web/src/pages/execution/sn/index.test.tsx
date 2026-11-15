import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { App as AntdApp } from 'antd';
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import ProductSnPage from './index';
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

const meta = { current_page: 1, per_page: 15, total: 1, last_page: 1 };
const productSn = {
    id: 1,
    sn: 'MES20261115000001',
    production_order_id: 11,
    production_batch_id: 3,
    material_batch_no: 'MAT-LOT-001',
    equipment_id: null,
    employee_id: 5,
    status: 'active',
    status_label: '在用',
    printed_at: null,
    voided_at: null,
    void_reason: null,
    production_order: {
        id: 11,
        code: 'PO-DEMO-001',
        planned_quantity: '100.000',
        product: { id: 1, code: 'P001', name: '智能电表' },
    },
    production_batch: { id: 3, code: 'PO-DEMO-001-L01', quantity: 5 },
    employee: { id: 5, employee_no: 'EMP004', name: '李秀兰' },
    created_at: '2026-11-15T08:00:00Z',
};

async function renderPage() {
    const container = document.createElement('div');
    document.body.append(container);
    const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });

    await act(async () => {
        createRoot(container).render(
            <QueryClientProvider client={queryClient}>
                <AntdApp>
                    <ProductSnPage />
                </AntdApp>
            </QueryClientProvider>,
        );
    });

    await act(async () => {
        await new Promise((resolve) => setTimeout(resolve, 0));
    });
}

describe('SN 管理页', () => {
    beforeEach(() => {
        http.defaults.adapter = async (config) => {
            let data: unknown = { items: [], meta: { ...meta, total: 0 } };

            if (config.url === '/production/sns') {
                data = { items: [productSn], meta };
            }

            if (config.url === '/production/sns/options') {
                data = { orders: [], employees: [] };
            }

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

    it('shows serial numbers to a user who can read them', async () => {
        useAuthStore.setState({
            token: 'token',
            user: currentUserFixture({ permissions: ['production.sns.index'] }),
        });

        await renderPage();

        expect(document.body.textContent).toContain('MES20261115000001');
        expect(document.body.textContent).toContain('PO-DEMO-001-L01');
        expect(document.body.textContent).toContain('MAT-LOT-001');
        expect(document.body.textContent).not.toContain('生成 SN');
        expect(document.body.textContent).not.toContain('作废');
    });

    it('offers generate, bind, label and void actions to a permitted operator', async () => {
        useAuthStore.setState({
            token: 'token',
            user: currentUserFixture({
                permissions: [
                    'production.sns.index',
                    'button.production.sns.create',
                    'button.production.sns.bind',
                    'button.production.sns.print',
                    'button.production.sns.void',
                ],
            }),
        });

        await renderPage();

        expect(document.body.textContent).toContain('生成 SN');
        expect(document.body.textContent).toContain('绑定');
        expect(document.body.textContent).toContain('标签');
        expect(document.body.textContent).toContain('作废');
    });
});
