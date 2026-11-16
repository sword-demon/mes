import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { App as AntdApp } from 'antd';
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import type { Root } from 'react-dom/client';
import { afterEach, describe, expect, it } from 'vitest';
import AuditLogs from './index';
import { formatChangeValue } from './operation-logs';
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

let root: Root;
let requests: { url?: string; params?: Record<string, unknown> }[] = [];

const operationLog = {
    id: 1,
    user_id: 1,
    user_name: '张三',
    action: 'production.order.transition',
    action_name: '订单状态流转',
    object_type: '生产订单',
    object_id: 1,
    object_name: 'PO-001',
    summary: '订单状态流转：生产订单 PO-001（状态：草稿 → 待审核）',
    changes: { 状态: { before: '草稿', after: '待审核' } },
    ip: '127.0.0.1',
    created_at: '2026-11-16T10:00:00+08:00',
};

const loginLog = {
    id: 1,
    user_id: 1,
    account: 'alice@mes.test',
    ip: '127.0.0.1',
    success: false,
    message: '密码错误',
    created_at: '2026-11-16T10:00:00+08:00',
};

const page = { items: [operationLog], meta: { current_page: 1, per_page: 15, total: 1, last_page: 1 } };
const loginPage = { items: [loginLog], meta: { current_page: 1, per_page: 15, total: 1, last_page: 1 } };

async function renderLogs(permissions: string[]) {
    useAuthStore.setState({ token: 'token', user: currentUserFixture({ permissions }) });
    requests = [];
    const container = document.createElement('div');
    document.body.append(container);
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });

    http.defaults.adapter = async (config) => {
        requests.push({ url: config.url, params: config.params });

        return {
            config,
            status: 200,
            statusText: 'OK',
            headers: {},
            data: {
                code: 200,
                message: 'success',
                timestamp: 0,
                data: config.url === '/system/operation-logs/options'
                    ? {
                          users: [{ id: 1, name: '张三' }],
                          actions: [{ action: operationLog.action, action_name: operationLog.action_name }],
                      }
                    : config.url === '/system/login-logs'
                        ? loginPage
                        : page,
            },
        };
    };

    await act(async () => {
        root = createRoot(container);
        root.render(
            <QueryClientProvider client={client}>
                <AntdApp>
                    <AuditLogs />
                </AntdApp>
            </QueryClientProvider>,
        );
    });
    await act(async () => { await new Promise((resolve) => setTimeout(resolve, 30)); });

    return client;
}

async function openTab(label: string) {
    const tab = [...document.querySelectorAll('[role="tab"]')]
        .find((item) => item.textContent === label);

    expect(tab).toBeDefined();

    await act(async () => {
        (tab as HTMLElement).click();
        await new Promise((resolve) => setTimeout(resolve, 0));
    });
}

async function waitFor(predicate: () => boolean, timeout = 1500) {
    const startedAt = Date.now();

    while (!predicate()) {
        if (Date.now() - startedAt > timeout) {
            throw new Error('timed out waiting for condition');
        }

        await act(async () => { await new Promise((resolve) => setTimeout(resolve, 20)); });
    }
}

async function chooseOption(placeholder: string, label: string) {
    const select = [...document.querySelectorAll('.ant-select')]
        .find((item) => item.textContent?.includes(placeholder)) as HTMLElement | undefined;

    if (!select) {
        throw new Error(`select not found: ${placeholder}`);
    }

    const input = select.querySelector('input') as HTMLInputElement;

    await act(async () => {
        select.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
        input.focus();
        await new Promise((resolve) => setTimeout(resolve, 0));
    });

    await waitFor(() => [...document.querySelectorAll('.ant-select-item-option')]
        .some((item) => item.textContent === label));

    const option = [...document.querySelectorAll('.ant-select-item-option')]
        .find((item) => item.textContent === label);

    expect(option).toBeDefined();

    await act(async () => {
        option!.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        await new Promise((resolve) => setTimeout(resolve, 30));
    });
}

afterEach(async () => {
    await act(async () => root?.unmount());
    document.body.innerHTML = '';
});

describe('操作与登录日志页', () => {
    it('renders the actor, action, object, summary and ip', async () => {
        await renderLogs(['system.logs.index']);

        expect(document.body.textContent).toContain('张三');
        expect(document.body.textContent).toContain('订单状态流转');
        expect(document.body.textContent).toContain('生产订单 PO-001');
        expect(document.body.textContent).toContain('草稿 → 待审核');
        expect(document.body.textContent).toContain('127.0.0.1');
    });

    it('switches to the login log tab and renders the result', async () => {
        await renderLogs(['system.logs.index']);
        await openTab('登录日志');
        await waitFor(() => document.body.textContent?.includes('alice@mes.test') ?? false);

        expect(document.body.textContent).toContain('alice@mes.test');
        expect(document.body.textContent).toContain('密码错误');
        expect(document.body.textContent).toContain('失败');
    });

    it('sends the selected action filter to the operation log API', async () => {
        await renderLogs(['system.logs.index']);
        await chooseOption('动作', '订单状态流转');
        await waitFor(() => requests.some((request) => request.url === '/system/operation-logs'
            && request.params?.action === 'production.order.transition'));

        expect(requests.some((request) => request.url === '/system/operation-logs'
            && request.params?.action === 'production.order.transition')).toBe(true);
    });

    it('sends the selected login result filter to the login log API', async () => {
        await renderLogs(['system.logs.index']);
        await openTab('登录日志');
        await chooseOption('结果', '失败');
        await waitFor(() => requests.some((request) => request.url === '/system/login-logs'
            && request.params?.success === false));

        expect(requests.some((request) => request.url === '/system/login-logs'
            && request.params?.success === false)).toBe(true);
    });

    it('shows an empty state without the log permission', async () => {
        await renderLogs(['menu.system.logs']);

        expect(document.body.textContent).toContain('暂无日志查看权限');
    });

    it('formats empty, boolean and list change values', () => {
        expect(formatChangeValue(null)).toBe('空');
        expect(formatChangeValue(false)).toBe('否');
        expect(formatChangeValue(['A', 'B'])).toBe('A、B');
    });
});
