import type { CurrentUser, MenuNode } from '@/types/api';

/**
 * Whether the current user holds a permission code.
 *
 * A missing user grants nothing: the menus and buttons must not flash before
 * `/me` resolves.
 */
export function can(user: CurrentUser | null, code: string): boolean {
    return user?.permissions?.includes(code) ?? false;
}

/**
 * The ancestor chain of `path`, root first. Empty when the path is not a menu.
 */
export function menuTrail(menus: MenuNode[], path: string): MenuNode[] {
    for (const node of menus) {
        if (node.path === path) {
            return [node];
        }

        const trail = menuTrail(node.children, path);

        if (trail.length > 0) {
            return [node, ...trail];
        }
    }

    return [];
}

export const findMenu = (menus: MenuNode[], path: string): MenuNode | null =>
    menuTrail(menus, path).at(-1) ?? null;

/**
 * The first page path in menu order, used as the post-login landing page.
 *
 * Children win over a group's own path, so a group that carries a path but
 * only exists to hold sub-pages still lands the user on a real page.
 */
export function firstMenuPath(menus: MenuNode[]): string | null {
    for (const node of menus) {
        const path = firstMenuPath(node.children) ?? node.path;

        if (path) {
            return path;
        }
    }

    return null;
}
