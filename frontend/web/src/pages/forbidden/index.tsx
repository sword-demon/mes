import { Button, Result } from 'antd';
import { useNavigate } from 'react-router';
import { useAuthStore } from '@/stores/auth';
import { firstMenuPath } from '@/utils/permission';

export default function Forbidden() {
    const navigate = useNavigate();
    const user = useAuthStore((state) => state.user);
    const home = firstMenuPath(user?.menus ?? []);

    return (
        <Result
            status="403"
            title="403"
            subTitle="抱歉，你没有访问该页面的权限"
            extra={
                home && (
                    <Button type="primary" onClick={() => navigate(home, { replace: true })}>
                        返回首页
                    </Button>
                )
            }
        />
    );
}
