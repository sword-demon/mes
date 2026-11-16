<template>
    <view class="report">
        <view class="report__hero">
            <text class="report__eyebrow">生产报工</text>
            <text class="report__title">扫码报工入口</text>
            <text class="report__desc">
                本期仅提供页面与路由骨架，工单选择、扫码报工将在 V1.1 接入。
            </text>
        </view>

        <up-cell-group border>
            <up-cell title="登录账号" :value="displayName" />
            <up-cell title="后端契约" value="POST /api/v1/auth/tokens" />
            <up-cell title="本期状态" value="骨架占位" />
        </up-cell-group>

        <view class="report__actions">
            <up-button
                type="primary"
                text="前往 SN 扫码占位"
                :custom-style="{ width: '100%' }"
                @click="goToScan"
            />
            <up-button
                text="退出登录"
                :custom-style="{ width: '100%' }"
                @click="handleLogout"
            />
        </view>
    </view>
</template>

<script setup lang="ts">
import { onShow } from '@dcloudio/uni-app';
import { computed } from 'vue';
import { LOGIN_PAGE } from '@/constants/routes';
import { goToScan, requireAuth, session, signOut } from '@/stores/auth';

onShow(() => {
    requireAuth();
});

const displayName = computed(() => session.user?.name ?? '已登录用户');

async function handleLogout(): Promise<void> {
    await signOut();
    uni.reLaunch({ url: LOGIN_PAGE });
}
</script>

<style lang="scss" scoped>
.report {
    min-height: 100vh;
    padding: 32rpx 32rpx calc(48rpx + env(safe-area-inset-bottom));
    box-sizing: border-box;

    &__hero {
        display: flex;
        flex-direction: column;
        margin-bottom: 32rpx;
        padding: 40rpx 36rpx;
        border-radius: 28rpx;
        background: #ffffff;
        box-shadow: 0 12rpx 32rpx rgba(15, 23, 42, 0.06);
    }

    &__eyebrow {
        color: #2563eb;
        font-size: 24rpx;
        font-weight: 600;
        letter-spacing: 2rpx;
    }

    &__title {
        margin-top: 16rpx;
        color: #111827;
        font-size: 44rpx;
        font-weight: 700;
    }

    &__desc {
        margin-top: 16rpx;
        color: #6b7280;
        font-size: 26rpx;
        line-height: 1.7;
    }

    &__actions {
        display: flex;
        flex-direction: column;
        gap: 24rpx;
        margin-top: 48rpx;
    }
}
</style>
