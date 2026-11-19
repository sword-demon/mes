# 生产订单状态语义归 ProductionOrder 所有

生产订单的状态语义（可报工、可生成 SN、可领料、可拆分、可统计、可编辑、允许的动作）由 `ProductionOrder` 拥有；动作表是唯一事实源，`allowed_actions` 只返回领域动作，权限判断留在调用方。`edit` 不是状态迁移，由 `editable` 单独表达；完成/关闭暂不增加任务、工序或报工 gate，保持当前行为。

## Consequences

- 订单状态语义只改 `ProductionOrder`，不再在 service 或前端复制状态数组。
- `allowed_actions` 只描述当前状态允许的迁移动作，不代表用户有权限执行。
- 完成/关闭的业务收口若需要收紧，另立决策，不把当前行为当成已实现的不变量。
