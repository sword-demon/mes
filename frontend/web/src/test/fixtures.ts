import type { CurrentUser, User } from '@/types/api';

/**
 * A signed-in user with no menus, so a test only spells out what it asserts on.
 */
export function currentUserFixture(overrides: Partial<CurrentUser> = {}): CurrentUser {
    return {
        id: 1,
        username: 'tester',
        name: '测试员',
        email: 'tester@mes.test',
        phone: null,
        department_id: null,
        position: null,
        status: true,
        email_verified_at: null,
        created_at: '2026-11-15T00:00:00Z',
        updated_at: '2026-11-15T00:00:00Z',
        permissions: [],
        data_scope: 'none',
        menus: [],
        ...overrides,
    };
}

/**
 * A row in a user list, with no roles attached.
 */
export function userFixture(overrides: Partial<User> = {}): User {
    return {
        id: 2,
        username: 'operator',
        name: '操作工',
        email: 'operator@mes.test',
        phone: null,
        department_id: null,
        position: null,
        status: true,
        email_verified_at: null,
        created_at: '2026-11-15T00:00:00Z',
        updated_at: '2026-11-15T00:00:00Z',
        ...overrides,
    };
}
