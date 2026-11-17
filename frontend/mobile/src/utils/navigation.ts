import { LOGIN_PAGE } from '@/constants/routes';

function currentRoute(): string {
    const pages = typeof getCurrentPages === 'function' ? getCurrentPages() : [];

    return pages.length > 0 ? `/${pages[pages.length - 1].route ?? ''}` : '';
}

export function redirectToLogin(): void {
    if (currentRoute() === LOGIN_PAGE) {
        return;
    }

    uni.reLaunch({ url: LOGIN_PAGE });
}
