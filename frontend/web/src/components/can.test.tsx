import { act } from 'react';
import { createRoot } from 'react-dom/client';
import { beforeEach, describe, expect, it } from 'vitest';
import Can from './can';
import { useAuthStore } from '@/stores/auth';
import type { CurrentUser } from '@/types/api';

(globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;

const user = (permissions: string[]): CurrentUser => ({
    id: 1,
    username: 'admin',
    name: '管理员',
    email: 'admin@mes.test',
    phone: null,
    department_id: null,
    position: null,
    status: true,
    email_verified_at: null,
    created_at: '2026-11-15T00:00:00Z',
    updated_at: '2026-11-15T00:00:00Z',
    permissions,
    data_scope: 'all',
    menus: [],
});

const render = () => {
    const container = document.createElement('div');

    act(() => {
        createRoot(container).render(
            <Can code="button.system.users.create">
                <button>新增用户</button>
            </Can>,
        );
    });

    return container.innerHTML;
};

describe('Can', () => {
    beforeEach(() => {
        useAuthStore.setState({ token: null, user: null });
    });

    it('renders the action when the button permission is granted', () => {
        useAuthStore.setState({ token: 't', user: user(['button.system.users.create']) });

        expect(render()).toContain('新增用户');
    });

    it('renders nothing when the button permission is missing', () => {
        useAuthStore.setState({ token: 't', user: user(['button.system.users.index']) });

        expect(render()).toBe('');
    });

    it('renders nothing before the current user is loaded', () => {
        useAuthStore.setState({ token: 't', user: null });

        expect(render()).toBe('');
    });
});
