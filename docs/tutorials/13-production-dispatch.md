# 13 生产派工

**目标**：把任务中的具体工序派给具体人员，保证“谁接了哪道工序”可查询、可追溯、不可重复。

## 1. 派工模型

`ProductionDispatch` 关键字段：

```text
code
production_task_id
route_operation_id
employee_id
team_id
dispatched_at
```

一条派工记录表示：

```text
任务 A 的第 2 道工序 → 员工 B（班组 C）
```

## 2. 创建派工

`ProductionDispatchService::create()` 在一个事务里：

1. 锁定生产任务；
2. 任务已完成时拒绝；
3. 校验工序属于任务的工艺路线；
4. 校验班组启用；
5. 校验员工启用且属于该班组；
6. 检查同一任务 + 同一工序 + 同一人员是否重复；
7. 生成派工编码并保存。

关键校验：

```php
if ($operation->route_id !== $task->route_id) {
    throw new ApiException('工序不属于该生产任务的工艺路线', 422);
}

if ($employee->team_id !== $teamId) {
    throw new ApiException('作业人员不属于所选班组', 422);
}
```

任务行锁和重复检查一起使用，避免两个并发请求同时通过检查。

## 3. 接口

```text
GET  /api/v1/production/dispatches
POST /api/v1/production/dispatches
GET  /api/v1/production/dispatches/mine
GET  /api/v1/production/tasks/{task}/operations
```

`mine` 按当前用户关联的员工档案返回“我的待办”，这是生产操作员视角。

列表支持按任务、订单、人员、日期等筛选；具体参数以控制器和 `EntityService` 配置为准。

## 4. Seeder

`ProductionSeeder` 给每个演示任务的前两道工序生成派工记录，并匹配演示员工和班组。这样“我的待办”页面打开就有数据。

## 5. 前端

`frontend/web/src/pages/production/dispatch/index.tsx` 展示派工记录，并支持：

- 选择任务；
- 选择路线工序；
- 选择班组和人员；
- 查看自己的待办。

## 6. 测试

```bash
php artisan test --compact tests/Feature/Api/ProductionDispatchTest.php
php artisan test --compact tests/Feature/ProductionSeederTest.php
```

验收：

- 已完成任务不能派工；
- 工序不属于任务路线时拒绝；
- 人员停用或班组不匹配时拒绝；
- 重复派工拒绝；
- `mine` 只返回当前员工的派工；
- Seeder 派工记录与任务、工序匹配。

## 练习

给一个任务的两道不同工序分别派给同一员工，再尝试把同一工序重复派给同一员工。观察：

1. 服务端返回什么错误；
2. 换一个员工是否允许；
3. `mine` 接口是否只返回当前用户的记录。
