import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { App as AntdApp } from 'antd';
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import ProductionDispatches from './index';
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

const meta = { current_page: 1, per_page: 15, total: 1, last_page: 1 };
const dispatch = {
    id: 31,
    code: 'PO-DEMO-001-T01-D01',
    production_task_id: 21,
    route_operation_id: 7,
    employee_id: 5,
    team_id: 4,
    dispatched_at: '2026-11-15T08:00:00Z',
    production_task: {
        id: 21,
        code: 'PO-DEMO-001-T01',
        production_order_id: 11,
        planned_quantity: '60.000',
        produced_quantity: '20.000',
        progress_percent: 33.3,
        planned_start_at: '2026-11-15T08:00:00Z',
        planned_end_at: null,
        status: 'in_progress',
        status_label: '生产中',
    },
    production_order: { id: 11, code: 'PO-DEMO-001' },
    route_operation: {
        id: 7,
        sequence: 1,
        process: { id: 3, code: 'OP001', name: '原材料准备' },
    },
    employee: { id: 5, employee_no: 'EMP004', name: '李秀兰' },
    team: { id: 4, code: 'TEAM01', name: 'A班' },
};

async function renderPage() {
    const container = document.createElement('div');
    document.body.append(container);
    const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });

    await act(async () => {
        createRoot(container).render(
            <QueryClientProvider client={queryClient}>
                <AntdApp>
                    <ProductionDispatches />
                </AntdApp>
            </QueryClientProvider>,
        );
    });

    await act(async () => {
        await new Promise((resolve) => setTimeout(resolve, 0));
    });
}

describe('生产派工页', () => {
    beforeEach(() => {
        http.defaults.adapter = async (config) => {
            const data =
                config.url === '/production/dispatches' || config.url === '/production/dispatches/mine'
                    ? { items: [dispatch], meta }
                    : { items: [], meta: { ...meta, total: 0 } };

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

    it('shows dispatch records to a user who can read them', async () => {
        useAuthStore.setState({
            token: 'token',
            user: currentUserFixture({ permissions: ['production.dispatches.index'] }),
        });

        await renderPage();

        expect(document.body.textContent).toContain('PO-DEMO-001-T01-D01');
        expect(document.body.textContent).toContain('派工记录');
        expect(document.body.textContent).not.toContain('我的待办');
        expect(document.body.textContent).not.toContain('新增派工');
    });

    it('shows only the own todo tab to an operator', async () => {
        useAuthStore.setState({
            token: 'token',
            user: currentUserFixture({ permissions: ['production.dispatches.mine'] }),
        });

        await renderPage();

        expect(document.body.textContent).toContain('我的待办');
        expect(document.body.textContent).toContain('PO-DEMO-001-T01-D01');
        expect(document.body.textContent).not.toContain('派工记录');
        expect(document.body.textContent).not.toContain('新增派工');
    });
});
