import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { App as AntdApp } from 'antd';
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import type { Root } from 'react-dom/client';
import { afterEach, expect, it } from 'vitest';
import ProductionExceptionsPage from './index';
import { http } from '@/api/client';
import type { ProductionException } from '@/api/production';
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
let requests: { url?: string; method?: string; data?: unknown; params?: unknown }[] = [];
let currentException: ProductionException;
const exception: ProductionException = {
    id: 1,
    code: 'EXC-DEMO-001',
    type: 'equipment',
    type_label: '设备异常',
    status: 'reported',
    status_label: '已上报',
    description: '贴片机吸嘴堵塞，产线停线。',
    handling_result: null,
    production_order: {
        id: 1,
        code: 'PO-DEMO-001',
        product: { id: 1, code: 'P-001', name: '控制板' },
    },
    process_record: {
        id: 5,
        sequence: 1,
        sn: null,
        route_operation: {
            id: 2,
            sequence: 1,
            process: { id: 3, code: 'OP-01', name: '贴片' },
        },
    },
    reporter: { id: 1, name: '测试员' },
    handler: null,
    images: [],
    reported_at: '2026-11-16T02:00:00Z',
    handled_at: null,
    closed_at: null,
    created_at: '2026-11-16T02:00:00Z',
    updated_at: '2026-11-16T02:00:00Z',
};

