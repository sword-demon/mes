import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { App as AntdApp } from 'antd';
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import type { Root } from 'react-dom/client';
import { afterEach, expect, it } from 'vitest';
import EquipmentMonitor from './index';
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

const equipment = {
    id: 1,
    code: 'EQ-SMT-01',
    name: 'SMT 贴片机一号',
    type: 'SMT 贴片机',
    model: 'SMT-X200',
    status: 'running',
    status_label: '运行',
    enabled: true,
    workshop_id: 1,
    workshop: { id: 1, code: 'WS01', name: '一号车间' },
    production_line_id: 2,
    production_line: { id: 2, code: 'PL01', name: 'SMT 一线' },
    owner_id: 3,
    owner: { id: 3, employee_no: 'EMP002', name: '刘志强' },
    latest_data: {
        recorded_at: '2026-11-16T05:00:00Z',
        temperature: 66.5,
        speed: 1320,
        pressure: 0.82,
        output: 912,
        runtime_seconds: 3600,
    },
    created_at: '2026-11-16T00:00:00Z',
    updated_at: '2026-11-16T00:00:00Z',
};

async function render(permissions: string[]) {
    requests.length = 0;
    useAuthStore.setState({ token: 'token', user: currentUserFixture({ permissions }) });
    const container = document.createElement('div');
    document.body.append(container);
    root = createRoot(container);
    client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
    http.defaults.adapter = async (config) => {
        requests.push(config.url ?? '');
        const data = config.url === '/equipments/monitor'
            ? {
                  items: [equipment],
                  status_counts: { running: 1, idle: 0, fault: 0, repair: 0, offline: 0 },
                  max_history_seconds: 31 * 24 * 60 * 60,
              }
            : { items: [] };

        return { config, status: 200, statusText: 'OK', headers: {}, data: { code: 200, data } };
    };
    await act(async () => {
        root.render(
            <QueryClientProvider client={client}>
                <AntdApp><EquipmentMonitor /></AntdApp>
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

it('shows the equipment status and its latest metrics', async () => {
    await render(['equipment.monitor.index']);

    expect(document.body.textContent).toContain('EQ-SMT-01');
    expect(document.body.textContent).toContain('SMT 贴片机一号');
    expect(document.body.textContent).toContain('66.5');
    expect(document.body.textContent).toContain('1320');
    expect(document.querySelector('[data-testid="status-count-running"]')?.textContent).toContain('1');
});

it('keeps history data out of the monitoring page', async () => {
    await render(['equipment.monitor.index']);

    expect(requests).not.toContain('/equipments/1/history');
    expect(document.body.textContent).not.toContain('历史曲线');
});

it('warns and loads nothing without the monitoring permission', async () => {
    await render([]);

    expect(document.body.textContent).toContain('没有设备监控权限');
    expect(requests).toHaveLength(0);
});
