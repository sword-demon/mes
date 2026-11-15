import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { App as AntdApp } from 'antd';
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import type { Root } from 'react-dom/client';
import { afterEach, expect, it } from 'vitest';
import InventoryAlertsPage from './index';
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
let requests: { url?: string; method?: string; params?: unknown }[] = [];
const alert = {
    id: 1,
    material_id: 1,
    min_stock: 20,
    current_quantity: 5,
    status: 'pending',
    status_label: '待确认',
    handled_by: null,
    handled_at: null,
    resolved_at: null,
    material: { id: 1, code: 'M001', name: '冷轧钢板', unit: 'kg' },
    created_at: '2026-11-15T00:00:00Z',
    updated_at: '2026-11-15T00:00:00Z',
};

async function render(permissions: string[]) {
    useAuthStore.setState({ token: 'token', user: currentUserFixture({ permissions }) });
    const container = document.createElement('div');
    document.body.append(container);
    root = createRoot(container);
    client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
    http.defaults.adapter = async (config) => {
        requests.push({ url: config.url, method: config.method, params: config.params });
        const responseAlert = requests.some((request) => request.url === '/inventory/alerts/1/close')
            ? { ...alert, status: 'closed', status_label: '已关闭' }
            : requests.some((request) => request.url === '/inventory/alerts/1/confirm')
                ? { ...alert, status: 'confirmed', status_label: '已确认' }
                : alert;

        return {
            config,
            status: 200,
            statusText: 'OK',
            headers: {},
            data: {
                code: 200,
                data: config.url === '/inventory/alerts/1/confirm' || config.url === '/inventory/alerts/1/close'
                    ? responseAlert
                    : {
                        items: responseAlert.status === 'closed' ? [] : [responseAlert],
                        meta: { current_page: 1, per_page: 15, total: responseAlert.status === 'closed' ? 0 : 1, last_page: 1 },
                    },
            },
        };
    };
    await act(async () => {
        root.render(<QueryClientProvider client={client}><AntdApp><InventoryAlertsPage /></AntdApp></QueryClientProvider>);
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

it('lists active low-stock alerts and offers actions only to authorized users', async () => {
    await render(['inventory.alerts.index', 'button.inventory.alerts.confirm']);
    const buttons = [...document.querySelectorAll('button')].map((button) => button.textContent?.trim());

    expect(document.body.textContent).toContain('M001');
    expect(document.body.textContent).toContain('冷轧钢板');
    expect(document.body.textContent).toContain('5');
    expect(document.body.textContent).toContain('20');
    expect(buttons).toContain('确认');
    expect(buttons).not.toContain('关闭');
});

it('confirms and closes an alert through its protected API actions', async () => {
    await render([
        'inventory.alerts.index',
        'button.inventory.alerts.confirm',
        'button.inventory.alerts.close',
    ]);
    const buttons = () => [...document.querySelectorAll('button')];
    const confirm = buttons().find((button) => button.textContent?.trim() === '确认');

    await act(async () => {
        (confirm as HTMLButtonElement).click();
        await new Promise((resolve) => setTimeout(resolve, 100));
    });

    expect(requests.some((request) => request.url === '/inventory/alerts/1/confirm' && request.method === 'post')).toBe(true);
    expect(document.body.textContent).toContain('已确认');

    const close = buttons().find((button) => button.textContent?.trim() === '关闭');

    await act(async () => {
        (close as HTMLButtonElement).click();
        await new Promise((resolve) => setTimeout(resolve, 100));
    });

    expect(requests.some((request) => request.url === '/inventory/alerts/1/close' && request.method === 'post')).toBe(true);
    expect(document.body.textContent).toContain('暂无待处理库存预警');
});
