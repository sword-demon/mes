import type { ReactNode } from 'react';
import { useAuthStore } from '@/stores/auth';
import { can } from '@/utils/permission';

/**
 * Renders its children only when the current user holds `code`.
 *
 * Hiding a button is a usability aid, never the security boundary: the matching
 * API route enforces the same permission server-side.
 */
export default function Can({ code, children }: { code: string; children: ReactNode }) {
    const user = useAuthStore((state) => state.user);

    return can(user, code) ? children : null;
}
