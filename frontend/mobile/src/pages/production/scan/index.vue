<template>
    <view>
        <view v-if="ready" class="scan">
            <up-empty
                mode="data"
                text="SN 扫码功能将在 V1.1 接入"
                text-color="#6b7280"
                icon-color="#9ca3af"
                margin-top="80"
            />

            <view class="scan__card">
                <text class="scan__title">接入点已预留</text>
                <text class="scan__desc">
                    后续扫码报工与扫码检验将复用 /api/v1 契约，本期不调用扫码设备，也不新增后端接口。
                </text>
            </view>

            <view class="scan__actions">
                <up-button
                    type="primary"
                    text="返回报工占位页"
                    :custom-style="{ width: '100%' }"
                    @click="backToReport"
                />
            </view>
        </view>
        <view v-else class="page-pending">
            <text>正在恢复登录态…</text>
        </view>
    </view>
</template>

<script setup lang="ts">
import { useAuthGuard } from '@/composables/use-auth-guard';
import { REPORT_PAGE } from '@/constants/routes';

const ready = useAuthGuard();

function backToReport(): void {
    uni.reLaunch({ url: REPORT_PAGE });
}
</script>

<style lang="scss" scoped>
.scan {
    min-height: 100vh;
    padding: 32rpx 32rpx calc(48rpx + env(safe-area-inset-bottom));
    box-sizing: border-box;

    &__card {
        display: flex;
        flex-direction: column;
        padding: 36rpx;
        border-radius: 24rpx;
        background: #ffffff;
        box-shadow: 0 12rpx 32rpx rgba(15, 23, 42, 0.06);
    }

    &__title {
        color: #111827;
        font-size: 32rpx;
        font-weight: 600;
    }

    &__desc {
        margin-top: 16rpx;
        color: #6b7280;
        font-size: 26rpx;
        line-height: 1.7;
    }

    &__actions {
        margin-top: 40rpx;
    }
}
</style>
