# 04: RBAC 权限模型与用户角色菜单管理

**What to build:** 系统管理员可以管理用户、角色、权限与菜单，并让权限真正生效：不同角色登录后看到不同菜单，越权请求被后端拒绝。

**Blocked by:** 02

**Status:** done

- [x] sys_users / sys_roles / sys_permissions / sys_user_roles / sys_role_permissions / sys_menus 迁移与模型就绪
- [x] 用户新增/编辑/停用/重置密码/角色分配、角色新增/编辑/启停/删除、权限列表查询均通过 API 可用
- [x] 权限类型覆盖菜单、页面、按钮、API 接口、数据五类，角色可勾选权限
- [x] GET /me 返回当前用户的角色、权限与菜单树
- [x] 每个受保护接口独立校验权限，越权访问返回 403，停用用户无法登录
