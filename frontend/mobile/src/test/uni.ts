import { vi } from 'vitest';

export type UniMock = {
    storage: Map<string, unknown>;
    request: ReturnType<typeof vi.fn>;
    reLaunch: ReturnType<typeof vi.fn>;
    navigateTo: ReturnType<typeof vi.fn>;
    showToast: ReturnType<typeof vi.fn>;
};

export function installUniMock(): UniMock {
    const storage = new Map<string, unknown>();
    const request = vi.fn();
    const reLaunch = vi.fn();
    const navigateTo = vi.fn();
    const showToast = vi.fn();

    Object.defineProperty(globalThis, 'getCurrentPages', {
        configurable: true,
        value: () => [],
    });
    Object.defineProperty(globalThis, 'uni', {
        configurable: true,
        value: {
            getStorageSync: (key: string) => storage.get(key) ?? '',
            setStorageSync: (key: string, value: unknown) => storage.set(key, value),
            removeStorageSync: (key: string) => storage.delete(key),
            request,
            reLaunch,
            navigateTo,
            showToast,
        },
    });

    return { storage, request, reLaunch, navigateTo, showToast };
}
