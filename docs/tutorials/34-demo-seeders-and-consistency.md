# 34 全量演示数据与一致性自检

**目标**：用一套可重复执行的 Seeder 生成完整演示数据，并在数据不自洽时立即失败。

## 1. Seeder 顺序

`DatabaseSeeder` 固定顺序：

```php
SystemSeeder
OrganizationSeeder
MaterialSeeder
ProductSeeder
ProcessSeeder
ProductionSeeder
QualitySeeder
EquipmentSeeder
TraceabilitySeeder
```

原因是有依赖：

- 产品依赖物料；
- BOM 依赖产品和物料；
- 生产依赖 BOM、路线、产线、班组；
- 质量/追溯依赖生产数据；
- 设备依赖组织和员工。

## 2. 数据体量

`DatabaseSeederTest` 保证：

```text
订单 ≥ 20
任务 ≥ 30
有效报工 ≥ 100
检验任务 ≥ 50
批次库存 ≥ 30
SN ≥ 50
告警 ≥ 20
角色 ≥ 8
用户 ≥ 20
```

## 3. 状态覆盖

- 订单 9 态；
- 任务 4 态；
- 报工 3 类（normal/batch/reversed）；
- 工序 7 态；
- 不良品 4 态；
- 检验有合格/不合格；
- 设备 5 态。

每一类至少有一条数据，保证列表、筛选和详情页都有内容。

## 4. 六条一致性规则

`DemoDataConsistencyChecker` 检查：

1. 合格数 + 不良数 = 报工数；
2. 订单已生产数 = 其下有效报工之和；
3. 报工数 ≤ 任务计划数；
4. 库存流水净额 = 库存当前值；
5. SN 全局唯一且批次属于订单；
6. 维修/离线设备没有产量增长。

任何一条不成立都会抛 `RuntimeException`，Seeder 失败。

## 5. 时间分布

ProductionSeeder 用 `Carbon` 相对时间生成数据：

- 最近 7 天有报工；
- 今日至少有订单和报工；
- Seeder 在午夜后执行也不会把今日数据算到昨天；
- 不生成未来时间的报工。

## 6. 幂等

所有 Seeder 使用 `updateOrCreate` / `firstOrCreate`，并在需要时校准库存流水。连续执行两次，各表行数不增长。

## 7. 测试

```bash
php artisan test --compact tests/Feature/DatabaseSeederTest.php
```

重点：

- 全量体量下限；
- 状态全覆盖；
- 一致性检查通过；
- 六条规则被破坏时测试能捕获；
- 两次 seed 行数一致；
- 时间分布正确。

## 练习

手动把一条有效报工的合格数 +1，然后执行：

```bash
php artisan db:seed
```

观察一致性检查在哪一条规则失败。
