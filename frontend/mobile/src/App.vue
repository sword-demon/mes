<script setup lang="ts">
import { onLaunch } from '@dcloudio/uni-app';
import { restoreSession } from '@/stores/auth';

onLaunch(() => {
    restoreSession();
});
</script>

<style lang="scss">
@import 'uview-plus/index.scss';

page {
    background-color: #f5f6f8;
    color: #1f2937;
    font-family: -apple-system, BlinkMacSystemFont, 'Helvetica Neue', Arial, sans-serif;
}

/* 骨架页面固定浅色卡片，锁定 uView 的浅色变量，避免系统深色模式让文字变白。 */
page,
body {
    --up-main-color: #303133;
    --up-content-color: #606266;
    --up-tips-color: #909193;
    --up-light-color: #c0c4cc;
    --up-border-color: #dadbde;
    --up-bg-color: #f3f4f6;
    --up-disabled-color: #c8c9cc;
    --up-page-bg-color: #f3f4f6;
    --up-card-bg-color: #ffffff;
    --up-navbar-bg-color: #ffffff;
    --up-hover-bg-color: #e7ebf0;
}
</style>
