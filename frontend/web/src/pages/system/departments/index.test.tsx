import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { App as AntdApp } from 'antd';
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import type { Root } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import Departments from './index';
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

const departments = [
    {
        id: 1,
        parent_id: null,
        code: 'root',
        name: '演示工厂',
        sort: 1,
        children: [
            {
                id: 2,
                parent_id: 1,
                code: 'production',
                name: '生产部',
                sort: 1,
                children: [],
            },
        ],
    },
];

let requested: string[] = [];
let root: Root;

async function renderDepartments() {
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
                    <Departments />
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

describe('部门管理页', () => {
    beforeEach(() => {
        requested = [];
        http.defaults.adapter = async (config) => {
            requested.push(config.url ?? '');

            return {
                data: { code: 200, message: 'success', data: departments, timestamp: 0 },
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

    it('renders the department tree returned by the api', async () => {
        useAuthStore.setState({
            token: 'token',
            user: currentUserFixture({ permissions: ['system.departments.index'] }),
        });

        await renderDepartments();

        await act(async () => {
            await new Promise((resolve) => setTimeout(resolve, 0));
        });

        expect(document.body.textContent).toContain('演示工厂');
        expect(document.body.textContent).toContain('生产部');
        expect(requested.some((url) => url.includes('/system/departments'))).toBe(true);
    });

    it('hides the create button without the create permission', async () => {
        useAuthStore.setState({
            token: 'token',
            user: currentUserFixture({ permissions: ['system.departments.index'] }),
        });

        await renderDepartments();

        await act(async () => {
            await new Promise((resolve) => setTimeout(resolve, 0));
        });

        expect(createButton('新增部门')).toBe(false);
    });

    it('shows the create button with the create permission', async () => {
        useAuthStore.setState({
            token: 'token',
            user: currentUserFixture({
                permissions: ['system.departments.index', 'button.system.departments.create'],
            }),
        });

        await renderDepartments();

        await act(async () => {
            await new Promise((resolve) => setTimeout(resolve, 0));
        });

        expect(createButton('新增部门')).toBe(true);
    });
});
