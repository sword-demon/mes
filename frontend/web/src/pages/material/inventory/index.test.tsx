import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { App as AntdApp } from 'antd';
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import type { Root } from 'react-dom/client';
import { afterEach, expect, it } from 'vitest';
import InventoryPage from './index';
import MaterialInbound from '../inbound';
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

const warehouse = { id: 1, code: 'WH01', name: '原料仓' };
const location = { id: 1, code: 'A-01', name: '库位 A-01', warehouse };
const material = { id: 1, code: 'M001', name: '冷轧钢板', unit: 'kg', batch_managed: true };
const inventory = {
    id: 1,
    material_id: 1,
    location_id: 1,
    batch_no: 'B001',
    current_quantity: 100,
    frozen_quantity: 10,
    available_quantity: 90,
    material,
    location,
    created_at: '2026-11-15T00:00:00Z',
    updated_at: '2026-11-15T00:00:00Z',
};
const transaction = {
    id: 1,
    inventory_id: 1,
    material_id: 1,
    business_type: 'inbound',
    business_type_label: '入库',
    quantity: 100,
    before_quantity: 0,
    after_quantity: 100,
    reference_no: 'PO-001',
    remark: null,
    material,
    inventory: { id: 1, batch_no: 'B001', location },
    created_at: '2026-11-15T00:00:00Z',
};

async function render(page: 'stock' | 'inbound', permissions: string[]) {
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
                data: config.url === '/inventory/options'
                    ? { materials: [material], warehouses: [{ ...warehouse, locations: [location] }] }
                    : config.url === '/inventory/transactions'
                        ? { items: [transaction], meta: { current_page: 1, per_page: 15, total: 1, last_page: 1 } }
                        : { items: [inventory], meta: { current_page: 1, per_page: 15, total: 1, last_page: 1 } },
            },
        };
    };
    await act(async () => {
        root.render(
            <QueryClientProvider client={client}>
                <AntdApp>
                    {page === 'stock' ? <InventoryPage /> : <MaterialInbound />}
                </AntdApp>
            </QueryClientProvider>,
        );
    });
    await act(async () => { await new Promise((resolve) => setTimeout(resolve, 30)); });
}

afterEach(async () => {
    await act(async () => root?.unmount());
    client?.clear();
    http.defaults.adapter = originalAdapter;
    requests = [];
    document.body.innerHTML = '';
});

it('lists stock with quantities and only offers movement actions the user holds', async () => {
    await render('stock', ['inventory.index', 'button.inventory.inbound']);
    const buttons = [...document.querySelectorAll('button')]
        .map((button) => button.textContent?.replace(/\s+/g, '') ?? '');

    expect(document.body.textContent).toContain('M001');
    expect(document.body.textContent).toContain('冷轧钢板');
    expect(document.body.textContent).toContain('原料仓');
    expect(document.body.textContent).toContain('A-01');
    expect(document.body.textContent).toContain('90');
    expect(buttons).toContain('入库');
    expect(buttons).not.toContain('出库');
    expect(buttons).not.toContain('库存调整');
});

it('shows the ledger on the transactions tab', async () => {
    await render('stock', ['inventory.index']);
    const tab = [...document.querySelectorAll('[role="tab"]')].find((item) => item.textContent === '流水');

    await act(async () => {
        (tab as HTMLElement).click();
        await new Promise((resolve) => setTimeout(resolve, 30));
    });

    expect(document.body.textContent).toContain('PO-001');
    expect(document.body.textContent).toContain('+100');
});

it('offers only the outbound action to an outbound-only user', async () => {
    await render('stock', ['inventory.index', 'button.inventory.outbound']);
    const buttons = [...document.querySelectorAll('button')]
        .map((button) => button.textContent?.replace(/\s+/g, '') ?? '');

    expect(buttons).toContain('出库');
    expect(buttons).not.toContain('入库');
    expect(buttons).not.toContain('库存调整');
});

it('submits a prefilled adjustment with the record position and target quantity', async () => {
    await render('stock', ['inventory.index', 'button.inventory.adjust']);
    const buttons = () => [...document.querySelectorAll('button')];
    const adjust = buttons().find((button) => button.textContent?.replace(/\s+/g, '') === '调整');

    await act(async () => {
        (adjust as HTMLElement).click();
        await new Promise((resolve) => setTimeout(resolve, 30));
    });

    const confirm = buttons().find((button) => button.textContent?.replace(/\s+/g, '') === '确认调整');

    await act(async () => {
        (confirm as HTMLElement).click();
        await new Promise((resolve) => setTimeout(resolve, 50));
    });

    const request = requests.find((item) => item.url === '/inventory/adjust' && item.method === 'post');
    const body = typeof request?.data === 'string' ? JSON.parse(request.data) : request?.data;

    expect(body).toMatchObject({
        material_id: 1,
        warehouse_id: 1,
        location_id: 1,
        batch_no: 'B001',
        current_quantity: 100,
    });
});

it('renders the inbound page as a ledger for a movement-only user', async () => {
    await render('inbound', ['button.inventory.inbound']);
    const buttons = [...document.querySelectorAll('button')]
        .map((button) => button.textContent?.replace(/\s+/g, '') ?? '');

    expect(document.body.textContent).toContain('入库管理');
    expect(document.body.textContent).toContain('PO-001');
    expect(buttons).toContain('入库');
    expect(buttons).not.toContain('库存调整');
});
