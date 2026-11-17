import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { App as AntdApp } from 'antd';
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import type { Root } from 'react-dom/client';
import { afterEach, expect, it, vi } from 'vitest';
import EquipmentHistory from './index';
import { http } from '@/api/client';
import { useAuthStore } from '@/stores/auth';
import { currentUserFixture } from '@/test/fixtures';

vi.mock('@/components/echart', () => ({
    default: () => <div data-testid="chart" />,
}));

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
const requests: { url?: string; params?: Record<string, unknown> }[] = [];

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
    production_line_id: 2,
    owner_id: 3,
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
        requests.push({ url: config.url, params: config.params as Record<string, unknown> });
        let data: unknown = { items: [] };

        if (config.url === '/equipments/monitor') {
            data = {
                items: [equipment],
                max_history_seconds: 31 * 24 * 60 * 60,
            };
        }

        if (config.url === '/equipments/1/history') {
            data = {
                equipment,
                start_at: '2026-11-16T00:00:00Z',
                end_at: '2026-11-16T06:00:00Z',
                items: [{
                    recorded_at: '2026-11-16T05:00:00Z',
                    temperature: 66.5,
                    speed: 1320,
                    pressure: 0.82,
                    output: 912,
                }],
            };
        }

        return { config, status: 200, statusText: 'OK', headers: {}, data: { code: 200, data } };
    };
    await act(async () => {
        root.render(
            <QueryClientProvider client={client}>
                <AntdApp><EquipmentHistory /></AntdApp>
            </QueryClientProvider>,
        );
    });
    // The equipment list resolves first, then the selected device starts the
    // history query, so the two queries need one flush each.
    await act(async () => { await new Promise((resolve) => setTimeout(resolve, 0)); });
    await act(async () => { await new Promise((resolve) => setTimeout(resolve, 0)); });
}

afterEach(() => {
    act(() => root?.unmount());
    client?.clear();
    http.defaults.adapter = originalAdapter;
    document.body.innerHTML = '';
});

it('shows the four metric curves and the samples of the selected equipment', async () => {
    await render(['equipment.history.index']);

    expect(document.body.textContent).toContain('设备历史数据');
    expect(document.querySelectorAll('[data-testid="chart"]')).toHaveLength(4);
    expect(document.body.textContent).toContain('66.5');
    expect(document.body.textContent).toContain('采样明细');

    const history = requests.find((request) => request.url === '/equipments/1/history');

    expect(history?.params).toMatchObject({
        start_at: expect.any(String),
        end_at: expect.any(String),
    });
});

it('loads the equipment list for users who only hold the history permission', async () => {
    await render(['equipment.history.index']);

    expect(requests.some((request) => request.url === '/equipments/monitor')).toBe(true);
    expect(requests.some((request) => request.url === '/equipments/1/history')).toBe(true);
});

it('warns and loads nothing without the history permission', async () => {
    await render([]);

    expect(document.body.textContent).toContain('没有设备历史数据权限');
    expect(requests).toHaveLength(0);
});
