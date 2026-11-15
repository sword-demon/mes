import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { App as AntdApp } from 'antd';
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import type { Root } from 'react-dom/client';
import { afterEach, expect, it } from 'vitest';
import EquipmentLedger from './index';
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

const equipment = {
    id: 1,
    code: 'EQ-SMT-01',
    name: 'SMT 贴片机一号',
    type: 'SMT 贴片机',
    model: 'SMT-X200',
    status: 'running',
    status_label: '运行',
    workshop_id: 1,
    workshop: { id: 1, code: 'WS01', name: '一号车间' },
    production_line_id: 2,
    production_line: { id: 2, code: 'PL01', name: 'SMT 一线' },
    owner_id: 3,
    owner: { id: 3, employee_no: 'EMP002', name: '刘志强' },
    created_at: '2026-11-16T00:00:00Z',
    updated_at: '2026-11-16T00:00:00Z',
};

async function render(permissions: string[]) {
    useAuthStore.setState({ token: 'token', user: currentUserFixture({ permissions }) });
    const container = document.createElement('div');
    document.body.append(container);
    root = createRoot(container);
    client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
    http.defaults.adapter = async (config) => {
        let data: unknown = { items: [], meta: { current_page: 1, per_page: 15, total: 0, last_page: 1 } };

        if (config.url === '/equipments') {
            data = { items: [equipment], meta: { current_page: 1, per_page: 15, total: 1, last_page: 1 } };
        }

        if (config.url === '/equipments/options') {
            data = {
                workshops: [equipment.workshop],
                production_lines: [{ id: 2, workshop_id: 1, code: 'PL01', name: 'SMT 一线' }],
                employees: [{ id: 3, employee_no: 'EMP002', name: '刘志强' }],
                equipments: [equipment],
            };
        }

        return { config, status: 200, statusText: 'OK', headers: {}, data: { code: 200, data } };
    };
    await act(async () => {
        root.render(
            <QueryClientProvider client={client}>
                <AntdApp><EquipmentLedger /></AntdApp>
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

it('lists equipment and hides editing without the update permission', async () => {
    await render(['equipment.ledger.index', 'button.equipment.ledger.create']);

    expect(document.body.textContent).toContain('EQ-SMT-01');
    expect(document.body.textContent).toContain('SMT 贴片机一号');
    expect(document.body.textContent).toContain('运行');
    expect(document.body.textContent).toContain('新增设备');
    expect([...document.querySelectorAll('button')].some((button) => button.textContent?.includes('编辑'))).toBe(false);
    expect([...document.querySelectorAll('button')].some((button) => button.textContent?.includes('停用'))).toBe(false);
});

it('shows editing and stopping for the update permission', async () => {
    await render(['equipment.ledger.index', 'button.equipment.ledger.update']);

    expect([...document.querySelectorAll('button')].some((button) => button.textContent?.includes('编辑'))).toBe(true);
    expect([...document.querySelectorAll('button')].some((button) => button.textContent?.includes('停用'))).toBe(true);
});
