# 12 生产任务拆分

**目标**：把已下达订单拆成可执行任务，分配产线、班组、负责人，并让任务进度随报工自动更新。

## 1. 任务状态

```text
pending      待分配
in_progress  生产中
paused       已暂停
completed    已完成
```

合法转移：

```text
待分配 → 生产中
生产中 → 已暂停 / 已完成
已暂停 → 生产中 / 已完成
已完成 → 终止
```

## 2. 拆分规则

`ProductionTaskService::split()` 先锁定订单：

```php
$locked = ProductionOrder::query()
    ->lockForUpdate()
    ->whereKey($order->getKey())
    ->firstOrFail();
```

只有 `released` 订单可以拆任务。然后计算：

```text
已拆任务计划量之和 + 本次计划量 ≤ 订单计划量
```

超出时返回 422，防止任务总量超过订单。

任务编码为：

```text
PO-DEMO-001-T01
PO-DEMO-001-T02
```

创建任务后立即调用：

```php
$this->processRecords->createForTask($task);
```

按任务继承的工艺路线生成全部工序记录，初始状态为 `pending`。

## 3. 分配任务

`assign()` 校验：

- 产线存在且启用；
- 班组存在且启用；
- 负责人存在且启用；
- 负责人属于所选班组。

待分配任务第一次分配后变为 `in_progress`；已经运行或暂停的任务重新分配时保留原状态。

## 4. 暂停、恢复、完成

```text
POST /api/v1/production/tasks/{task}/pause
POST /api/v1/production/tasks/{task}/resume
POST /api/v1/production/tasks/{task}/complete
```

暂停 / 恢复 / 完成都走 `transitionTo()`，非法转移返回明确错误。

## 5. 进度是派生值

任务不单独保存“进度百分比”：

```php
public function progressPercent(): float
{
    $planned = (float) $this->planned_quantity;

    return $planned <= 0
        ? 0.0
        : min(100.0, round((float) $this->produced_quantity / $planned * 100, 1));
}
```

报工写入后更新 `produced_quantity`，进度自然变化，不会出现两个字段不一致。

## 6. Seeder 和前端

`ProductionSeeder` 为 20 张订单生成 30 个任务，覆盖四种任务状态，并生成派工记录、工序记录和报工。

前端 `pages/production/tasks/index.tsx` 提供任务列表、拆分、分配、暂停、恢复、完成。

## 7. 测试

```bash
php artisan test --compact tests/Feature/Api/ProductionTaskTest.php
php artisan test --compact tests/Feature/ProductionSeederTest.php
```

验收：

- 只有已下达订单能拆任务；
- 任务计划量总和不超订单计划量；
- 任务生成后自动出现完整工序记录；
- 负责人必须属于所选班组；
- 任务暂停/恢复/完成状态合法；
- 报工后任务进度自动变化。

## 练习

对一张 `released` 订单拆两个任务，计划量分别为 4 和 6，然后：

1. 再拆一个计划量 1 的任务；
2. 把负责人分配到错误班组；
3. 暂停待分配任务；
4. 对任务报工后查看进度。
