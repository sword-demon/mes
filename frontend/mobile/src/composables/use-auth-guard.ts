import { onShow } from '@dcloudio/uni-app';
import { ref } from 'vue';
import { requireAuth } from '@/stores/auth';

export function useAuthGuard() {
    const ready = ref(false);

    onShow(async () => {
        ready.value = false;
        ready.value = await requireAuth();
    });

    return ready;
}
