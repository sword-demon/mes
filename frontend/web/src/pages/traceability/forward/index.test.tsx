import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { App as AntdApp } from 'antd';
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import { MemoryRouter } from 'react-router';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import ForwardTracePage from './index';
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

const sn = 'MES20261116000001';

const trace = {
    has_data: true,
    message: null,
    sn: {
        id: 1,
        sn,
        status: 'active',
        status_label: '在用',
        material_batch_no: 'MAT-LOT-TRACE',
        printed_at: null,
        voided_at: null,
        void_reason: null,
        employee: { id: 3, employee_no: 'EMP-TRACE', name: '追溯操作员' },
        equipment: {
            id: 4,
            code: 'EQ-TRACE',
            name: 'SMT 贴片机一号',
            type: 'SMT 贴片机',
            status: 'running',
            status_label: '运行',
        },
        created_at: '2026-11-16T01:00:00Z',
    },
    product: {
        id: 1,
        code: 'P-TRACE',
        name: '智能电表',
        model: 'DTSD-1',
        specification: '220V',
        category: '成品',
        unit: '台',
    },
    production_order: {
        id: 11,
        code: 'PO-TRACE-001',
        status: 'in_progress',
        status_label: '生产中',
        planned_quantity: '10.000',
        produced_quantity: '6.000',
        qualified_quantity: '4.000',
        defective_quantity: '2.000',
        planned_start_at: '2026-11-16T00:00:00Z',
        planned_end_at: '2026-11-18T00:00:00Z',
        bom_version: 'B1.0',
        route_version: 'R1.0',
        production_line: { id: 2, code: 'PL01', name: 'SMT 一线' },
        product_version: { id: 5, version: 'V1.0' },
    },
    production_batch: { id: 6, code: 'LOT-TRACE-001', quantity: 5 },
    bom: {
        id: 7,
        code: 'BOM-TRACE',
        version: 'B1.0',
        audit_status: 'approved',
    },
    materials: [
        {
            material: {
                id: 9,
                code: 'MAT-TRACE-01',
                name: '追溯物料',
                unit: '个',
                batch_managed: true,
            },
            quantity: 2,
            loss_rate: 0,
            issued_quantity: '18.000',
        },
    ],
    route: {
        id: 10,
        code: 'RT-TRACE',
        version: 'R1.0',
        audit_status: 'approved',
        operations: [
            {
                id: 21,
                sequence: 1,
                standard_hours: 0.5,
                parameters: null,
                process: { id: 31, code: 'OP01', name: 'SMT 贴片', requires_inspection: true },
            },
            {
                id: 22,
                sequence: 2,
                standard_hours: 0.5,
                parameters: null,
                process: { id: 32, code: 'OP02', name: '整机组装', requires_inspection: false },
            },
        ],
    },
    process_records: [
        {
            id: 41,
            sequence: 1,
            sn,
            status: 'transferred',
            status_label: '已流转',
            started_at: '2026-11-16T02:00:00Z',
            completed_at: '2026-11-16T03:00:00Z',
            inspected_at: '2026-11-16T02:30:00Z',
            submitted_at: '2026-11-16T03:10:00Z',
            received_at: '2026-11-16T03:20:00Z',
            exception_reason: null,
            exception_at: null,
            rework_reason: null,
            rework_at: null,
            rework_count: 0,
            route_operation: {
                id: 21,
                sequence: 1,
                process: { id: 31, code: 'OP01', name: 'SMT 贴片' },
            },
            employee: { id: 3, employee_no: 'EMP-TRACE', name: '追溯操作员' },
            team: null,
            equipment: {
                id: 4,
                code: 'EQ-TRACE',
                name: 'SMT 贴片机一号',
                type: 'SMT 贴片机',
                status: 'running',
                status_label: '运行',
            },
        },
    ],
    inspections: [
        {
            id: 51,
            inspection_type: 'ipqc',
            inspection_type_label: 'IPQC 过程检验',
            scope: 'sn',
            result: 'passed',
            status: 'passed',
            result_label: '合格',
            inspector: { id: 6, name: '质检员' },
            inspected_at: '2026-11-16T02:30:00Z',
            remark: null,
            production_order: { id: 11, code: 'PO-TRACE-001', product: { id: 1, code: 'P-TRACE', name: '智能电表' } },
            process_record: { id: 41, sequence: 1, sn },
            route_operation: {
                id: 21,
                sequence: 1,
                process: { id: 31, code: 'OP01', name: 'SMT 贴片' },
            },
            items: [
                {
                    inspection_item_id: 61,
                    code: 'INS-TRACE',
                    name: '焊点温度',
                    unit: '℃',
                    measured_value: '5.000',
                    standard_value: '5.000',
                    min_value: '4.800',
                    max_value: '5.200',
                    passed: true,
                },
            ],
            required_items: [],
            images: [],
            created_at: '2026-11-16T02:20:00Z',
            updated_at: '2026-11-16T02:30:00Z',
        },
        {
            id: 52,
            inspection_type: 'oqc',
            inspection_type_label: 'OQC 出货检验',
            scope: 'order',
            result: null,
            status: 'pending',
            result_label: '待检验',
            inspector: null,
            inspected_at: null,
            remark: '订单完工后检验',
            production_order: { id: 11, code: 'PO-TRACE-001', product: { id: 1, code: 'P-TRACE', name: '智能电表' } },
            process_record: null,
            route_operation: {
                id: 22,
                sequence: 2,
                process: { id: 32, code: 'OP02', name: '整机组装' },
            },
            items: [],
            required_items: [],
            images: [],
            created_at: '2026-11-16T02:50:00Z',
            updated_at: '2026-11-16T02:50:00Z',
        },
    ],
    defects: [
        {
            id: 71,
            scope: 'batch',
            status: 'closed',
            status_label: '已关闭',
            quantity: '2.000',
            remark: null,
            production_order: { id: 11, code: 'PO-TRACE-001', product: { id: 1, code: 'P-TRACE', name: '智能电表' } },
            route_operation: {
                id: 21,
                sequence: 1,
                process: { id: 31, code: 'OP01', name: 'SMT 贴片' },
            },
            production_batch: { id: 6, code: 'LOT-TRACE-001', quantity: 5 },
            defect_code: { id: 81, code: 'D01', name: '焊点虚焊', category: '工艺' },
            handled_by: { id: 6, name: '质检员' },
            rework_orders: [],
            recheck_result: null,
            recheck_remark: null,
            rechecked_at: null,
            scrap_reason: null,
            scrap_quantity: null,
            scrapped_at: null,
            created_at: '2026-11-16T02:40:00Z',
            updated_at: '2026-11-16T02:40:00Z',
        },
    ],
};

