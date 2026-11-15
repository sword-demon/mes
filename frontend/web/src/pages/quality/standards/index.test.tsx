import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { App as AntdApp } from 'antd';
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import type { Root } from 'react-dom/client';
import { afterEach, expect, it } from 'vitest';
import QualityStandards from './index';
import { http } from '@/api/client';
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
let saved: Record<string, unknown> | undefined;

async function render(permissions: string[]) {
    saved = undefined;
    useAuthStore.setState({ token: 'token', user: currentUserFixture({ permissions }) });
    const container = document.createElement('div');
    document.body.append(container);
    root = createRoot(container);
    client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
    http.defaults.adapter = async (config) => {
        if (config.method === 'put') {
            saved = JSON.parse(config.data);
        }
        return ({
        config, status: 200, statusText: 'OK', headers: {},
        data: { code: 200, data: {
            items: config.url === '/quality/inspection-items'
                ? [{ id: 1, code: 'TEMP', name: '工作温度', unit: '℃', standard_value: '25.000', min_value: '-10.000', max_value: '60.000', status: true }]
                : [{ id: 2, code: 'D001', name: '表面划伤', category: '外观', status: true }],
            meta: { current_page: 1, per_page: 15, total: 1, last_page: 1 },
        } },
    });
    };
    await act(async () => {
        root.render(<QueryClientProvider client={client}><AntdApp><QualityStandards /></AntdApp></QueryClientProvider>);
    });
    await act(async () => { await new Promise((resolve) => setTimeout(resolve, 30)); });
}

afterEach(async () => {
    await act(async () => root?.unmount());
    client?.clear();
    http.defaults.adapter = originalAdapter;
    document.body.innerHTML = '';
});

it('shows numerical standards and hides maintenance actions for readers', async () => {
    await render(['quality.standards.index']);
    expect(document.body.textContent).toContain('工作温度');
    expect(document.body.textContent).toContain('-10.000');
    expect(document.body.textContent).not.toContain('新增');
    expect(document.body.textContent).not.toContain('编辑');
    expect(document.body.textContent).not.toContain('缺陷代码');
});

it('explains the empty state when the menu is visible but no quality permission is granted', async () => {
    await render([]);
    expect(document.body.textContent).toContain('暂无检验标准查看权限');
});

it('opens an editable standard with negative values and switches to classified defects', async () => {
    await render(['quality.standards.index', 'button.quality.standards.update', 'quality.defect-codes.index']);
    const edit = [...document.querySelectorAll('button')].find((button) => button.textContent?.replace(/\s/g, '') === '编辑');
    expect(edit).toBeDefined();
    await act(async () => { edit!.click(); });
    expect((document.querySelector('input#min_value') as HTMLInputElement).value).toBe('-10');
    expect(document.querySelector('input#min_value')?.getAttribute('aria-valuemin')).toBe('-999999999.999');
    const save = document.querySelector('.ant-modal-footer button.ant-btn-primary') as HTMLButtonElement;
    await act(async () => { save.click(); });
    await act(async () => { await new Promise((resolve) => setTimeout(resolve, 30)); });
    expect(saved).toMatchObject({ code: 'TEMP', min_value: '-10.000', standard_value: '25.000' });
    const defects = [...document.querySelectorAll('[role="tab"]')].find((tab) => tab.textContent === '缺陷代码');
    await act(async () => { (defects as HTMLElement).click(); });
    await act(async () => { await new Promise((resolve) => setTimeout(resolve, 30)); });
    expect(document.body.textContent).toContain('表面划伤');
    expect(document.body.textContent).toContain('外观');
});
