import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { App as AntdApp } from 'antd';
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import type { Root } from 'react-dom/client';
import { MemoryRouter } from 'react-router';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import BackwardTracePage from './index';
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

const batchNo = 'LOT-BATCH-001';

const trace = {
    batch_no: batchNo,
    has_data: true,
    message: null,
    data_granularity: {
        linkage: 'material_requisition',
        production_batches: 'order',
        product_sns: 'sn',
    },
    scope_note:
        '物料按生产订单发料，未细分到生产批次；同一批次号可能对应多个物料，结果按生产批次汇总并列出全部物料用量。',
    summary: {
        material_count: 2,
        production_order_count: 1,
        production_batch_count: 1,
        sn_count: 2,
    },
    materials: [
        {
            id: 9,
            code: 'MAT-BATCH-01',
            name: '批次物料',
            unit: 'kg',
            batch_managed: true,
        },
        {
            id: 10,
            code: 'MAT-BATCH-02',
            name: '批次辅料',
            unit: 'kg',
            batch_managed: true,
        },
    ],
    production_batches: [
        {
            id: 6,
            code: 'PB-BATCH-01',
            quantity: 2,
            materials: [
                {
                    material: {
                        id: 9,
                        code: 'MAT-BATCH-01',
                        name: '批次物料',
                        unit: 'kg',
                        batch_managed: true,
                    },
                    issued_quantity: '18.000',
                },
                {
                    material: {
                        id: 10,
                        code: 'MAT-BATCH-02',
                        name: '批次辅料',
                        unit: 'kg',
                        batch_managed: true,
                    },
                    issued_quantity: '5.000',
                },
            ],
            production_order: {
                id: 11,
                code: 'PO-BATCH-001',
                status: 'in_progress',
                status_label: '生产中',
                planned_quantity: '10.000',
                produced_quantity: '6.000',
                product: {
                    id: 1,
                    code: 'P-BATCH',
                    name: '智能电表',
                    model: 'DTSD-1',
                    specification: '220V',
                    unit: '台',
                },
            },
            product_sns: [
                {
                    id: 1,
                    sn: 'SN-BATCH-001',
                    status: 'active',
                    status_label: '在用',
                    material_batch_no: null,
                    printed_at: null,
                    voided_at: null,
                    void_reason: null,
                    created_at: '2026-11-16T01:00:00Z',
                },
                {
                    id: 2,
                    sn: 'SN-BATCH-002',
                    status: 'voided',
                    status_label: '已作废',
                    material_batch_no: null,
                    printed_at: null,
                    voided_at: '2026-11-16T02:00:00Z',
                    void_reason: '测试报废',
                    created_at: '2026-11-16T01:30:00Z',
                },
            ],
        },
    ],
};

let originalAdapter: typeof http.defaults.adapter;
let root: Root | undefined;
let queryClient: QueryClient | undefined;

async function flush() {
    await act(async () => {
        await new Promise((resolve) => setTimeout(resolve, 80));
    });
}

async function renderPage(entry: string) {
    const container = document.createElement('div');
    document.body.append(container);
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
    queryClient = client;
    root = createRoot(container);

    await act(async () => {
        root?.render(
            <MemoryRouter initialEntries={[entry]}>
                <QueryClientProvider client={client}>
                    <AntdApp>
                        <BackwardTracePage />
                    </AntdApp>
                </QueryClientProvider>
            </MemoryRouter>,
        );
    });

    await flush();
}

async function expandFirstRow() {
    const expandButton = document.querySelector<HTMLElement>(
        '.ant-table-row-expand-icon',
    );

    expect(expandButton).not.toBeNull();

    await act(async () => {
        expandButton?.click();
    });
    await flush();
}

describe('物料反向追溯页', () => {
    beforeEach(() => {
        originalAdapter = http.defaults.adapter;
        useAuthStore.setState({
            token: 'token',
            user: currentUserFixture({
                permissions: [
                    'traceability.backward.show',
                    'traceability.forward.show',
                ],
            }),
        });
        http.defaults.adapter = async (config) => {
            if (config.url === `/traceability/material/${encodeURIComponent(batchNo)}`) {
                return {
                    data: { code: 200, message: 'success', data: trace, timestamp: 0 },
                    status: 200,
                    statusText: '',
                    headers: {},
                    config,
                };
            }

            throw Object.assign(new Error('Request failed'), {
                isAxiosError: true,
                config,
                response: {
                    data: { code: 404, message: '物料批次不存在', data: null, timestamp: 0 },
                    status: 404,
                    statusText: '',
                    headers: {},
                    config,
                },
            });
        };
    });

    afterEach(() => {
        act(() => root?.unmount());
        queryClient?.clear();
        root = undefined;
        queryClient = undefined;
        http.defaults.adapter = originalAdapter;
        document.body.innerHTML = '';
    });

    it('loads a material batch from the URL and expands its product serial numbers', async () => {
        await renderPage(`/traceability/backward?batch_no=${batchNo}`);

        for (const text of [
            '物料反向追溯',
            batchNo,
            'MAT-BATCH-01',
            'PB-BATCH-01',
            'PO-BATCH-001',
            '智能电表',
            '18.000',
            'MAT-BATCH-02',
            '5.000',
            '物料按生产订单发料，未细分到生产批次；同一批次号可能对应多个物料，结果按生产批次汇总并列出全部物料用量。',
        ]) {
            expect(document.body.textContent).toContain(text);
        }

        expect(document.body.textContent).not.toContain('SN-BATCH-001');

        await expandFirstRow();

        expect(document.body.textContent).toContain('SN-BATCH-001');
        expect(document.body.textContent).toContain('SN-BATCH-002');
        expect(document.body.textContent).toContain('已作废');
        expect(
            document.querySelector('a[href^="/traceability/forward"]'),
        ).not.toBeNull();
    });

    it('hides the forward trace link without the forward trace permission', async () => {
        useAuthStore.setState({
            token: 'token',
            user: currentUserFixture({
                permissions: ['traceability.backward.show'],
            }),
        });

        await renderPage(`/traceability/backward?batch_no=${batchNo}`);
        await expandFirstRow();

        expect(document.body.textContent).toContain('SN-BATCH-001');
        expect(
            document.querySelector('a[href^="/traceability/forward"]'),
        ).toBeNull();
    });

    it('shows an empty result when the material batch has no products', async () => {
        http.defaults.adapter = async (config) => ({
            data: {
                code: 200,
                message: 'success',
                data: {
                    ...trace,
                    has_data: false,
                    message: '该物料批次未关联任何产品 SN',
                    materials: [],
                    production_batches: [],
                    summary: {
                        material_count: 0,
                        production_order_count: 0,
                        production_batch_count: 0,
                        sn_count: 0,
                    },
                },
                timestamp: 0,
            },
            status: 200,
            statusText: '',
            headers: {},
            config,
        });

        await renderPage(`/traceability/backward?batch_no=${batchNo}`);

        expect(document.body.textContent).toContain('该物料批次未关联任何产品 SN');
        expect(document.body.textContent).toContain('暂无关联产品 SN');
    });
});
