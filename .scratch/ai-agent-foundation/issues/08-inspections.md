# 08: 检验任务与检验标准

**What to build:** 质检员只能看到和创建本产线订单的检验任务；检验标准维护留下日志；检验任务创建、结果录入、图片上传留下日志。

**Blocked by:** 01, 02, 04

**Status:** done

- [x] 检验任务列表按订单归属过滤
- [x] 跨范围创建检验任务返回 403
- [x] 检验标准保存产生一条日志，记录标准值变化
- [x] 检验任务创建、结果录入、图片上传各产生一条日志
- [x] 检验结果日志记录合格判定与不合格项数量
- [x] 检验结果日志不含逐项实测值明细
- [x] 现有检验测试通过

## Comments

- ticket 05 移交的 inspect 端到端回归已补：质检录入接口对范围外工序/任务返回 403（DataScopeTest），工序层 `production.flow.inspect` 的联动日志在审计用例中一并验证。
- 检验标准（InspectionItem）为基础数据，不实现 HasDataScope（全局共享）。
- record 日志的检验轮次取任务的 `inspection_cycle` 快照（创建时锁定），与工序记录的轮次推进独立。
- addImages 补了事务包裹（对齐异常上报模式）；历史遗留的单文件失败清理路径保留。
- 审查确认标准保存快照 before/after 用同一 snapshot 方法，键与格式对称；两处 taskLabel 概念相似但类型不同，按 rule of three 暂不提取。
