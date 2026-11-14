import { act } from 'react';
import { createRoot } from 'react-dom/client';
import { beforeEach, describe, expect, it } from 'vitest';
import Can from './can';
import { useAuthStore } from '@/stores/auth';
import { currentUserFixture } from '@/test/fixtures';

(globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;

const user = (permissions: string[]) => currentUserFixture({ permissions });

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
