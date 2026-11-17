import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { App as AntdApp } from 'antd';
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import type { Root } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import Menus from './index';
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

const menus = [
    {
        id: 1,
        parent_id: null,
        permission_id: 5,
        name: '系统管理',
        path: null,
        icon: 'SettingOutlined',
        sort: 1,
        children: [
            {
                id: 2,
                parent_id: 1,
                permission_id: 6,
                name: '角色管理',
                path: '/system/roles',
                icon: null,
                sort: 1,
                children: [],
            },
        ],
    },
];

let requested: string[] = [];
let root: Root;

async function renderMenus() {
    const container = document.createElement('div');
    document.body.append(container);

    const queryClient = new QueryClient({
        defaultOptions: { queries: { retry: false } },
    });

    await act(async () => {
        root = createRoot(container);
        root.render(
            <QueryClientProvider client={queryClient}>
                <AntdApp>
                    <Menus />
                </AntdApp>
            </QueryClientProvider>,
        );
    });

    return container;
}

function createButton(label: string): boolean {
    return [...document.body.querySelectorAll('button')].some((button) =>
        button.textContent?.includes(label),
    );
}

describe('菜单管理页', () => {
    beforeEach(() => {
        requested = [];
        http.defaults.adapter = async (config) => {
            requested.push(config.url ?? '');
            const data = config.url?.includes('/system/permissions') ? [] : menus;

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
        act(() => root?.unmount());
        document.body.innerHTML = '';
    });

    it('renders the menu tree returned by the api', async () => {
        useAuthStore.setState({
            token: 'token',
            user: currentUserFixture({ permissions: ['system.menus.index'] }),
        });

        await renderMenus();

        await act(async () => {
            await new Promise((resolve) => setTimeout(resolve, 0));
        });

        expect(document.body.textContent).toContain('系统管理');
        expect(document.body.textContent).toContain('角色管理');
        expect(requested.some((url) => url.includes('/system/menus'))).toBe(true);
    });

    it('hides the create button without the create permission', async () => {
        useAuthStore.setState({
            token: 'token',
            user: currentUserFixture({ permissions: ['system.menus.index'] }),
        });

        await renderMenus();

        await act(async () => {
            await new Promise((resolve) => setTimeout(resolve, 0));
        });

        expect(createButton('新增菜单')).toBe(false);
        expect(requested.some((url) => url.includes('/system/permissions'))).toBe(false);
    });

    it('shows the create button with the create permission', async () => {
        useAuthStore.setState({
            token: 'token',
            user: currentUserFixture({
                permissions: ['system.menus.index', 'button.system.menus.create'],
            }),
        });

        await renderMenus();

        await act(async () => {
            await new Promise((resolve) => setTimeout(resolve, 0));
        });

        expect(createButton('新增菜单')).toBe(true);
    });
});
