# 23 退料

**目标**：把未使用物料退回仓库，限制退量不超过实领量，并在审核后回补库存。

## 1. 可退数量

退料建议来自原领料单：

```text
可退量 = 已发料量 - 已审核退料量 - 待审核退料量
```

只有可退量大于 0 的明细才出现在建议中。

## 2. 创建退料单

```text
POST /api/v1/materials/returns
```

创建时：

- 锁定原领料单；
- 锁定领料明细；
- 计算已审核/待审核退料量；
- 每条退料数量不能超过可退量；
- 状态为 `pending`；
- 不改变库存。

待审核退料也会占用可退额度，避免同时创建多张退料单超额。

## 3. 审核退料

```text
POST /api/v1/materials/returns/{return}/approve
```

审核时必须为每条明细指定退回库位/批次，然后：

- 再次校验可退量；
- 调用库存入库；
- 流水 `reference_no` 写退料单号；
- 状态改为 `approved`；
- 记录审核人和审核时间。

未审核退料不改变库存。

## 4. 撤销

```text
DELETE /api/v1/materials/returns/{return}
```

只有创建人能撤销自己的待审核退料；已审核不能撤销。

## 5. 状态

```text
pending → approved / cancelled
```

## 6. 前端和测试

`frontend/web/src/pages/material/returns/index.tsx` 支持选择领料单、带出可退量、创建退料、审核、撤销。

```bash
php artisan test --compact tests/Feature/Api/MaterialReturnTest.php
```

验收：

- 退量超过实领量拒绝；
- 多张待审核退料不能合计超额；
- 未审核不改库存；
- 审核后库存增加并生成入库流水；
- 只有创建人能撤销待审核退料；
- 已审核不能撤销。

## 练习

对一张领料单创建退料单，先不审核，再创建第二张退料单，确认两张合计不超过实领量。审核第一张后，检查库存和流水。