async function render(permissions: string[], overrides: Partial<ProductionException> = {}) {
    useAuthStore.setState({ token: 'token', user: currentUserFixture({ permissions }) });
    currentException = { ...exception, ...overrides };
    requests = [];
    const container = document.createElement('div');
    document.body.append(container);
    root = createRoot(container);
    client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
    http.defaults.adapter = async (config) => {
        requests.push({ url: config.url, method: config.method, data: config.data, params: config.params });
        const body = typeof config.data === 'string' ? JSON.parse(config.data) : config.data;

        if (config.url === '/production/exceptions/1/close') {
            currentException = {
                ...currentException,
                status: 'closed',
                status_label: '已关闭',
                closed_at: '2026-11-16T04:00:00Z',
            };
        } else if (config.url === '/production/exceptions/1/process') {
            currentException = {
                ...currentException,
                status: 'processing',
                status_label: '处理中',
                handler: { id: 1, name: '测试员' },
                handling_result: body?.handling_result ?? '处理中',
                handled_at: '2026-11-16T03:00:00Z',
            };
        }

        const isAction = ['process', 'close']
            .some((action) => config.url === `/production/exceptions/1/${action}`);
        const include = (config.params as { include?: string } | undefined)?.include ?? 'all';

        return {
            config,
            status: 200,
            statusText: 'OK',
            headers: {},
            data: {
                code: 200,
                data: isAction
                    ? currentException
                    : config.url === '/production/exceptions/options'
                        ? {
                            orders: include === 'process_records' ? [] : [exception.production_order],
                            process_records: include === 'orders' ? [] : [exception.process_record],
                        }
                        : {
                            items: [currentException],
                            meta: { current_page: 1, per_page: 15, total: 1, last_page: 1 },
                        },
            },
        };
    };
    await act(async () => {
        root.render(
            <QueryClientProvider client={client}>
                <AntdApp>
                    <ProductionExceptionsPage />
                </AntdApp>
            </QueryClientProvider>,
        );
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

it('lists exceptions and only offers actions the user may run', async () => {
    await render(['production.exceptions.index']);
    const buttons = [...document.querySelectorAll('button')].map((button) => button.textContent?.trim());

    expect(document.body.textContent).toContain('EXC-DEMO-001');
    expect(document.body.textContent).toContain('设备异常');
    expect(document.body.textContent).toContain('贴片机吸嘴堵塞，产线停线。');
    expect(document.body.textContent).toContain('已上报');
    expect(document.body.textContent).toContain('PO-DEMO-001');
    expect(document.body.textContent).toContain('工序 1 贴片');
    expect(buttons).not.toContain('上报异常');
    expect(buttons).not.toContain('处理');
});

it('closes a processing exception through the protected API action', async () => {
    await render(
        ['production.exceptions.index', 'button.production.exceptions.close'],
        {
            status: 'processing',
            status_label: '处理中',
            handler: { id: 1, name: '测试员' },
            handling_result: '已更换吸嘴',
            handled_at: '2026-11-16T03:00:00Z',
        },
    );
    const close = [...document.querySelectorAll('button')]
        .find((button) => button.textContent?.trim() === '关闭');

    await act(async () => {
        (close as HTMLButtonElement).click();
    });
    await act(async () => { await new Promise((resolve) => setTimeout(resolve, 50)); });

    const confirm = [...document.querySelectorAll('button')]
        .find((button) => button.textContent?.trim() === '确认关闭');

    await act(async () => {
        (confirm as HTMLButtonElement).click();
        await new Promise((resolve) => setTimeout(resolve, 100));
    });

    expect(requests.some((request) => request.url === '/production/exceptions/1/close' && request.method === 'post')).toBe(true);
    expect(document.body.textContent).toContain('已关闭');
});

it('requires a handling result before processing an exception', async () => {
    await render(['production.exceptions.index', 'button.production.exceptions.process']);
    const process = [...document.querySelectorAll('button')]
        .find((button) => button.textContent?.trim() === '处理');

    await act(async () => {
        (process as HTMLButtonElement).click();
    });
    await act(async () => { await new Promise((resolve) => setTimeout(resolve, 50)); });

    const submit = [...document.querySelectorAll('button')]
        .find((button) => button.textContent?.trim() === '提交处理');

    await act(async () => {
        (submit as HTMLButtonElement).click();
        await new Promise((resolve) => setTimeout(resolve, 50));
    });

    expect(requests.some((request) => request.url === '/production/exceptions/1/process')).toBe(false);

    const textarea = document.querySelector('textarea') as HTMLTextAreaElement;
    const setValue = Object.getOwnPropertyDescriptor(
        window.HTMLTextAreaElement.prototype,
        'value',
    )?.set;

    await act(async () => {
        setValue?.call(textarea, '已更换吸嘴并复测。');
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
    });
    await act(async () => {
        (submit as HTMLButtonElement).click();
        await new Promise((resolve) => setTimeout(resolve, 100));
    });

    const request = requests.find((item) => item.url === '/production/exceptions/1/process');

    expect(request).toBeDefined();
    expect(String(request?.data)).toContain('已更换吸嘴并复测。');
    expect(document.body.textContent).toContain('处理中');
});

it('shows the report form only when the user may create exceptions', async () => {
    await render(['production.exceptions.index', 'button.production.exceptions.create']);
    const report = [...document.querySelectorAll('button')]
        .find((button) => button.textContent?.trim() === '上报异常');

    await act(async () => {
        (report as HTMLButtonElement).click();
    });

    expect(document.body.textContent).toContain('上报生产异常');
    expect(document.body.textContent).toContain('异常类型');
    expect(document.body.textContent).toContain('生产订单与工序执行记录至少填写一项');
});

it('lets a create-only user open the report form', async () => {
    await render(['button.production.exceptions.create']);
    const report = [...document.querySelectorAll('button')]
        .find((button) => button.textContent?.trim() === '上报异常');

    expect(report).toBeDefined();

    await act(async () => {
        (report as HTMLButtonElement).click();
    });

    expect(document.body.textContent).toContain('上报生产异常');
});

it('offers the production orders inside the report form', async () => {
    await render(['production.exceptions.index', 'button.production.exceptions.create']);
    const report = [...document.querySelectorAll('button')]
        .find((button) => button.textContent?.trim() === '上报异常');

    await act(async () => {
        (report as HTMLButtonElement).click();
    });
    await act(async () => { await new Promise((resolve) => setTimeout(resolve, 50)); });

    const options = requests.find((request) => request.url === '/production/exceptions/options');
    const params = options?.params as { keyword?: string; include?: string } | undefined;

    // An empty search box must not travel as a keyword.
    expect(params?.keyword).toBeUndefined();
    expect(params?.include).toBe('orders');

    await act(async () => {
        // AntD opens the dropdown on mousedown, not on click.
        document.body.querySelector<HTMLElement>('#production_order_id')?.dispatchEvent(
            new MouseEvent('mousedown', { bubbles: true }),
        );
        await new Promise((resolve) => setTimeout(resolve, 0));
    });

    expect(document.body.textContent).toContain('PO-DEMO-001 控制板');
});
