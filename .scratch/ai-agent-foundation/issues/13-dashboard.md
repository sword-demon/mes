# 13: 看板

**What to build:** 九个看板接口都只统计当前用户范围内的数据；产线用户看到的今日产量、质量汇总、设备状态、最新列表只含本产线。

**Blocked by:** 04, 05, 06, 08, 12

**Status:** done

- [x] 今日订单数、计划数、产量、合格数、不良数、运行设备数按范围统计
- [x] 近 7 天生产趋势与产线产量按范围统计
- [x] 质量汇总与不良原因分布按范围统计
- [x] 设备状态分布按范围统计
- [x] 最新订单、最新报工、最新异常、最新告警按范围返回
- [x] 超级管理员看到的数字与改造前一致
- [x] 现有看板测试通过

## Comments

- 九个指标全部走模型 `inDataScope`（复用 ticket 04-12 已实现的各模型 scope），DashboardService 注入 DataScopeGuard。
- equipmentStatus 复用 EquipmentService::statusCounts 并给它加了可选 DataScope 参数（设备监控页的 status_counts 同步受益）。
- productionLines 的 join 聚合用 `DataScopeQuery::scopedProductionLineIds` 收窄：`all` 不过滤、line/factory 按范围内产线集合（含 COALESCE 任务产线兜底）；产线列表本身同步收窄。
- 趋势断言用显式 `reported_at`（本地日午时）固定在今日 bucket，避免 UTC/上海跨日漂移。
