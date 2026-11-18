# 15 工序流转

**目标**：按工艺路线驱动工序执行，强制顺序、检验卡口、流转接收以及返工/异常原因。

## 1. 工序状态

```text
pending           待加工
processing        加工中
inspecting        待检验
inspected         检验通过
pending_transfer  待流转
transferred       已流转
reworking         返工中
```

## 2. 任务生成工序记录

任务拆分后，`ProcessRecordService::createForTask()` 按路线逐道生成记录，初始都是 `pending`。所以任务一创建，完整工序链就可见。

## 3. 开始加工

`start()` 会：

- 锁定任务和工序记录；
- 校验任务未完成；
- 如果当前工序是 `pending`，检查上道工序是否已 `transferred`；
- 指定操作人员（默认任务负责人）；
- 校验设备属于任务/订单产线；
- 绑定或校验 SN；
- 状态变为 `processing`。

第一道工序没有上道工序时可开始；后续工序必须等上道流转完成。

## 4. 完成与检验

```php
complete()：processing → inspecting 或 pending_transfer
```

- 工序 `requires_inspection = true`：完成加工后进入 `inspecting`；
- 否则直接进入 `pending_transfer`。

需检验工序必须通过 `inspect()` 才能变成 `inspected`；不合格进入 `reworking`，并记录返工原因和次数。

## 5. 提交与接收

```text
POST /process-records/{record}/submit
POST /process-records/{record}/receive
```

- `submit` 把 `inspected` 变成 `pending_transfer`；
- `receive` 把 `pending_transfer` 变成 `transferred`。

下道工序只有在上一道 `transferred` 后才能开始。

## 6. 异常和返工

```text
POST /process-records/{record}/exception
POST /process-records/{record}/rework
```

异常和返工都要求填写原因，并写入异常时间/返工时间。返工会增加 `rework_count`，后续需要重新加工和检验。

## 7. SN 校验

如果工序绑定 SN：

- SN 必须存在；
- SN 不能被作废；
- SN 必须属于当前生产订单；
- 已绑定的 SN 不能换成另一个。

## 8. 前端和测试

前端页面：`frontend/web/src/pages/execution/flows/index.tsx`。

```bash
php artisan test --compact tests/Feature/Api/ProcessRecordTest.php
php artisan test --compact tests/Feature/ProductionSeederTest.php
```

验收：

- 上道未流转不能开始下道；
- 需检验工序未通过不能提交；
- 检验不合格必须填原因；
- 异常/返工必须填原因；
- SN 作废或不属于订单时不能继续流转；
- Seeder 覆盖七种工序状态。

## 练习

选择一条有 3 道工序的任务，依次执行：

```text
开始第 1 道 → 完成 → 提交 → 接收
开始第 2 道 → 完成 → 检验 → 提交 → 接收
开始第 3 道
```

然后尝试跳过第 2 道直接开始第 3 道，观察错误。
