# mes

制造执行系统，覆盖生产订单、任务、工序流转、质量、物料与设备追溯。

## Language

**生产订单 (Production Order)**:
一张按产品、BOM、工艺路线和产线组织生产的历史单据；它固定版本快照，并拥有从草稿到关闭的状态。
_Avoid_: 工单, 生产工单

**订单状态 (Order Status)**:
生产订单在生命周期中的当前阶段；合法迁移由订单状态机决定。
_Avoid_: 订单阶段, 订单进度

**订单动作 (Order Action)**:
改变生产订单状态的命名业务动作，例如提交、审核、下达、开工、暂停、恢复、完成、关闭、取消；编辑不属于订单动作。
_Avoid_: 按钮, 操作

**可编辑订单 (Editable Order)**:
订单处于 `draft` 时，允许修改订单内容；编辑不是一次状态迁移。
_Avoid_: 草稿订单, 可修改订单

**可拆分订单 (Splittable Order)**:
订单处于 `released` 时，允许拆分为生产任务。
_Avoid_: 可下达订单, 可排产订单

**可报工状态 (Reportable Order Status)**:
订单处于 `released`、`in_progress` 或 `paused` 时，允许对订单下的任务报工；任务自身是否可报工是任务级事实。
_Avoid_: 可生产订单, 执行中订单

**可生成 SN 状态 (Serial-Number-Generatable Order Status)**:
订单处于 `released`、`in_progress` 或 `paused` 时，允许生成产品 SN。
_Avoid_: 可打印订单, 可追溯订单

**可领料状态 (Requisitionable Order Status)**:
订单处于 `released`、`in_progress` 或 `paused` 时，允许创建领料单。
_Avoid_: 可发料订单, 可投料订单

**可统计订单 (Countable Order)**:
未被取消的生产订单；它进入生产统计口径，不代表订单已经开工或完成。
_Avoid_: 有效订单, 统计有效订单

**允许的动作 (Allowed Actions)**:
当前订单状态下领域允许执行的动作集合；用户是否能执行还要再经过权限判断。
_Avoid_: 可用按钮, 操作权限

**订单完成 (Order Completion)**:
订单从 `in_progress` 进入 `completed` 的状态迁移；当前不要求任务、工序或报工全部收口。
_Avoid_: 订单结案, 生产结束

**订单关闭 (Order Closure)**:
订单从 `completed` 进入 `closed` 的终态迁移；当前不再检查其他业务对象。
_Avoid_: 订单归档, 订单结束