let originalAdapter: typeof http.defaults.adapter;

async function renderPage(entry: string) {
    const container = document.createElement('div');
    document.body.append(container);
    const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });

    await act(async () => {
        createRoot(container).render(
            <MemoryRouter initialEntries={[entry]}>
                <QueryClientProvider client={queryClient}>
                    <AntdApp>
                        <ForwardTracePage />
                    </AntdApp>
                </QueryClientProvider>
            </MemoryRouter>,
        );
    });

    await act(async () => {
        await new Promise((resolve) => setTimeout(resolve, 80));
    });
}

describe('产品正向追溯页', () => {
    beforeEach(() => {
        originalAdapter = http.defaults.adapter;
        useAuthStore.setState({
            token: 'token',
            user: currentUserFixture({ permissions: ['traceability.forward.show'] }),
        });
        http.defaults.adapter = async (config) => {
            if (config.url === `/traceability/sn/${sn}`) {
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
                    data: { code: 404, message: 'SN 不存在', data: null, timestamp: 0 },
                    status: 404,
                    statusText: '',
                    headers: {},
                    config,
                },
            });
        };
    });

    afterEach(() => {
        http.defaults.adapter = originalAdapter;
        document.body.innerHTML = '';
    });

    it('loads a serial number from the URL and renders the full production history', async () => {
        await renderPage(`/traceability/forward?sn=${sn}`);

        for (const text of [
            sn,
            '智能电表',
            'PO-TRACE-001',
            'LOT-TRACE-001',
            'MAT-LOT-TRACE',
            'B1.0',
            'R1.0',
            'MAT-TRACE-01',
            '18.000',
            '生产时间轴',
            'SMT 贴片',
            '整机组装',
            'EMP-TRACE 追溯操作员',
            'EQ-TRACE',
            '质量检验记录',
            '订单级',
            '不良处理记录',
            '焊点虚焊',
        ]) {
            expect(document.body.textContent).toContain(text);
        }
    });

    it('shows a clear message when the serial number does not exist', async () => {
        await renderPage('/traceability/forward?sn=UNKNOWN-SN');

        expect(document.body.textContent).toContain('SN 不存在');
    });

    it('reports an existing serial number without execution records', async () => {
        http.defaults.adapter = async (config) => ({
            data: {
                code: 200,
                message: 'success',
                data: {
                    ...trace,
                    has_data: false,
                    message: '该 SN 暂无可追溯的生产执行记录',
                    process_records: [],
                    inspections: [],
                    defects: [],
                },
                timestamp: 0,
            },
            status: 200,
            statusText: '',
            headers: {},
            config,
        });

        await renderPage(`/traceability/forward?sn=${sn}`);

        expect(document.body.textContent).toContain('该 SN 暂无可追溯的生产执行记录');
        expect(document.body.textContent).toContain('暂无工序执行记录');
    });
});
