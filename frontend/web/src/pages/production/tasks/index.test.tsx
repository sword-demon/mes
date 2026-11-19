import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { App as AntdApp } from 'antd';
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import ProductionTasks, { filterSplittableOrders } from './index';
import { http } from '@/api/client';
import { useAuthStore } from '@/stores/auth';
import { currentUserFixture } from '@/test/fixtures';

(globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;

window.matchMedia ??= ((query: string) => ({
    matches: false,
    media: query,
    onchange: null,
    addListener: () => undefined,
    removeListener: () => undefined,
    addEventListener: () => undefined,
    removeEventListener: () => undefined,
    dispatchEvent: () => false,
})) as unknown as typeof window.matchMedia;

globalThis.ResizeObserver ??= class {
    observe() {}
    unobserve() {}
    disconnect() {}
};

type Call = { method: string; url: string };

let calls: Call[] = [];

const meta = { current_page: 1, per_page: 15, total: 1, last_page: 1 };
const splittableOrder = {
    id: 11,
    code: 'PO-2026-001',
    planned_quantity: '100.000',
    planned_start_at: '2026-11-16T08:00:00Z',
    planned_end_at: null,
    status: 'released',
    status_label: '已下达',
    can_split: true,
};
const blockedOrder = {
    ...splittableOrder,
    id: 12,
    code: 'PO-2026-002',
    can_split: false,
};
const task = {
    id: 21,
    code: 'PO-2026-001-T01',
    production_order_id: 11,
    product_id: 1,
    route_id: 3,
    production_line_id: 2,
    team_id: 4,
    owner_id: 5,
    planned_quantity: '40.000',
    produced_quantity: '10.000',
    qualified_quantity: '9.000',
    defective_quantity: '1.000',
    progress_percent: 25,
    planned_start_at: '2026-11-16T08:00:00Z',
    planned_end_at: null,
    status: 'in_progress',
    status_label: '生产中',
    production_order: { id: 11, code: 'PO-2026-001', status: 'released' },
    product: { id: 1, code: 'P001', name: '智能电表' },
    route: { id: 3, code: 'R001', version: 'R1.0' },
    production_line: { id: 2, code: 'PL01', name: '一号线' },
    team: { id: 4, code: 'TEAM01', name: '一班' },
    owner: { id: 5, employee_no: 'EMP0001', name: '张三' },
};

async function renderPage() {
    const container = document.createElement('div');
    document.body.append(container);
    const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });

    await act(async () => {
        createRoot(container).render(
            <QueryClientProvider client={queryClient}>
                <AntdApp>
                    <ProductionTasks />
                </AntdApp>
            </QueryClientProvider>,
        );
    });

    await act(async () => {
        await new Promise((resolve) => setTimeout(resolve, 0));
    });
}

describe('生产任务页', () => {
    beforeEach(() => {
        calls = [];
        http.defaults.adapter = async (config) => {
            calls.push({ method: config.method ?? 'get', url: config.url ?? '' });

            const data =
                config.url === '/production/orders'
                    ? { items: [splittableOrder, blockedOrder], meta }
                    : config.url === '/basic-data/production-lines'
                      ? { items: [task.production_line], meta }
                      : { items: [task], meta };

            return {
                data: { code: 200, message: 'success', data, timestamp: 0 },
                status: 200,
                statusText: '',
                headers: {},
                config,
            };
        };
    });

    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('shows the task without actions the user cannot perform', async () => {
        useAuthStore.setState({
            token: 'token',
            user: currentUserFixture({ permissions: ['production.tasks.index'] }),
        });

        await renderPage();

        expect(document.body.textContent).toContain('PO-2026-001-T01');
        expect(document.body.textContent).not.toContain('拆分任务');
        expect(document.body.textContent).not.toContain('暂停');
        expect(document.body.textContent).not.toContain('分配');
    });

    it('pauses a running task through its dedicated endpoint', async () => {
        useAuthStore.setState({
            token: 'token',
            user: currentUserFixture({
                permissions: ['production.tasks.index', 'button.production.tasks.pause'],
            }),
        });

        await renderPage();

        await act(async () => {
            [...document.querySelectorAll<HTMLButtonElement>('button')]
                .find((button) => button.textContent === '暂停')
                ?.click();
            await new Promise((resolve) => setTimeout(resolve, 0));
        });

        expect(calls).toContainEqual({ method: 'post', url: '/production/tasks/21/pause' });
    });

    it('filters split options by can_split', () => {
        expect(filterSplittableOrders([splittableOrder, blockedOrder])).toEqual([splittableOrder]);
    });
});
