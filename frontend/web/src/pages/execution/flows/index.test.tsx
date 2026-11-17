import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { App as AntdApp } from 'antd';
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import type { Root } from 'react-dom/client';
import { afterEach, expect, it } from 'vitest';
import ProcessFlows from './index';
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
const requests: string[] = [];

const record = (id: number, status: string, statusLabel: string) => ({
    id,
    production_task_id: 11,
    route_operation_id: 3,
    sequence: 1,
    sn: 'MES-DEMO-000001',
    employee_id: 2,
    team_id: 4,
    equipment_id: 5,
    status,
    status_label: statusLabel,
    started_at: '2026-11-16T05:00:00Z',
    completed_at: null,
    inspected_at: null,
    submitted_at: null,
    received_at: null,
    exception_reason: null,
    exception_at: null,
    rework_reason: null,
    rework_at: null,
    rework_count: 0,
    production_task: { id: 11, code: 'PO-DEMO-001-T01', status: 'in_progress', status_label: '生产中' },
    production_order: { id: 1, code: 'PO-DEMO-001' },
    route_operation: {
        id: 3,
        sequence: 1,
        process: { id: 2, code: 'P-SMT', name: 'SMT 贴片', requires_inspection: true },
    },
    employee: { id: 2, employee_no: 'EMP002', name: '张三' },
    team: { id: 4, code: 'TEAM01', name: '一班' },
    equipment: null,
});

async function render(permissions: string[]) {
    requests.length = 0;
    useAuthStore.setState({ token: 'token', user: currentUserFixture({ permissions }) });
    const container = document.createElement('div');
    document.body.append(container);
    root = createRoot(container);
    client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
    http.defaults.adapter = async (config) => {
        requests.push(config.url ?? '');
        const data = config.url === '/production/process-records'
            ? {
                  items: [record(1, 'processing', '加工中'), record(2, 'transferred', '已流转')],
                  meta: { current_page: 1, per_page: 15, total: 2, last_page: 1 },
              }
            : { items: [] };

        return { config, status: 200, statusText: 'OK', headers: {}, data: { code: 200, data } };
    };
    await act(async () => {
        root.render(
            <QueryClientProvider client={client}>
                <AntdApp><ProcessFlows /></AntdApp>
            </QueryClientProvider>,
        );
    });
    await act(async () => { await new Promise((resolve) => setTimeout(resolve, 60)); });
}

afterEach(() => {
    act(() => root?.unmount());
    client?.clear();
    http.defaults.adapter = originalAdapter;
    document.body.innerHTML = '';
});

function buttons(): string[] {
    return [...document.body.querySelectorAll('button')].map((button) => button.textContent ?? '');
}

it('lists the process records with their status', async () => {
    await render(['production.flows.index']);

    expect(document.body.textContent).toContain('SMT 贴片');
    expect(document.body.textContent).toContain('加工中');
    expect(document.body.textContent).toContain('已流转');
    expect(requests).toContain('/production/process-records');
});

it('offers only the actions the current status allows', async () => {
    await render([
        'production.flows.index',
        'button.production.flows.complete',
        'button.production.flows.exception',
    ]);

    // The processing row can be finished or reported, the transferred row cannot.
    expect(buttons().filter((label) => label === '完成加工')).toHaveLength(1);
    expect(buttons().filter((label) => label === '上报异常')).toHaveLength(1);
    expect(buttons().filter((label) => label === '开始加工')).toHaveLength(0);
});

it('hides every action without the matching permission', async () => {
    await render(['production.flows.index']);

    expect(buttons().filter((label) => label === '完成加工')).toHaveLength(0);
    expect(buttons().filter((label) => label === '上报异常')).toHaveLength(0);
});

it('warns and loads nothing without the flow permission', async () => {
    await render([]);

    expect(document.body.textContent).toContain('没有工序流转权限');
    expect(requests).toHaveLength(0);
});
