import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { App as AntdApp } from 'antd';
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import type { Root } from 'react-dom/client';
import { afterEach, expect, it } from 'vitest';
import QualityTasks from './index';
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

const task = {
    id: 1,
    inspection_type: 'ipqc',
    inspection_type_label: 'IPQC 过程检验',
    result: null,
    status: 'pending',
    result_label: '待检验',
    inspector: null,
    inspected_at: null,
    remark: null,
    production_order: { id: 1, code: 'PO-DEMO-001', product: { id: 1, code: 'P001', name: '电机' } },
    route_operation: { id: 1, sequence: 1, process: { id: 1, name: '装配' } },
    process_record: {
        id: 1,
        sequence: 1,
        sn: 'SN001',
    },
    items: [],
    required_items: [],
    images: [],
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
            data: config.url === '/quality/inspection-tasks/options'
                ? { orders: [task.production_order], process_records: [] }
                : {
                    items: [task],
                    meta: { current_page: 1, per_page: 15, total: 1, last_page: 1 },
                },
        },
    });
    await act(async () => {
        root.render(<QueryClientProvider client={client}><AntdApp><QualityTasks /></AntdApp></QueryClientProvider>);
    });
    await act(async () => { await new Promise((resolve) => setTimeout(resolve, 30)); });
}

afterEach(async () => {
    await act(async () => root?.unmount());
    client?.clear();
    http.defaults.adapter = originalAdapter;
    document.body.innerHTML = '';
});

it('lists pending inspection tasks and offers creation only to creators', async () => {
    await render(['quality.inspections.index', 'button.quality.inspections.create']);

    expect(document.body.textContent).toContain('PO-DEMO-001');
    expect(document.body.textContent).toContain('待检验');
    expect(document.body.textContent).toContain('新建检验任务');
    expect(document.body.textContent).not.toContain('录入结果');
});

it('offers result entry to inspectors without the create button', async () => {
    await render(['quality.inspections.index', 'button.quality.inspections.record']);

    expect(document.body.textContent).toContain('录入结果');
    expect(document.body.textContent).not.toContain('新建检验任务');
});
