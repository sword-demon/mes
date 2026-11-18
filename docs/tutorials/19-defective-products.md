# 19 不合格品处理

**目标**：登记不良品，完成返工、复检或报废，未复检不能直接关闭。

## 1. 状态机

```text
pending          待处理
processing       处理中
pending_recheck  待复检
closed           已关闭
```

两条关闭路径：

```text
待处理 → 处理中 → 待复检 → 复检合格 → 已关闭
待处理 → 处理中 → 报废 → 已关闭
```

## 2. 登记不良品

请求必须关联：

- 生产订单；
- 工序；
- 生产批次；
- 缺陷代码；
- 数量；
- 原因备注。

服务会校验：

- 工序属于该订单路线；
- 批次属于该订单；
- 缺陷代码启用；
- 数量大于 0；
- 不超过批次数量。

## 3. 处理动作

```text
POST /defective-products/{defective}/start
POST /defective-products/{defective}/rework
POST /defective-products/{defective}/scrap
POST /defective-products/{defective}/recheck
```

`rework` 必须填写原因，生成 `ReworkOrder`，状态进入 `pending_recheck`。

`recheck`：

- 合格 → 关闭；
- 不合格 → 回到 `processing`，允许继续返工。

`scrap` 必须填写原因和数量，直接关闭；报废数量不能超过不良数量。

## 4. 关键代码

```php
private function ensureStatus(DefectiveProduct $defect, string $expected, string $action): void
{
    if ($defect->status !== $expected) {
        throw new ApiException(
            "不良品当前状态为「{$defect->statusLabel()}」，不能{$action}",
            422,
        );
    }
}
```

所有动作都在事务中锁定不良品记录，避免重复处理。

## 5. 前端

`frontend/web/src/pages/quality/defects/index.tsx` 按状态展示：

- 待处理：开始处理；
- 处理中：返工、报废；
- 待复检：复检；
- 已关闭：只读。

## 6. 测试

```bash
php artisan test --compact tests/Feature/Api/DefectiveProductTest.php
php artisan test --compact tests/Feature/QualitySeederTest.php
```

验收：

- 不良数量不能超过批次数量；
- 未开始处理不能返工/报废；
- 返工必须填原因并生成返工单；
- 未复检不能关闭；
- 复检不合格回到处理中；
- 报废需原因和数量；
- 状态跳步返回 422。

## 练习

登记 2 件不良品，执行：

```text
开始处理 → 返工 → 复检不合格 → 再返工 → 复检合格 → 关闭
```

观察返工单数量和最终状态。
