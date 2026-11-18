# 05 动态菜单与按钮权限

**目标**：让侧边菜单、路由和操作按钮都由当前用户的权限决定，同时保留后端鉴权作为真正的安全边界。

## 你将学到

- `/me` 返回的菜单树如何变成 Ant Design 侧边栏；
- `can()` 和 `<Can>` 如何控制按钮；
- 为什么“前端隐藏”不能代替后端 403；
- 切换账号或刷新页面时，菜单和 Token 如何保持一致。

## 关键文件

```text
frontend/web/src/hooks/use-current-user.ts
frontend/web/src/layouts/admin-layout.tsx
frontend/web/src/router/index.tsx
frontend/web/src/utils/permission.ts
frontend/web/src/components/can.tsx
frontend/web/src/stores/auth.ts
```

## 1. 菜单来自 `/me`，不是前端写死

登录后，`AdminLayout` 通过 React Query 请求 `/me`：

```ts
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
```

`/me` 返回的 `menus` 是后端已经过滤过的树。`AdminLayout` 用 `menuItems()` 把它转换成 Ant Design 的 `Menu` items，路径作为 `key`，点击后调用 `navigate(key)`。

菜单数据流：

```text
MySQL sys_menus
  → AccessControlService::menuTree()
  → GET /api/v1/me
  → Zustand user.menus
  → AdminLayout Menu
```

## 2. 路由守卫检查菜单

`RequireAuth` 只检查 Token：

```tsx
return token ? children : <Navigate to="/login" replace />;
```

`MenuPage` 再检查当前路径是否在用户菜单里：

```tsx
const menu = findMenu(user.menus, pathname);

if (!menu) {
    return <Navigate to="/403" replace />;
}
```

这样即使用户手动输入 `/system/users`，没有菜单授权也会进入 403。

`menuTrail()` 按路径段匹配，所以 `/system/users/12` 会继承 `/system/users` 的权限，但 `/systematic` 不会误匹配 `/system`。

## 3. 按钮权限

`utils/permission.ts`：

```ts
export function can(user: CurrentUser | null, code: string): boolean {
    return user?.permissions?.includes(code) ?? false;
}
```

`components/can.tsx`：

```tsx
export default function Can({ code, children }: { code: string; children: ReactNode }) {
    const user = useAuthStore((state) => state.user);

    return can(user, code) ? children : null;
}
```

页面中这样使用：

```tsx
<Can code="button.system.users.create">
    <Button type="primary">新增用户</Button>
</Can>
```

注意编码前缀：前端按钮通常使用 `button.*`，后端路由使用不带 `button.` 的 API 权限。

## 4. 切换账号和刷新

Zustand 只持久化 Token：

```ts
partialize: (state) => ({ token: state.token })
```

刷新页面时：

1. 从 localStorage 恢复 Token；
2. `useCurrentUser()` 重新请求 `/me`；
3. 菜单和权限以新响应为准。

登录成功时，登录页会清掉旧的 `['me']` 缓存，避免新账号短暂看到旧账号的权限：

```ts
queryClient.removeQueries({ queryKey: ['me'] });
signIn(result.token, null);
```

退出时则清理查询缓存、Zustand 和 Token。

## 5. 常见坑

| 现象 | 原因 |
| --- | --- |
| 刷新后菜单短暂为空 | `/me` 尚未返回，属于正常加载态 |
| 菜单消失但 Token 还在 | 用户角色或菜单权限被修改，重新请求 `/me` |
| 按钮显示了但接口 403 | 只有 `button.*`，没有对应 API 权限 |
| 手动输入 URL 进入空白 | 路径不在 `/me` 菜单树，应跳 403 |
| 切换账号后看到旧菜单 | 没有清理 `['me']` 缓存 |

## 6. 练习

1. 创建一个只有 `button.system.users.index` 的用户，检查“新增用户”按钮是否隐藏；
2. 手动请求 `POST /api/v1/system/users`，确认后端仍然返回 403；
3. 修改角色菜单后刷新页面，观察侧边栏变化；
4. 给一个详情路径 `/system/users/12` 加菜单，验证它能继承列表页权限。

## 验收标准

- 侧边栏完全由 `/me` 菜单树驱动；
- 无权限路径跳 `/403`；
- 无权限按钮不渲染；
- 切换账号后菜单和按钮立即刷新；
- 刷新页面仍保持登录态。
