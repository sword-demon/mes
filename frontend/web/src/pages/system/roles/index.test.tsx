import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { App as AntdApp } from 'antd';
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import type { Root } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import Roles from './index';
import { http } from '@/api/client';
import { useAuthStore } from '@/stores/auth';
import { currentUserFixture } from '@/test/fixtures';

(globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;

// jsdom has no matchMedia; antd's responsive observer needs it.
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

const role = {
    id: 3,
    code: 'quality_inspector',
    name: '质检主管',
    description: '负责质量判定',
    status: true,
    permissions: [{ id: 9, code: 'quality.inspections.index', name: '检验任务列表', type: 'api' }],
};

let requested: string[] = [];
let root: Root;

async function renderRoles() {
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
                    <Roles />
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

describe('角色管理页', () => {
    beforeEach(() => {
        requested = [];
        http.defaults.adapter = async (config) => {
            requested.push(config.url ?? '');
            const data = config.url?.includes('/system/permissions')
                ? [{ id: 9, code: 'quality.inspections.index', name: '检验任务列表', type: 'api' }]
                : {
                      items: [role],
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
        act(() => root?.unmount());
        document.body.innerHTML = '';
    });

    it('lists the roles returned by the api', async () => {
        useAuthStore.setState({
            token: 'token',
            user: currentUserFixture({ permissions: ['system.roles.index'] }),
        });

        await renderRoles();

        await act(async () => {
            await new Promise((resolve) => setTimeout(resolve, 0));
        });

        expect(document.body.textContent).toContain('质检主管');
        expect(requested.some((url) => url.includes('/system/roles'))).toBe(true);
    });

    it('hides the create button without the create permission', async () => {
        useAuthStore.setState({
            token: 'token',
            user: currentUserFixture({ permissions: ['system.roles.index'] }),
        });

        await renderRoles();

        await act(async () => {
            await new Promise((resolve) => setTimeout(resolve, 0));
        });

        expect(createButton('新增角色')).toBe(false);
        // The permission picker is only needed while the form is open.
        expect(requested.some((url) => url.includes('/system/permissions'))).toBe(false);
    });

    it('shows the create button with the create permission', async () => {
        useAuthStore.setState({
            token: 'token',
            user: currentUserFixture({
                permissions: ['system.roles.index', 'button.system.roles.create'],
            }),
        });

        await renderRoles();

        await act(async () => {
            await new Promise((resolve) => setTimeout(resolve, 0));
        });

        expect(createButton('新增角色')).toBe(true);
    });
});
