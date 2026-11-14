import { useQuery } from '@tanstack/react-query';
import { useEffect } from 'react';
import { fetchMe } from '@/api/auth';
import { useAuthStore } from '@/stores/auth';

/**
 * Load `/me` and mirror it into the auth store.
 *
 * The store is persisted, so a reload renders the last known menus at once and
 * this request refreshes them. Both the layout and the route guard read it, and
 * react-query dedupes the shared `['me']` key into one request.
 */
export function useCurrentUser() {
    const token = useAuthStore((state) => state.token);
    const setUser = useAuthStore((state) => state.setUser);

    const query = useQuery({
        queryKey: ['me'],
        queryFn: fetchMe,
        enabled: Boolean(token),
        staleTime: 30_000,
    });

    useEffect(() => {
        if (query.data) {
            setUser(query.data);
        }
    }, [query.data, setUser]);

    return query;
}
