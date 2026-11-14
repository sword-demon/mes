import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { App as AntdApp, ConfigProvider } from 'antd';
import zhCN from 'antd/locale/zh_CN';
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import Products from './index';
import type { Product, ProductVersion } from '@/api/products';
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

const products: {
    items: Product[];
    meta: { current_page: number; per_page: number; total: number; last_page: number };
} = {
    items: [
        {
            id: 7,
            code: 'P001',
            name: '智能电表',
            model: 'DTS-100',
            specification: '220V 5(60)A',
            category: '成品',
            unit: '台',
            status: true,
        },
    ],
    meta: { current_page: 1, per_page: 15, total: 1, last_page: 1 },
};

const versions: {
    items: ProductVersion[];
    meta: { current_page: number; per_page: number; total: number; last_page: number };
} = {
    items: [
        {
            id: 11,
            product_id: 7,
            version: 'V1.0',
            remark: '首版量产',
            status: true,
            created_at: '2026-11-15T00:00:00Z',
            updated_at: '2026-11-15T00:00:00Z',
        },
        {
            id: 12,
            product_id: 7,
            version: 'V2.0',
            remark: '增加远程通信',
            status: true,
            created_at: '2026-11-15T00:00:00Z',
            updated_at: '2026-11-15T00:00:00Z',
        },
    ],
    meta: { current_page: 1, per_page: 100, total: 2, last_page: 1 },
};

let versionItems: typeof versions.items = [];

async function flush() {
    await act(async () => {
        await new Promise((resolve) => setTimeout(resolve, 20));
    });
}

async function renderPage() {
    const container = document.createElement('div');
    document.body.append(container);

    const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });

    await act(async () => {
        createRoot(container).render(
            <QueryClientProvider client={queryClient}>
                <ConfigProvider locale={zhCN}>
                    <AntdApp>
                        <Products />
                    </AntdApp>
                </ConfigProvider>
            </QueryClientProvider>,
        );
    });

    await act(async () => {
        await new Promise((resolve) => setTimeout(resolve, 0));
    });
}

async function clickByText(selector: string, label: string) {
    const element = [...document.querySelectorAll(selector)].find(
        (candidate) => candidate.textContent === label,
    );

    expect(element).toBeDefined();

    await act(async () => {
        element!.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        await new Promise((resolve) => setTimeout(resolve, 0));
        await new Promise((resolve) => setTimeout(resolve, 0));
    });
}

async function clickRadio(label: string) {
    const option = [...document.querySelectorAll('label')].find(
        (candidate) => candidate.textContent === label,
    );
    const input = option?.querySelector('input[type="radio"]');

    expect(input).toBeDefined();

    await act(async () => {
        input!.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        await new Promise((resolve) => setTimeout(resolve, 0));
        await new Promise((resolve) => setTimeout(resolve, 0));
    });
}

async function clickButton(label: string) {
    const normalize = (text: string | null) => text?.replace(/\s/g, '') ?? '';
    const button = [...document.querySelectorAll('button')].find(
        (candidate) => normalize(candidate.textContent) === label,
    );

    expect(button).toBeDefined();

    await act(async () => {
        button!.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        await new Promise((resolve) => setTimeout(resolve, 0));
        await new Promise((resolve) => setTimeout(resolve, 0));
    });
}

describe('产品管理页', () => {
    beforeEach(() => {
        versionItems = versions.items.map((version) => ({ ...version }));

        useAuthStore.setState({
            token: 'token',
            user: currentUserFixture({
                permissions: [
                    'basic.products.index',
                    'button.basic.products.create',
                    'button.basic.products.update',
                ],
            }),
        });

        http.defaults.adapter = async (config) => {
            const url = config.url ?? '';
            let data: typeof products | typeof versions = products;

            if (url.includes('/versions')) {
                if (config.method === 'post') {
                    const body = JSON.parse(String(config.data)) as {
                        version: string;
                        remark?: string;
                        status?: boolean;
                    };
                    const created = {
                        id: 99,
                        product_id: 7,
                        version: body.version,
                        remark: body.remark ?? null,
                        status: body.status ?? true,
                        created_at: '2026-11-15T00:00:00Z',
                        updated_at: '2026-11-15T00:00:00Z',
                    };
                    versionItems = [...versionItems, created];
                    data = { ...versions, items: [created] };
                } else {
                    data = {
                        items: versionItems,
                        meta: { ...versions.meta, total: versionItems.length },
                    };
                }
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

    it('switches between versions to inspect each one', async () => {
        await renderPage();

        expect(document.body.textContent).toContain('P001');

        await clickByText('button', '版本');

        expect(document.body.textContent).toContain('首版量产');

        await clickRadio('V2.0');

        expect(document.body.textContent).toContain('增加远程通信');
    });

    it('hides the version entry without the update permission', async () => {
        useAuthStore.setState({
            token: 'token',
            user: currentUserFixture({ permissions: ['basic.products.index'] }),
        });

        await renderPage();

        expect(document.body.textContent).not.toContain('版本');
        expect(document.body.textContent).not.toContain('新增版本');
        expect(document.body.textContent).not.toContain('编辑当前版本');
    });

    it('clears the previous version values when switching from editing to creating', async () => {
        await renderPage();
        await clickButton('版本');
        await clickButton('新增版本');

        expect((document.querySelector('input#version') as HTMLInputElement).value).toBe('');

        await clickButton('取消');
        await clickButton('编辑当前版本');

        expect((document.querySelector('input#version') as HTMLInputElement).value).toBe('V1.0');

        await clickButton('取消');
        await clickButton('新增版本');

        expect((document.querySelector('input#version') as HTMLInputElement).value).toBe('');
    });

    it('selects the version it just created', async () => {
        await renderPage();
        await clickButton('版本');
        await clickButton('新增版本');

        const input = document.querySelector('input#version') as HTMLInputElement;

        await act(async () => {
            const setValue = Object.getOwnPropertyDescriptor(
                HTMLInputElement.prototype,
                'value',
            )!.set!;
            setValue.call(input, 'V3.0');
            input.dispatchEvent(new Event('input', { bubbles: true }));
        });

        await clickButton('确定');
        await flush();

        expect(document.body.textContent).toContain('V3.0');
    });
});
