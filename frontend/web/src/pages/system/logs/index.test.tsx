import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { App as AntdApp } from 'antd';
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import type { Root } from 'react-dom/client';
import { afterEach, describe, expect, it } from 'vitest';
import OperationLogs, { formatChangeValue } from './index';
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

const log = {
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

async function renderLogs(permissions: string[]) {
    useAuthStore.setState({ token: 'token', user: currentUserFixture({ permissions }) });
    const container = document.createElement('div');
    document.body.append(container);
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });

    http.defaults.adapter = async (config) => ({
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
                      actions: [{ action: log.action, action_name: log.action_name }],
                  }
                : { items: [log], meta: { current_page: 1, per_page: 15, total: 1, last_page: 1 } },
        },
    });

    await act(async () => {
        root = createRoot(container);
        root.render(
            <QueryClientProvider client={client}>
                <AntdApp>
                    <OperationLogs />
                </AntdApp>
            </QueryClientProvider>,
        );
    });
    await act(async () => { await new Promise((resolve) => setTimeout(resolve, 30)); });

    return client;
}

afterEach(async () => {
    await act(async () => root?.unmount());
    document.body.innerHTML = '';
});

describe('操作日志页', () => {
    it('renders the actor, action, object, summary and ip', async () => {
        await renderLogs(['system.logs.index']);

        expect(document.body.textContent).toContain('张三');
        expect(document.body.textContent).toContain('订单状态流转');
        expect(document.body.textContent).toContain('生产订单 PO-001');
        expect(document.body.textContent).toContain('草稿 → 待审核');
        expect(document.body.textContent).toContain('127.0.0.1');
    });

    it('shows an empty state without the log permission', async () => {
        await renderLogs(['menu.system.logs']);

        expect(document.body.textContent).toContain('暂无操作日志查看权限');
    });

    it('formats empty, boolean and list change values', () => {
        expect(formatChangeValue(null)).toBe('空');
        expect(formatChangeValue(false)).toBe('否');
        expect(formatChangeValue(['A', 'B'])).toBe('A、B');
    });
});
