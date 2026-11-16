import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { App as AntdApp } from 'antd';
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import type { Root } from 'react-dom/client';
import { afterEach, expect, it } from 'vitest';
import EquipmentAlarmsPage from './index';
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
let requests: { url?: string; method?: string }[] = [];
const alarm = {
    id: 1,
    code: 'ALM-DEMO-009',
    equipment_id: 1,
    equipment_data_id: 10,
    metric: 'speed',
    metric_label: '转速',
    metric_unit: 'rpm',
    level: 'warning',
    level_label: '警告',
    status: 'pending',
    status_label: '待确认',
    threshold: 1350,
    measured_value: 1388,
    handling_note: null,
    confirmed_by: null,
    confirmed_at: null,
    handled_by: null,
    handled_at: null,
    closed_at: null,
    triggered_at: '2026-11-16T02:00:00Z',
    equipment: {
        id: 1,
        code: 'EQ-SMT-01',
        name: 'SMT 贴片机一号',
        type: 'SMT 贴片机',
        status: 'running',
        status_label: '运行',
    },
    created_at: '2026-11-16T02:00:00Z',
    updated_at: '2026-11-16T02:00:00Z',
};

async function render(permissions: string[]) {
    useAuthStore.setState({ token: 'token', user: currentUserFixture({ permissions }) });
    const container = document.createElement('div');
    document.body.append(container);
    root = createRoot(container);
    client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
    http.defaults.adapter = async (config) => {
        requests.push({ url: config.url, method: config.method });
        const confirmed = requests.some((request) => request.url === '/equipments/alarms/1/confirm');
        const responseAlarm = confirmed
            ? { ...alarm, status: 'confirmed', status_label: '已确认' }
            : alarm;

        return {
            config,
            status: 200,
            statusText: 'OK',
            headers: {},
            data: {
                code: 200,
                data: config.url === '/equipments/alarms/1/confirm'
                    ? responseAlarm
                    : config.url === '/equipments/alarms/options'
                        ? { equipments: [alarm.equipment] }
                        : {
                            items: [responseAlarm],
                            meta: { current_page: 1, per_page: 15, total: 1, last_page: 1 },
                        },
            },
        };
    };
    await act(async () => {
        root.render(<QueryClientProvider client={client}><AntdApp><EquipmentAlarmsPage /></AntdApp></QueryClientProvider>);
    });
    await act(async () => { await new Promise((resolve) => setTimeout(resolve, 50)); });
}

afterEach(async () => {
    await act(async () => root?.unmount());
    client?.clear();
    http.defaults.adapter = originalAdapter;
    requests = [];
    document.body.innerHTML = '';
});

it('lists equipment alarms and only offers actions the user may run', async () => {
    await render(['equipment.alarms.index']);
    const buttons = [...document.querySelectorAll('button')].map((button) => button.textContent?.trim());

    expect(document.body.textContent).toContain('ALM-DEMO-009');
    expect(document.body.textContent).toContain('EQ-SMT-01');
    expect(document.body.textContent).toContain('转速 1388 / 阈值 1350 rpm');
    expect(document.body.textContent).toContain('警告');
    expect(document.body.textContent).toContain('待确认');
    expect(buttons).not.toContain('确认');
});

it('confirms a pending alarm through the protected API action', async () => {
    await render(['equipment.alarms.index', 'button.equipment.alarms.confirm']);
    const confirm = [...document.querySelectorAll('button')]
        .find((button) => button.textContent?.trim() === '确认');

    await act(async () => {
        (confirm as HTMLButtonElement).click();
        await new Promise((resolve) => setTimeout(resolve, 100));
    });

    expect(requests.some((request) => request.url === '/equipments/alarms/1/confirm' && request.method === 'post')).toBe(true);
    expect(document.body.textContent).toContain('已确认');
});
