import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { App as AntdApp, ConfigProvider } from 'antd';
import zhCN from 'antd/locale/zh_CN';
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import Materials from './index';
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

function pageText() {
    return document.body.textContent?.replace(/\s/g, '') ?? '';
}

type Call = {
    method: string;
    url: string;
    params?: Record<string, unknown>;
    body?: Record<string, unknown>;
};

let calls: Call[] = [];

async function renderPage() {
    const container = document.createElement('div');
    document.body.append(container);

    const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });

    await act(async () => {
        createRoot(container).render(
            <QueryClientProvider client={queryClient}>
                <ConfigProvider locale={zhCN}>
                    <AntdApp>
                        <Materials />
                    </AntdApp>
                </ConfigProvider>
            </QueryClientProvider>,
        );
    });

    await act(async () => {
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

async function setInput(selector: string, value: string) {
    const input = document.querySelector(selector) as HTMLInputElement;
    const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value')!.set!;

    await act(async () => {
        setter.call(input, value);
        input.dispatchEvent(new Event('input', { bubbles: true }));
        await new Promise((resolve) => setTimeout(resolve, 0));
    });
}

async function selectOption(scope: string, type: string) {
    const selector = document.querySelector(`${scope} .ant-select`) as HTMLElement;
    const input = selector.querySelector('input') as HTMLInputElement;

    await act(async () => {
        selector.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
        input.focus();
        await new Promise((resolve) => setTimeout(resolve, 0));
    });

    const option = [...document.querySelectorAll('.ant-select-item-option')].find(
        (candidate) => candidate.textContent === type,
    );

    expect(option).toBeDefined();

    await act(async () => {
        option!.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        await new Promise((resolve) => setTimeout(resolve, 0));
    });
}

describe('物料管理页', () => {
    beforeEach(() => {
        calls = [];
        http.defaults.adapter = async (config) => {
            calls.push({
                method: config.method ?? 'get',
                url: config.url ?? '',
                params: config.params as Record<string, unknown> | undefined,
                body: config.data ? JSON.parse(String(config.data)) : undefined,
            });

            const data = {
                items: [
                    {
                        id: 7,
                        code: 'M001',
                        name: '冷轧钢板',
                        type: '原材料',
                        specification: 'SPCC 1.0mm',
                        unit: 'kg',
                        min_stock: 200,
                        safety_stock: 500,
                        batch_managed: true,
                        status: true,
                    },
                ],
                meta: { current_page: 1, per_page: 15, total: 1, last_page: 1 },
            };

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

    it('shows stock thresholds and batch management, and hides writes without permissions', async () => {
        useAuthStore.setState({
            token: 'token',
            user: currentUserFixture({ permissions: ['basic.materials.index'] }),
        });

        await renderPage();

        expect(pageText()).toContain('M001');
        expect(pageText()).toContain('原材料');
        expect(pageText()).toContain('500');
        expect(pageText()).toContain('批次');
        expect(pageText()).not.toContain('新增');
        expect(pageText()).not.toContain('编辑');
        expect(pageText()).not.toContain('停用');
        expect(pageText()).not.toContain('删除');
    });

    it('offers creation with the material create permission', async () => {
        useAuthStore.setState({
            token: 'token',
            user: currentUserFixture({
                permissions: ['basic.materials.index', 'button.basic.materials.create'],
            }),
        });

        await renderPage();

        expect(pageText()).toContain('新增');
    });

    it('submits stock thresholds and batch management when creating', async () => {
        useAuthStore.setState({
            token: 'token',
            user: currentUserFixture({
                permissions: ['basic.materials.index', 'button.basic.materials.create'],
            }),
        });

        await renderPage();
        await clickButton('新增');
        await setInput('#code', 'M011');
        await setInput('#name', '助焊剂');
        await selectOption('.ant-modal', '辅助材料');
        await setInput('#unit', '瓶');
        await setInput('#min_stock', '20');
        await setInput('#safety_stock', '60');

        await act(async () => {
            (document.querySelector('#batch_managed') as HTMLButtonElement).click();
            await new Promise((resolve) => setTimeout(resolve, 0));
        });

        await clickButton('确定');

        const post = calls.find((call) => call.method === 'post');

        expect(post?.url).toBe('/basic-data/materials');
        expect(post?.body).toMatchObject({
            code: 'M011',
            name: '助焊剂',
            type: '辅助材料',
            unit: '瓶',
            min_stock: 20,
            safety_stock: 60,
            batch_managed: true,
        });
    });

    it('sends the selected type when filtering', async () => {
        useAuthStore.setState({
            token: 'token',
            user: currentUserFixture({ permissions: ['basic.materials.index'] }),
        });

        await renderPage();
        await selectOption('body', '原材料');
        await act(async () => {
            await new Promise((resolve) => setTimeout(resolve, 20));
        });

        const lastList = calls.filter((call) => call.method === 'get').at(-1);

        expect(lastList?.params).toMatchObject({ type: '原材料' });
    });

    it('disables a material through a status-only update', async () => {
        useAuthStore.setState({
            token: 'token',
            user: currentUserFixture({
                permissions: ['basic.materials.index', 'button.basic.materials.update'],
            }),
        });

        await renderPage();
        await clickButton('停用');

        await act(async () => {
            document.body
                .querySelector<HTMLButtonElement>('.ant-popconfirm-buttons .ant-btn-primary')
                ?.click();
            await new Promise((resolve) => setTimeout(resolve, 0));
        });

        const put = calls.find((call) => call.method === 'put');

        expect(put?.url).toBe('/basic-data/materials/7');
        expect(put?.body).toEqual({ status: false });
    });
});
