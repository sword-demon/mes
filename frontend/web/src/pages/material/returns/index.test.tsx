import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { App as AntdApp } from 'antd';
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import type { Root } from 'react-dom/client';
import { afterEach, expect, it } from 'vitest';
import MaterialReturns from './index';
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

const materialReturn = {
    id: 1,
    code: 'RT-100001',
    requisition_id: 11,
    requisition: { id: 11, code: 'MR-100001' },
    production_order_id: 1,
    production_order: { id: 1, code: 'PO-100001' },
    status: 'pending',
    status_label: '待审核',
    remark: null,
    created_by_id: 1,
    items: [{
        id: 21,
        requisition_item_id: 31,
        material_id: 1,
        material: { id: 1, code: 'M001', name: '冷轧钢板', unit: 'kg' },
        quantity: 1.5,
    }],
    approved_by: null,
    approved_at: null,
    created_at: '2026-11-16T01:00:00Z',
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
            data: config.url?.startsWith('/materials/returns')
                ? { items: [materialReturn], meta: { current_page: 1, per_page: 15, total: 1, last_page: 1 } }
                : { items: [{ id: 1, code: 'PO-100001' }], meta: { current_page: 1, per_page: 100, total: 1, last_page: 1 } },
        },
    });
    await act(async () => {
        root.render(
            <QueryClientProvider client={client}>
                <AntdApp><MaterialReturns /></AntdApp>
            </QueryClientProvider>,
        );
    });
    await act(async () => { await new Promise((resolve) => setTimeout(resolve, 30)); });
}

afterEach(() => {
    act(() => root?.unmount());
    client?.clear();
    http.defaults.adapter = originalAdapter;
    document.body.innerHTML = '';
});

it('lists returns and hides auditing without the audit permission', async () => {
    await render(['material.returns.index', 'button.material.returns.create']);

    expect(document.body.textContent).toContain('RT-100001');
    expect(document.body.textContent).toContain('MR-100001');
    expect(document.body.textContent).toContain('创建退料单');
    expect([...document.querySelectorAll('button')].some((button) => button.textContent?.includes('撤销'))).toBe(true);
    expect([...document.querySelectorAll('button')].some((button) => button.textContent?.includes('审核'))).toBe(false);
});

it('shows auditing for the audit permission', async () => {
    await render(['material.returns.index', 'button.material.returns.audit']);

    expect(document.body.textContent).toContain('待审核');
    expect([...document.querySelectorAll('button')].some((button) => button.textContent?.includes('撤销'))).toBe(false);
    expect([...document.querySelectorAll('button')].some((button) => button.textContent?.includes('审核'))).toBe(true);
});
