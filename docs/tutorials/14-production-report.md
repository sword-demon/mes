# 14 生产报工

**目标**：对已下达任务提交报工，保证数量校验、进度累计和冲销回滚在事务中完成。

## 1. 哪些任务可以报工

订单必须处于可报工状态（`released`、`in_progress` 或 `paused`），该事实由 `ProductionOrder` 的 `isReportable()` 表达；任务自身不能是 `completed`。报工必须关联：

- 任务；
- 工艺路线中的工序；
- 启用员工；
- 可选设备（设备必须属于任务/订单产线并可用）。

## 2. 数量规则

报工接收三个数量：

```text
quantity            报工数
qualified_quantity  合格数
defective_quantity  不良数
```

必须满足：

```text
合格数 + 不良数 = 报工数
报工数 ≤ 任务剩余数量
```

项目把数量统一换算为千分位整数再运算：

```php
private function milli(mixed $value): int
{
    return (int) round((float) $value * 1000);
}
```

这样 `0.1 + 0.2` 不会因为浮点数变成 `0.30000000000000004`。

## 3. 报工事务

`ProductionReportService::create()`：

1. 锁定任务和订单；
2. 校验订单/任务状态；
3. 校验工序属于任务路线；
4. 校验员工、设备；
5. 校验数量；
6. 创建报工记录；
7. 同步累加任务和订单的已生产、合格、不良数量；
8. 写操作日志。

累计数量通过 `shiftQuantities()` 同时更新任务和订单，任何一步失败整个事务回滚。

## 4. 冲销而不是删除

已提交报工不能删除。冲销：

```text
POST /api/v1/production/reports/{report}/reverse
```

冲销会：

- 把报工类型改为 `reversed`；
- 记录 `reversed_at`；
- 从任务和订单累计数量中扣回原报工数量；
- 保留原记录作为审计证据。

有效报工使用 `scopeEffective()` 排除已冲销记录和已取消订单的报工：

```php
public function scopeEffective(Builder $query): void
{
    $query->where($query->qualifyColumn('type'), '!=', self::REVERSED)
        ->whereHas(
            'productionTask.productionOrder',
            fn (Builder $order) => $order->countable(),
        );
}
```

## 5. 前端

`frontend/web/src/pages/production/reports/index.tsx` 提供报工列表、筛选、报工表单和冲销操作。

报工表单通常从任务带出工序和人员选项，避免用户手填错误 ID。

## 6. 测试

```bash
php artisan test --compact tests/Feature/Api/ProductionReportTest.php
```

重点：

- 非可报工状态拒绝；
- 报工数超过剩余数量拒绝；
- 合格 + 不良 ≠ 报工数拒绝；
- 报工成功后任务和订单累计一致；
- 冲销后数量回退；
- 冲销不能重复；
- 冲销记录仍可查询。

## 练习

对一个任务提交 3 件报工（2 合格、1 不良），然后：

1. 提交数量不匹配的报工；
2. 提交超过剩余数量的报工；
3. 冲销刚才的记录；
4. 查看任务、订单和报工列表的数量变化。
