# 21 库存预警

**目标**：当物料总库存低于最低库存时自动生成预警，并支持确认、关闭和定时扫描。

## 1. 预警状态

```text
pending    待确认
confirmed  已确认
closed     已关闭
```

预警按物料聚合，不按单个仓库或批次聚合。判断口径：

```text
该物料所有库存行 current_quantity 之和 < material.min_stock
```

`min_stock = 0` 的物料不产生预警。

## 2. 定时扫描

`InventoryAlertService::scan()` 会：

1. 分批遍历物料；
2. 锁定物料行；
3. 汇总当前库存；
4. 低于最低库存时创建预警；
5. 已存在活跃预警时只更新数量；
6. 补货后自动关闭预警；
7. 物料停用时关闭未解决预警。

`routes/console.php` 注册：

```php
Schedule::command(ScanInventoryAlerts::class)
    ->everyFiveMinutes()
    ->withoutOverlapping(10);
```

## 3. 确认和关闭

```text
POST /api/v1/inventory/alerts/{alert}/confirm
POST /api/v1/inventory/alerts/{alert}/close
```

- 只有 `pending` 可以确认；
- `pending` 或 `confirmed` 可以关闭；
- 记录处理人、处理时间和 `resolved_at`。

## 4. 前端

`frontend/web/src/pages/material/inventory-alerts/index.tsx` 展示当前活跃预警，支持按物料查询、确认、关闭。

## 5. 测试

```bash
php artisan test --compact tests/Feature/InventoryAlertScanTest.php
php artisan test --compact tests/Feature/InventoryAlertTest.php
```

验收：

- 低于最低库存生成预警；
- 等于或高于最低库存不生成；
- 重复扫描不重复生成；
- 补货后关闭；
- 物料停用后关闭；
- 无权限不能确认/关闭。

## 练习

1. 把某物料库存调到低于 `min_stock`；
2. 执行 `php artisan inventory:scan-alerts`；
3. 再执行一次，确认没有重复预警；
4. 入库补足库存，再执行扫描，观察预警关闭。
