import { describe, expect, it } from 'vitest';
import { can, findMenu, firstMenuPath, menuTrail } from './permission';
import type { CurrentUser, MenuNode } from '@/types/api';

const menu = (
    id: number,
    name: string,
    path: string | null,
    children: MenuNode[] = [],
): MenuNode => ({
    id,
    parent_id: null,
    permission_id: id,
    name,
    path,
    icon: null,
    sort: id,
    children,
});

const menus: MenuNode[] = [
    menu(1, '首页驾驶舱', null, [
        menu(2, '生产数据总览', '/dashboard/overview'),
        menu(3, '生产趋势', '/dashboard/trend'),
    ]),
    menu(4, '生产管理', null, [menu(5, '生产订单', '/production/orders')]),
    menu(6, '系统管理', '/system'),
];

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
    menus,
});

describe('can', () => {
    it('allows a granted permission code', () => {
        expect(can(user(['button.system.users.create']), 'button.system.users.create')).toBe(
            true,
        );
    });

    it('denies a code the user was not granted', () => {
        expect(can(user(['button.system.users.index']), 'button.system.users.create')).toBe(
            false,
        );
    });

    it('denies everyone before the current user is loaded', () => {
        expect(can(null, 'button.system.users.create')).toBe(false);
    });
});

describe('findMenu', () => {
    it('finds a nested menu by path', () => {
        expect(findMenu(menus, '/production/orders')?.name).toBe('生产订单');
    });

    it('returns null for an unknown path', () => {
        expect(findMenu(menus, '/system/logs')).toBeNull();
    });

    it('does not match a group node by its own missing path', () => {
        expect(findMenu(menus, '')).toBeNull();
    });
});

describe('menuTrail', () => {
    it('returns the root-first ancestor chain of a nested path', () => {
        expect(menuTrail(menus, '/production/orders').map((node) => node.name)).toEqual([
            '生产管理',
            '生产订单',
        ]);
    });

    it('is empty for a path the user cannot reach', () => {
        expect(menuTrail(menus, '/system/logs')).toEqual([]);
    });
});

describe('firstMenuPath', () => {
    it('returns the first reachable page path in tree order', () => {
        expect(firstMenuPath(menus)).toBe('/dashboard/overview');
    });

    it('skips groups without a path', () => {
        expect(firstMenuPath([menu(1, '生产管理', null, [menu(2, '生产订单', '/production/orders')])]))
            .toBe('/production/orders');
    });

    it('prefers a child page over the group path', () => {
        expect(firstMenuPath([menu(1, '首页驾驶舱', '/dashboard', [menu(2, '生产数据总览', '/dashboard/overview')])]))
            .toBe('/dashboard/overview');
    });

    it('returns null when the user has no menus', () => {
        expect(firstMenuPath([])).toBeNull();
    });
});

