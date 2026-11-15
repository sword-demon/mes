import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { App as AntdApp } from 'antd';
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import type { Root } from 'react-dom/client';
import { afterEach, expect, it } from 'vitest';
import MaterialRequisitions from './index';
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
let requests: { url?: string; method?: string; data?: unknown }[] = [];

const requisition = {
    id: 1,
    code: 'MR-100001',
    production_order_id: 1,
    production_order: { id: 1, code: 'PO-100001' },
    status: 'pending',
    remark: null,
    items: [{
        id: 11,
        material_id: 1,
        material: { id: 1, code: 'M001', name: '冷轧钢板', unit: 'kg' },
        requested_quantity: 21,
        issued_quantity: 0,
        over_requirement: false,
    }],
    created_at: '2026-11-15T01:00:00Z',
};

async function render(permissions: string[]) {
    useAuthStore.setState({ token: 'token', user: currentUserFixture({ permissions }) });
    const container = document.createElement('div');
    document.body.append(container);
    root = createRoot(container);
    client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
    http.defaults.adapter = async (config) => {
        requests.push({ url: config.url, method: config.method, data: config.data });

        return {
            config,
            status: 200,
            statusText: 'OK',
            headers: {},
            data: {
                code: 200,
                data: config.url?.startsWith('/materials/requisitions')
                    ? { items: [requisition], meta: { current_page: 1, per_page: 15, total: 1, last_page: 1 } }
                    : { items: [{ id: 1, code: 'PO-100001', status: 'released', status_label: '已下达' }], meta: { current_page: 1, per_page: 100, total: 1, last_page: 1 } },
            },
        };
    };
    await act(async () => {
        root.render(
            <QueryClientProvider client={client}>
                <AntdApp><MaterialRequisitions /></AntdApp>
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
    requests = [];
});

it('lists requisitions and hides issuing without the warehouse permission', async () => {
    await render(['material.requisitions.index', 'button.material.requisitions.create']);

    expect(document.body.textContent).toContain('MR-100001');
    expect(document.body.textContent).toContain('PO-100001');
    expect(document.body.textContent).toContain('创建领料单');
    expect([...document.querySelectorAll('button')].some((button) => button.textContent?.includes('发料'))).toBe(false);
});

it('shows issuing for the warehouse permission', async () => {
    await render(['material.requisitions.index', 'button.material.requisitions.issue']);

    expect(document.body.textContent).toContain('发料');
    expect(requests.some((request) => request.url?.startsWith('/materials/requisitions'))).toBe(true);
});
