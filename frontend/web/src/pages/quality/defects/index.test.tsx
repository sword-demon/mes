import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { App as AntdApp } from 'antd';
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import type { Root } from 'react-dom/client';
import { afterEach, expect, it } from 'vitest';
import DefectsPage from './index';
import { http } from '@/api/client';
import { useAuthStore } from '@/stores/auth';
import { currentUserFixture } from '@/test/fixtures';

(globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
window.matchMedia ??= ((query: string) => ({
    matches: false, media: query, onchange: null,
    addListener() {}, removeListener() {}, addEventListener() {}, removeEventListener() {},
    dispatchEvent: () => false,
})) as unknown as typeof window.matchMedia;
globalThis.ResizeObserver ??= class {
    observe() {}
    unobserve() {}
    disconnect() {}
};

let root: Root;
let client: QueryClient;
const originalAdapter = http.defaults.adapter;

const order = { id: 1, code: 'PO-DEMO-001', product: { id: 1, code: 'P001', name: '电机' } };
const defect = {
    id: 1,
    status: 'processing',
    status_label: '处理中',
    quantity: '3.000',
    remark: null,
    production_order: order,
    route_operation: { id: 1, sequence: 1, process: { id: 1, code: 'OP1', name: '装配' } },
    production_batch: { id: 1, code: 'LOT-001', quantity: 10 },
    defect_code: { id: 1, code: 'D001', name: '表面划伤', category: '外观' },
    handled_by: null,
    rework_orders: [],
    recheck_result: null,
    recheck_remark: null,
    rechecked_at: null,
    scrap_reason: null,
    scrap_quantity: null,
    scrapped_at: null,
    created_at: '2026-11-15T00:00:00Z',
    updated_at: '2026-11-15T00:00:00Z',
};

async function render(permissions: string[]) {
    useAuthStore.setState({ token: 'token', user: currentUserFixture({ permissions }) });
    const container = document.createElement('div');
    document.body.append(container);
    root = createRoot(container);
    client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
    http.defaults.adapter = async (config) => ({
        config,
        status: 200,
        statusText: 'OK',
        headers: {},
        data: {
            code: 200,
            data: config.url === '/quality/defective-products/options'
                ? { orders: [order], operations: [], batches: [], defect_codes: [] }
                : {
                    items: [defect],
                    meta: { current_page: 1, per_page: 15, total: 1, last_page: 1 },
                },
        },
    });
    await act(async () => {
        root.render(<QueryClientProvider client={client}><AntdApp><DefectsPage /></AntdApp></QueryClientProvider>);
    });
    await act(async () => { await new Promise((resolve) => setTimeout(resolve, 30)); });
}

afterEach(async () => {
    await act(async () => root?.unmount());
    client?.clear();
    http.defaults.adapter = originalAdapter;
    document.body.innerHTML = '';
});

it('lists defective products and offers registration only to creators', async () => {
    await render(['quality.defects.index', 'button.quality.defects.create']);
    const buttons = [...document.querySelectorAll('button')].map((button) => button.textContent);

    expect(document.body.textContent).toContain('PO-DEMO-001');
    expect(document.body.textContent).toContain('表面划伤');
    expect(document.body.textContent).toContain('处理中');
    expect(buttons).toContain('登记不良品');
    expect(buttons).not.toContain('返工');
});

it('offers rework and scrap to disposition holders', async () => {
    await render([
        'quality.defects.index',
        'button.quality.defects.rework',
        'button.quality.defects.scrap',
    ]);
    const buttons = [...document.querySelectorAll('button')].map((button) => button.textContent);

    expect(buttons).toContain('返工');
    expect(buttons).toContain('报废');
    expect(buttons).not.toContain('登记不良品');
});
