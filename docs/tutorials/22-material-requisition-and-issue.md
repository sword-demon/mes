# 22 领料与发料

**目标**：生产人员按订单领料，仓库按实际库位/批次发料，系统自动扣减库存并记录流水。

## 1. 领料建议

`MaterialRequisitionService::suggestions()` 按订单 BOM 计算：

```text
需求量 = 订单计划量 × BOM 用量 × (1 + 损耗率 / 100)
建议领用量 = 需求量 - 已发料量
```

返回每个物料的：

```text
required_quantity
issued_quantity
suggested_quantity
```

## 2. 创建领料单

```text
POST /api/v1/materials/requisitions
```

只有以下订单状态可以领料：

```text
released / in_progress / paused
```

创建时校验物料启用，明细保存申请数量，状态默认 `pending`。

## 3. 发料

```text
POST /api/v1/materials/requisitions/{requisition}/issue
```

发料单锁定后：

- 校验领料单仍为 `pending`；
- 每条明细发料量不能超过申请量；
- 调用 `InventoryService::outbound()` 扣减库存；
- 流水的 `reference_no` 写领料单号；
- 更新明细已发数量；
- 全部发完变 `issued`，否则保持 `pending`；
- 超过 BOM 需求量时标记 `over_requirement`，作为告警而不是直接阻断。

## 4. 为什么发料要选库位和批次

库存按“物料 + 库位 + 批次”建账。同一物料可能分布在多个批次，发料必须指定实际取货的库位/批次，流水才能对应真实库存。

## 5. 前端和测试

`frontend/web/src/pages/material/requisitions/index.tsx` 支持创建领料单、查看建议量、发料。

```bash
php artisan test --compact tests/Feature/Api/MaterialRequisitionTest.php
```

重点：

- 只有可领料订单状态能创建；
- 建议量按 BOM 和损耗率计算；
- 发料超过申请量拒绝；
- 发料超过可用库存由库存服务拒绝；
- 发料生成出库流水；
- 部分发料保持待发状态；
- 超 BOM 需求量标记 `over_requirement`。

## 练习

为一张已下达订单查看领料建议，创建领料单并只发一部分料。检查：

1. 领料单状态；
2. 已发数量；
3. 库存流水；
4. BOM 需求量与实际发料量。
