import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { App as AntdApp } from 'antd';
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import ProductionReports from './index';
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
const report = {
    id: 51,
    code: 'PO-DEMO-001-T01-R01',
    production_task_id: 21,
    route_operation_id: 7,
    employee_id: 5,
    team_id: 4,
    equipment_id: null,
    quantity: '12.000',
    qualified_quantity: '10.000',
    defective_quantity: '2.000',
    type: 'normal',
    type_label: '正常',
    reported_at: '2026-11-15T09:30:00Z',
    reversed_at: null,
    production_task: {
        id: 21,
        code: 'PO-DEMO-001-T01',
        production_order_id: 11,
        planned_quantity: '60.000',
        produced_quantity: '12.000',
        progress_percent: 20,
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
                    <ProductionReports />
                </AntdApp>
            </QueryClientProvider>,
        );
    });

    await act(async () => {
        await new Promise((resolve) => setTimeout(resolve, 0));
    });
}

describe('生产报工页', () => {
    beforeEach(() => {
        http.defaults.adapter = async (config) => {
            let data: unknown = { items: [], meta: { ...meta, total: 0 } };

            if (config.url === '/production/reports') {
                data = { items: [report], meta };
            }

            if (config.url === '/production/reports/options') {
                data = { tasks: [], employees: [], equipments: [] };
            }

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

    it('shows report records to a user who can read them', async () => {
        useAuthStore.setState({
            token: 'token',
            user: currentUserFixture({ permissions: ['production.reports.index'] }),
        });

        await renderPage();

        expect(document.body.textContent).toContain('PO-DEMO-001-T01-R01');
        expect(document.body.textContent).toContain('原材料准备');
        expect(document.body.textContent).toContain('正常');
        expect(document.body.textContent).not.toContain('提交报工');
        expect(document.body.textContent).not.toContain('冲销');
    });

    it('offers submit and reverse actions to a permitted operator', async () => {
        useAuthStore.setState({
            token: 'token',
            user: currentUserFixture({
                permissions: [
                    'production.reports.index',
                    'button.production.reports.create',
                    'button.production.reports.reverse',
                ],
            }),
        });

        await renderPage();

        expect(document.body.textContent).toContain('提交报工');
        expect(document.body.textContent).toContain('冲销');
    });
});
