# 18 检验任务与自动判定

**目标**：创建 IPQC/OQC 检验任务，录入实测值，由系统按标准区间自动判定合格/不合格。

## 1. 两种检验

```text
IPQC  工序检验，必须关联待检验工序记录
OQC   完工检验，不关联工序记录，但必须关联订单路线中的工序
```

检验结果：

```text
pending → passed / failed
```

任务还可能因工序轮次变化而 `expired`。

## 2. 创建任务

`InspectionTaskService::create()` 的关键校验：

- 订单存在；
- IPQC 必须有 `process_record_id`，且工序状态必须是 `inspecting`；
- 同一工序同一轮次不能有未判定任务；
- OQC 不能关联工序记录；
- OQC 必须指定订单路线中的工序；
- 至少有一个启用检验项目；
- 每个项目必须有标准。

创建时会把当前启用的检验项目复制到 `requiredItems`，形成任务快照。

## 3. 自动判定

`record()` 会：

1. 锁定任务；
2. 校验任务未判定；
3. 校验 IPQC 的工序轮次没有过期；
4. 校验所有必检项目都有实测值；
5. 用 `bccomp` 比较实测值与上下限；
6. 保存每项结果；
7. 汇总合格/不合格；
8. 如果是 IPQC，推动工序记录通过或返工。

核心比较：

```php
$passed = bccomp($measured, $minimum, 3) >= 0
    && bccomp($measured, $maximum, 3) <= 0;
```

不合格时把失败项拼成原因，交给 `ProcessRecordService::inspect()` 进入返工。

## 4. 图片

```text
POST /api/v1/quality/inspection-tasks/{task}/images
```

图片存 `storage/app/public/inspection-images/{taskId}`，数据库记录路径和原文件名。上传失败会回滚已保存文件。

## 5. 查询

检验任务和检验记录共用：

```text
GET /api/v1/quality/inspection-tasks
```

支持按订单、工序、判定结果、时间筛选。前端：

```text
frontend/web/src/pages/quality/tasks/index.tsx
frontend/web/src/pages/quality/records/index.tsx
```

## 6. 测试

```bash
php artisan test --compact tests/Feature/Api/InspectionTaskTest.php
php artisan test --compact tests/Feature/QualitySeederTest.php
```

重点：

- IPQC 必须关联待检验工序；
- 重复待判定任务拒绝；
- 缺项或越界判不合格；
- 合格后工序进入 `inspected`；
- 不合格后工序进入 `reworking`；
- 过期轮次不能提交；
- 图片能挂到任务上；
- Seeder 至少 50 条检验任务，覆盖合格/不合格。

## 练习

创建一个 IPQC 任务，录入：

```text
VOLTAGE = 5.1
TEMPERATURE = 61
LENGTH = 100
```

观察温度越界后任务结果和工序状态的变化。
