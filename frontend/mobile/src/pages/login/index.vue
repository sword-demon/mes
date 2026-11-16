<template>
    <view class="login">
        <view class="login__brand">
            <view class="login__logo">MES</view>
            <text class="login__title">移动端工作台</text>
            <text class="login__subtitle">扫码报工与扫码检验入口</text>
        </view>

        <view class="login__card">
            <up-input v-model="form.email" placeholder="邮箱" border="surround" clearable />
            <up-input
                v-model="form.password"
                :password="true"
                placeholder="密码"
                border="surround"
                clearable
            />
            <up-button
                type="primary"
                text="登录"
                :loading="submitting"
                :custom-style="{ width: '100%' }"
                @click="submit"
            />
        </view>

        <text class="login__note">
            当前为移动端骨架，仅登录可用；生产报工与 SN 扫码业务将在 V1.1 接入。
        </text>
    </view>
</template>

<script setup lang="ts">
import { onLoad } from '@dcloudio/uni-app';
import { reactive, ref } from 'vue';
import { ApiRequestError } from '@/api/request';
import { REPORT_PAGE } from '@/constants/routes';
import { session, signIn } from '@/stores/auth';

const form = reactive({
    email: '',
    password: '',
});
const submitting = ref(false);

onLoad(() => {
    if (session.token) {
        uni.reLaunch({ url: REPORT_PAGE });
    }
});

async function submit(): Promise<void> {
    if (!form.email.trim() || !form.password) {
        uni.showToast({ title: '请输入邮箱和密码', icon: 'none' });

        return;
    }

    submitting.value = true;

    try {
        await signIn({
            email: form.email.trim(),
            password: form.password,
        });
        uni.reLaunch({ url: REPORT_PAGE });
    } catch (error) {
        const message =
            error instanceof ApiRequestError
                ? (Object.values(error.errors ?? {}).flat()[0] ?? error.message)
                : '登录失败，请稍后重试';

        uni.showToast({ title: message, icon: 'none' });
    } finally {
        submitting.value = false;
    }
}
</script>

<style lang="scss" scoped>
.login {
    min-height: 100vh;
    padding: 72rpx 48rpx calc(48rpx + env(safe-area-inset-bottom));
    box-sizing: border-box;
    background: linear-gradient(180deg, #eef4ff 0%, #f5f6f8 46%, #f5f6f8 100%);

    &__brand {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding-top: 48rpx;
    }

    &__logo {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 136rpx;
        height: 136rpx;
        border-radius: 36rpx;
        background: #2563eb;
        color: #ffffff;
        font-size: 40rpx;
        font-weight: 700;
        letter-spacing: 2rpx;
        box-shadow: 0 18rpx 40rpx rgba(37, 99, 235, 0.25);
    }

    &__title {
        margin-top: 32rpx;
        color: #111827;
        font-size: 44rpx;
        font-weight: 700;
    }

    &__subtitle {
        margin-top: 12rpx;
        color: #6b7280;
        font-size: 26rpx;
    }

    &__card {
        display: flex;
        flex-direction: column;
        gap: 28rpx;
        margin-top: 64rpx;
        padding: 48rpx 40rpx;
        border-radius: 28rpx;
        background: #ffffff;
        box-shadow: 0 20rpx 48rpx rgba(15, 23, 42, 0.08);
    }

    &__note {
        display: block;
        margin-top: 40rpx;
        color: #9ca3af;
        font-size: 24rpx;
        line-height: 1.7;
        text-align: center;
    }
}
</style>
