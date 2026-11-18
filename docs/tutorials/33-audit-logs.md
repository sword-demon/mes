# 33 操作日志与登录日志

**目标**：记录关键写操作和登录行为，支持按用户、动作、时间和结果查询。

## 1. 两张表

```text
sys_operation_logs
sys_login_logs
```

操作日志字段：

```text
user_id / user_name（快照）
action / action_name
object_type / object_id / object_name
summary / changes
ip / created_at
```

登录日志字段：

```text
user_id / account
ip / success / message
created_at
```

## 2. 登录日志

`AccessTokenService` 在三种失败场景分别记录：

```text
账号不存在
账号已停用
密码错误
```

成功登录记录“登录成功”。这样既能排查撞库，也不会通过响应泄露账号是否存在。

`LoginLogService` 在写入前截断超长账号，避免数据库列溢出。

## 3. 操作日志

业务 Service 通过 `OperationLogEntry` 描述一次操作：

```php
$this->operationLogs->record(new OperationLogEntry(
    action: 'production.order.transition',
    actionName: '订单状态流转',
    objectType: '生产订单',
    objectId: $order->id,
    objectName: $order->code,
    before: ['状态' => $before],
    after: ['状态' => $after],
));
```

`OperationLogService` 只保留真正变化的字段，自动生成摘要：

```text
操作名称：对象 名称（字段：旧值 → 新值）
```

记录人来自当前请求用户；没有用户时记为“系统”。

## 4. 哪些操作会写日志

- 生产订单状态流转；
- 生产报工和冲销；
- 库存入库/出库/调整；
- 用户创建/编辑/重置密码/分配角色；
- 角色创建/编辑/删除；
- 其他显式调用 `OperationLogService` 的写操作。

日志与业务写操作在同一个事务里，日志失败会回滚业务，避免“操作成功但没有审计”。

## 5. 查询接口

```text
GET /api/v1/system/operation-logs
GET /api/v1/system/operation-logs/options
GET /api/v1/system/login-logs
```

支持按用户、动作、日期、登录结果筛选。前端：

```text
frontend/web/src/pages/system/logs/index.tsx
```

## 6. 测试

```bash
php artisan test --compact tests/Feature/Api/AuditLogTest.php
```

验收：

- 登录成功/失败都写日志；
- 停用账号登录失败有原因；
- 订单流转、报工、库存、权限变更写操作日志；
- 只记录变化字段；
- 超长账号/对象名被截断；
- 日志失败时业务回滚；
- 无权限不能查询日志。

## 练习

修改一个角色权限，再查看操作日志：

1. `before` 和 `after` 是否只包含变化的权限；
2. 摘要是否可读；
3. 操作人和 IP 是否正确；
4. 删除角色后日志是否仍保留角色名称快照。
