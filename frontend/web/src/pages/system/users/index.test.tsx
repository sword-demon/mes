import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { App as AntdApp } from 'antd';
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import type { Root } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import Users from './index';
import { http } from '@/api/client';
import { useAuthStore } from '@/stores/auth';
import { currentUserFixture, userFixture } from '@/test/fixtures';

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

let requested: string[] = [];
let root: Root;

async function renderUsers() {
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
                    <Users />
                </AntdApp>
            </QueryClientProvider>,
        );
    });

    return container;
}

describe('用户管理页', () => {
    beforeEach(() => {
        requested = [];
        http.defaults.adapter = async (config) => {
            requested.push(config.url ?? '');
            const data = config.url?.includes('/system/roles')
                ? { items: [], meta: { current_page: 1, per_page: 15, total: 0, last_page: 1 } }
                : { items: [userFixture()], meta: { current_page: 1, per_page: 15, total: 1, last_page: 1 } };

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

    it('hides the role field when the user cannot assign roles', async () => {
        useAuthStore.setState({
            token: 'token',
            user: currentUserFixture({
                permissions: ['menu.system.users', 'page.system.users', 'system.users.index', 'button.system.users.update'],
            }),
        });

        const container = await renderUsers();

        // The row action only exists once the user list resolves.
        await act(async () => {
            await new Promise((resolve) => setTimeout(resolve, 0));
        });

        await act(async () => {
            container.querySelector<HTMLButtonElement>('button.ant-btn-link')?.click();
            await new Promise((resolve) => setTimeout(resolve, 0));
        });

        expect(document.body.querySelector('#role_ids')).toBeNull();
        expect(requested.some((url) => url.includes('/system/roles'))).toBe(false);
    });

    it('shows the role field when the user can assign roles', async () => {
        useAuthStore.setState({
            token: 'token',
            user: currentUserFixture({
                permissions: [
                    'menu.system.users',
                    'page.system.users',
                    'system.users.index',
                    'button.system.users.update',
                    'button.system.users.assign-roles',
                ],
            }),
        });

        const container = await renderUsers();

        // The row action only exists once the user list resolves.
        await act(async () => {
            await new Promise((resolve) => setTimeout(resolve, 0));
        });

        await act(async () => {
            container.querySelector<HTMLButtonElement>('button.ant-btn-link')?.click();
            await new Promise((resolve) => setTimeout(resolve, 0));
        });

        expect(document.body.querySelector('#role_ids')).not.toBeNull();
    });
});
