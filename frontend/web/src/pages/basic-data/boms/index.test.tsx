import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { App as AntdApp } from 'antd';
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import type { Root } from 'react-dom/client';
import { afterEach, beforeEach, expect, it } from 'vitest';
import Boms from './index';
import { http } from '@/api/client';
import { useAuthStore } from '@/stores/auth';
import { currentUserFixture } from '@/test/fixtures';

(globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
window.matchMedia ??= ((query: string) => ({ matches: false, media: query, onchange: null, addListener() {}, removeListener() {}, addEventListener() {}, removeEventListener() {}, dispatchEvent: () => false })) as unknown as typeof window.matchMedia;

let root: Root;
const originalAdapter = http.defaults.adapter;
const requested: string[] = [];
const writes: { url?: string; data: unknown }[] = [];

beforeEach(() => {
    requested.length = 0;
    writes.length = 0;
    http.defaults.adapter = async (config) => {
        requested.push(config.url ?? '');
        if (config.method === 'put') writes.push({ url: config.url, data: JSON.parse(config.data) });
        const data = config.url?.includes('/basic-data/boms')
            ? { items: [{ id: 2, code: 'BOM-P001-01', product_version_id: 3, version: 'V1.0', audit_status: 'draft', audited_at: null, remark: null, status: true, product_version: { id: 3, version: 'V1', product: { id: 1, code: 'P001', name: '智能电表' } } }], meta: { current_page: 1, per_page: 15, total: 1, last_page: 1 } }
            : { items: [], meta: { current_page: 1, per_page: 100, total: 0, last_page: 1 } };
        return { config, status: 200, statusText: 'OK', headers: {}, data: { code: 200, message: 'success', timestamp: 0, data } };
    };
});

afterEach(() => {
    act(() => root?.unmount());
    http.defaults.adapter = originalAdapter;
    document.body.innerHTML = '';
});

it('lists BOMs and disables one using a status-only update', async () => {
    useAuthStore.setState({ token: 'token', user: currentUserFixture({ permissions: ['basic.boms.index', 'button.basic.boms.update'] }) });
    const container = document.createElement('div');
    document.body.append(container);
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });

    await act(async () => {
        root = createRoot(container);
        root.render(<QueryClientProvider client={client}><AntdApp><Boms /></AntdApp></QueryClientProvider>);
    });
    await act(async () => { await new Promise((resolve) => setTimeout(resolve, 20)); });

    expect(requested).toContain('/basic-data/boms');
    expect(document.body.textContent).toContain('BOM-P001-01');
    expect(document.body.textContent).toContain('V1.0');
    expect(document.body.textContent).toContain('草稿');
    await act(async () => {
        [...document.querySelectorAll('button')].find((button) => button.textContent === '停用')?.click();
    });
    expect(writes).toEqual([{ url: '/basic-data/boms/2', data: { status: false } }]);
});
