import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { App as AntdApp } from 'antd';
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import type { Root } from 'react-dom/client';
import { afterEach, beforeEach, expect, it } from 'vitest';
import Processes from './index';
import { http } from '@/api/client';
import { useAuthStore } from '@/stores/auth';
import { currentUserFixture } from '@/test/fixtures';

(globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
window.matchMedia ??= ((query: string) => ({ matches: false, media: query, onchange: null, addListener() {}, removeListener() {}, addEventListener() {}, removeEventListener() {}, dispatchEvent: () => false })) as unknown as typeof window.matchMedia;

let root: Root;
const originalAdapter = http.defaults.adapter;
let requested = '';

beforeEach(() => {
    http.defaults.adapter = async (config) => {
        requested = config.url ?? '';
        return {
            config,
            status: 200,
            statusText: 'OK',
            headers: {},
            data: { code: 200, message: 'success', timestamp: 0, data: { items: [{ id: 1, code: 'OP01', name: '装配', type: '加工', standard_hours: 1.5, requires_inspection: true, description: null, status: true }], meta: { current_page: 1, per_page: 15, total: 1, last_page: 1 } } },
        };
    };
});

afterEach(() => {
    act(() => root?.unmount());
    http.defaults.adapter = originalAdapter;
    document.body.innerHTML = '';
});

it('lists processes and their inspection requirement', async () => {
    useAuthStore.setState({ token: 'token', user: currentUserFixture({ permissions: ['basic.processes.index'] }) });
    const container = document.createElement('div');
    document.body.append(container);
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });

    await act(async () => {
        root = createRoot(container);
        root.render(<QueryClientProvider client={client}><AntdApp><Processes /></AntdApp></QueryClientProvider>);
    });
    await act(async () => { await new Promise((resolve) => setTimeout(resolve, 20)); });

    expect(requested).toBe('/basic-data/processes');
    expect(document.body.textContent).toContain('装配');
    expect(document.body.textContent).toContain('需检验');
    expect([...document.querySelectorAll('button')].some((button) => button.textContent === '编辑')).toBe(false);
    client.clear();
});
