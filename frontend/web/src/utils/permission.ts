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
 * Whether `path` is `menuPath` itself or sits beneath it as a deeper segment,
 * so a detail route like `/system/users/12` inherits its list page's grant.
 *
 * The `/` boundary keeps `/system` from matching `/systematic`.
 */
const isUnder = (menuPath: string, path: string): boolean =>
    path === menuPath || path.startsWith(`${menuPath}/`);

/**
 * The ancestor chain of the deepest menu whose path covers `path`, root first.
 * Empty when no granted menu covers the path.
 */
export function menuTrail(menus: MenuNode[], path: string): MenuNode[] {
    let best: MenuNode[] = [];
    let bestLength = -1;

    const walk = (nodes: MenuNode[], trail: MenuNode[]): void => {
        for (const node of nodes) {
            const next = [...trail, node];

            // The deepest covering menu wins, so a detail route inherits the
            // most specific list page rather than an outer group.
            if (node.path && node.path.length > bestLength && isUnder(node.path, path)) {
                best = next;
                bestLength = node.path.length;
            }

            walk(node.children, next);
        }
    };

    walk(menus, []);

    return best;
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
