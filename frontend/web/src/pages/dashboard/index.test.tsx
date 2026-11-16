import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { App as AntdApp } from 'antd';
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import type { Root } from 'react-dom/client';
import { MemoryRouter, Route, Routes, useLocation } from 'react-router';
import { afterEach, expect, it, vi } from 'vitest';
import Dashboard from './index';
import { http } from '@/api/client';
import { useAuthStore } from '@/stores/auth';
import { currentUserFixture } from '@/test/fixtures';
import type { MenuNode } from '@/types/api';

vi.mock('@/components/echart', () => ({
    default: () => <div data-testid="chart" />,
}));

(globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
window.matchMedia ??= ((query: string) => ({
    matches: false,
    media: query,
    onchange: null,
    addListener() {},
    removeListener() {},
    addEventListener() {},
    removeEventListener() {},
    dispatchEvent: () => false,
})) as unknown as typeof window.matchMedia;
globalThis.ResizeObserver ??= class {
    observe() {}
    unobserve() {}
    disconnect() {}
};

type Payloads = Record<string, unknown>;

const payloads: Payloads = {
    '/dashboard/today': {
        date: '2026-11-17',
        order_count: 12,
        planned_quantity: '1200.000',
        produced_quantity: '980.000',
        qualified_quantity: '950.000',
        defective_quantity: '30.000',
        running_equipment_count: 8,
    },
    '/dashboard/production-trend': {
        days: 7,
        items: [
            { date: '2026-11-11', quantity: '120.000' },
            { date: '2026-11-12', quantity: '140.000' },
            { date: '2026-11-13', quantity: '130.000' },
            { date: '2026-11-14', quantity: '150.000' },
            { date: '2026-11-15', quantity: '160.000' },
            { date: '2026-11-16', quantity: '150.000' },
            { date: '2026-11-17', quantity: '130.000' },
        ],
    },
    '/dashboard/production-lines': {
        items: [
            { production_line_id: 1, code: 'PL01', name: 'SMT 一线', quantity: '500.000' },
            { production_line_id: 2, code: 'PL02', name: '总装二线', quantity: '480.000' },
        ],
    },
    '/dashboard/quality': {
        qualified_quantity: '950.000',
        defective_quantity: '30.000',
        total_quantity: '980.000',
        qualified_rate: '96.94',
        defect_reasons: [
            {
                defect_code_id: 1,
                code: 'DEF01',
                name: '表面划伤',
                category: '外观',
                quantity: '20.000',
            },
            {
                defect_code_id: 2,
                code: 'DEF02',
                name: '尺寸超差',
                category: '尺寸',
                quantity: '10.000',
            },
        ],
    },
    '/dashboard/equipment-status': {
        items: [
            { status: 'running', label: '运行', count: 8 },
            { status: 'idle', label: '待机', count: 2 },
            { status: 'fault', label: '故障', count: 1 },
            { status: 'repair', label: '维修', count: 1 },
            { status: 'offline', label: '离线', count: 0 },
        ],
    },
    '/dashboard/latest-orders': {
        items: [{
            id: 1,
            code: 'PO-DEMO-001',
            produced_quantity: '980.000',
            planned_quantity: '1200.000',
            status: 'in_progress',
            status_label: '生产中',
            product: { id: 1, code: 'P001', name: '控制板' },
            production_line: { id: 1, code: 'PL01', name: 'SMT 一线' },
        }],
    },
    '/dashboard/latest-reports': {
        items: [{
            id: 1,
            code: 'PR-DEMO-001',
            quantity: '120.000',
            reported_at: '2026-11-17T02:00:00Z',
            type_label: '正常',
            product: { id: 1, code: 'P001', name: '控制板' },
            production_task: { id: 1, code: 'PT-DEMO-001' },
        }],
    },
    '/dashboard/latest-exceptions': {
        items: [{
            id: 1,
            code: 'EXC-DEMO-001',
            type_label: '设备异常',
            status_label: '已上报',
            description: '贴片机吸嘴堵塞',
            reported_at: '2026-11-17T03:00:00Z',
            production_order: { id: 1, code: 'PO-DEMO-001' },
        }],
    },
    '/dashboard/latest-alarms': {
        items: [{
            id: 1,
            code: 'ALM-DEMO-001',
            message: '转速超限',
            level_label: '警告',
            status_label: '待确认',
            triggered_at: '2026-11-17T04:00:00Z',
            equipment: { id: 1, code: 'EQ-SMT-01', name: 'SMT 贴片机一号' },
        }],
    },
};

let root: Root;
let client: QueryClient;
const originalAdapter = http.defaults.adapter;
let requests: string[] = [];

const menu = (id: number, path: string, name: string): MenuNode => ({
    id,
    parent_id: null,
    permission_id: null,
    name,
    path,
    icon: null,
    sort: id,
    children: [],
});

const defaultMenus = [
    menu(1, '/dashboard/overview', '生产数据总览'),
    menu(2, '/production/orders', '生产订单'),
    menu(3, '/production/reports', '生产报工'),
    menu(4, '/execution/exceptions', '生产异常'),
    menu(5, '/equipment/alarms', '设备告警'),
];

const defaultPermissions = [
    'dashboard.overview',
    'dashboard.trend',
    'dashboard.quality',
    'dashboard.equipment',
];

function LocationProbe() {
    const location = useLocation();

    return <div data-testid="location">{location.pathname}</div>;
}

async function render(
    overrides: Payloads = {},
    menus: MenuNode[] = defaultMenus,
    failures: string[] = [],
    permissions: string[] = defaultPermissions,
) {
    const responses = { ...payloads, ...overrides };
    requests = [];
    useAuthStore.setState({
        token: 'token',
        user: currentUserFixture({
            permissions,
            menus,
        }),
    });
    http.defaults.adapter = async (config) => {
        requests.push(config.url ?? '');

        if (failures.includes(config.url ?? '')) {
            throw Object.assign(new Error('Request failed'), {
                isAxiosError: true,
                config,
                response: {
                    data: { code: 503, message: '服务不可用', data: null },
                    status: 503,
                    statusText: 'Service Unavailable',
                    headers: {},
                    config,
                },
            });
        }

        return {
            config,
            status: 200,
            statusText: 'OK',
            headers: {},
            data: {
                code: 200,
                message: 'success',
                data: responses[config.url ?? ''],
                timestamp: 0,
            },
        };
    };

    const container = document.createElement('div');
    document.body.append(container);
    root = createRoot(container);
    client = new QueryClient({ defaultOptions: { queries: { retry: false } } });

    await act(async () => {
        root.render(
            <QueryClientProvider client={client}>
                <AntdApp>
                    <MemoryRouter initialEntries={['/dashboard/overview']}>
                        <LocationProbe />
                        <Routes>
                            <Route path="/dashboard/overview" element={<Dashboard />} />
                            <Route path="/production/orders" element={<div>订单列表页</div>} />
                            <Route path="/production/reports" element={<div>报工记录页</div>} />
                            <Route path="/execution/exceptions" element={<div>生产异常页</div>} />
                            <Route path="/equipment/alarms" element={<div>设备告警页</div>} />
                        </Routes>
                    </MemoryRouter>
                </AntdApp>
            </QueryClientProvider>,
        );
    });
    await act(async () => { await new Promise((resolve) => setTimeout(resolve, 80)); });
}

function card(title: string) {
    return [...document.querySelectorAll<HTMLElement>('.ant-card')]
        .find((item) => item.textContent?.includes(title));
}

afterEach(async () => {
    await act(async () => root?.unmount());
    client?.clear();
    http.defaults.adapter = originalAdapter;
    requests = [];
    document.body.innerHTML = '';
});

it('renders the six statistics, five charts and four latest lists', async () => {
    await render();

    expect(card('今日订单')?.textContent).toContain('12');
    expect(card('计划数量')?.textContent).toContain('1,200');
    expect(card('实际产量')?.textContent).toContain('980');
    expect(card('合格数量')?.textContent).toContain('950');
    expect(card('不良数量')?.textContent).toContain('30');
    expect(card('运行设备')?.textContent).toContain('8');

    expect(document.querySelectorAll('[data-testid="chart"]')).toHaveLength(5);
    for (const title of ['近七天生产趋势', '各产线产量对比', '产品合格率', '设备状态分布', '不良原因分布']) {
        expect(card(title)?.querySelector('[data-testid="chart"]')).not.toBeNull();
    }

    expect(document.body.textContent).toContain('PO-DEMO-001');
    expect(document.body.textContent).toContain('PR-DEMO-001');
    expect(document.body.textContent).toContain('EXC-DEMO-001');
    expect(document.body.textContent).toContain('ALM-DEMO-001');
});

it.each([
    ['最新订单', 'PO-DEMO-001', '/production/orders', '订单列表页'],
    ['最新报工', 'PR-DEMO-001', '/production/reports', '报工记录页'],
    ['最新异常', 'EXC-DEMO-001', '/execution/exceptions', '生产异常页'],
    ['最新告警', 'ALM-DEMO-001', '/equipment/alarms', '设备告警页'],
])('jumps from %s to its module page', async (_, code, path, pageText) => {
    await render();
    const row = [...document.querySelectorAll<HTMLTableRowElement>('tr')]
        .find((item) => item.textContent?.includes(code));

    expect(row).toBeDefined();

    await act(async () => {
        row?.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        await new Promise((resolve) => setTimeout(resolve, 20));
    });

    expect(document.querySelector('[data-testid="location"]')?.textContent).toBe(path);
    expect(document.body.textContent).toContain(pageText);
});

it('shows friendly empty states instead of charts or errors when there is no data', async () => {
    await render({
        '/dashboard/today': {
            date: '2026-11-17',
            order_count: 0,
            planned_quantity: '0.000',
            produced_quantity: '0.000',
            qualified_quantity: '0.000',
            defective_quantity: '0.000',
            running_equipment_count: 0,
        },
        '/dashboard/production-trend': {
            days: 7,
            items: [
                '2026-11-11',
                '2026-11-12',
                '2026-11-13',
                '2026-11-14',
                '2026-11-15',
                '2026-11-16',
                '2026-11-17',
            ].map((date) => ({ date, quantity: '0.000' })),
        },
        '/dashboard/production-lines': {
            items: [
                { production_line_id: 1, code: 'PL01', name: 'SMT 一线', quantity: '0.000' },
            ],
        },
        '/dashboard/quality': {
            qualified_quantity: '0.000',
            defective_quantity: '0.000',
            total_quantity: '0.000',
            qualified_rate: '0.00',
            defect_reasons: [],
        },
        '/dashboard/equipment-status': {
            items: [
                { status: 'running', label: '运行', count: 0 },
                { status: 'idle', label: '待机', count: 0 },
                { status: 'fault', label: '故障', count: 0 },
                { status: 'repair', label: '维修', count: 0 },
                { status: 'offline', label: '离线', count: 0 },
            ],
        },
        '/dashboard/latest-orders': { items: [] },
        '/dashboard/latest-reports': { items: [] },
        '/dashboard/latest-exceptions': { items: [] },
        '/dashboard/latest-alarms': { items: [] },
    });

    expect(document.querySelectorAll('[data-testid="chart"]')).toHaveLength(0);
    for (const text of [
        '暂无生产趋势数据',
        '暂无产线对比数据',
        '暂无合格率数据',
        '暂无不良原因数据',
        '暂无设备状态数据',
        '暂无最新订单',
        '暂无最新报工',
        '暂无最新异常',
        '暂无最新告警',
    ]) {
        expect(document.body.textContent).toContain(text);
    }
    expect(document.body.textContent).not.toContain('加载失败');
});

it('only requests authorized data and links to pages the user can open', async () => {
    await render(
        {},
        [menu(1, '/dashboard/overview', '生产数据总览')],
        [],
        ['dashboard.overview'],
    );

    expect(requests).not.toContain('/dashboard/production-trend');
    expect(requests).not.toContain('/dashboard/quality');
    expect(requests).not.toContain('/dashboard/equipment-status');
    expect(document.body.textContent).toContain('无生产趋势查看权限');
    expect(document.body.textContent).toContain('无质量统计查看权限');
    expect(document.body.textContent).toContain('无设备状态查看权限');
    expect([...document.querySelectorAll('button')].map((button) => button.textContent))
        .not.toContain('查看全部');
});

it('shows an error instead of zeros when today statistics fail', async () => {
    await render({}, defaultMenus, ['/dashboard/today']);

    expect(document.body.textContent).toContain('今日数据加载失败：服务不可用');
    expect(card('今日订单')?.textContent).toContain('-');
    expect(card('今日订单')?.textContent).not.toContain('12');
});
