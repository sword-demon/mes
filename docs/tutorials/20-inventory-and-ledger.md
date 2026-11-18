# 20 库存出入库与流水

**目标**：查询和变更库存，保证每次变化都有流水，出库不超过可用库存，账实一致。

## 1. 库存维度

库存行由三件套唯一确定：

```text
material_id + location_id + batch_no
```

字段：

```text
current_quantity  当前数量
frozen_quantity   冻结数量
```

可用量：

```text
available = current_quantity - frozen_quantity
```

批次管理物料必须有批次号；非批次物料允许空批次。

## 2. 三种业务动作

```text
inbound    入库：current + quantity
outbound   出库：current - quantity，且 quantity ≤ available
adjustment 调整：直接把 current 设为目标值，不能低于冻结量
```

每次动作都在事务中：

1. 锁定物料；
2. 校验库位属于仓库且启用；
3. 找到/创建库存行；
4. 锁定库存行；
5. 计算前后数量；
6. 更新库存；
7. 写 `InventoryTransaction`；
8. 写操作日志。

## 3. 库存流水

`InventoryTransaction` 记录：

```text
inventory_id / material_id
business_type
quantity（本次变动，可正可负）
before_quantity / after_quantity
reference_no / remark / created_by_id
```

流水不可变，是库存变化的审计证据。

## 4. Seeder

`InventorySeeder` 生成：

- 4 个仓库；
- 10 个库位；
- 30+ 条批次库存；
- 期初入库流水。

重复执行时，如果库存数量变化，会写一条“Seeder 账实校准”的调整流水，而不是直接改库存不留痕。

## 5. 前端

`frontend/web/src/pages/material/inventory/index.tsx` 提供库存查询和流水查看；入库、出库、调整有独立页面/弹窗。

## 6. 测试

```bash
php artisan test --compact tests/Feature/Api/InventoryTest.php
php artisan test --compact tests/Feature/MaterialSeederTest.php
```

验收：

- 出库超过可用库存拒绝；
- 调整不能低于冻结数量；
- 批次管理物料缺批次拒绝；
- 每次变更都有流水；
- 流水净额等于库存当前值；
- Seeder 重复执行不会重复创建流水/库存。

## 练习

对同一物料连续执行：

```text
入库 100 → 出库 30 → 调整到 50
```

查看三条流水的 `before/after`，并计算净额是否等于最终库存。
