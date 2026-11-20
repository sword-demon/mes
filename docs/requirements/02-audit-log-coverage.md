# 02 审计日志覆盖需求

> 目标读者：后端开发、测试。本文定义"哪些写操作必须留痕、留什么、怎么留"。

## 1. 目标

让 `sys_operation_logs` 覆盖所有会改变业务状态的关键写操作，并为 AI 参与的操作提供可区分的来源标记与链路标识。

验收口径：任意一条业务数据的状态变更，都能在日志中查到"谁、在什么时候、把哪个字段从什么改成了什么"；若该操作由 AI 提议并由人工确认，能查出确认人与对应的 AI 会话。

## 2. 现状

### 2.1 已有实现

| 项 | 位置 |
| --- | --- |
| 日志表 | `sys_operation_logs`（`database/migrations/2026_11_17_010000_create_audit_log_tables.php:14-32`） |
| 写入服务 | `app/Modules/System/Service/OperationLogService.php` |
| 值对象 | `app/Modules/System/ValueObject/OperationLogEntry.php` |
| 查询接口 | `app/Modules/System/Controller/OperationLogController.php` |
| 前端页面 | `frontend/web` 系统管理 → 操作日志 |

表字段：

```text
id, user_id, user_name, action, action_name, object_type, object_id,
object_name, summary, changes(json), ip, created_at
```

已有约束（必须保留）：

- **日志在业务事务内写入**，随事务回滚一并回滚（示例：`ProductionOrderService::transition()` `app/Modules/Production/Service/ProductionOrderService.php:112-134`）。
- `changes` 只保留实际发生变化的字段（`OperationLogService::changes()` `:47-63`）。
- `summary` 由动作名、对象名与变更摘要拼装，截断 480 字符。

### 2.2 已覆盖范围

| 模块 | Service | 方法 | action |
| --- | --- | --- | --- |
| 生产 | `ProductionOrderService` | `transition` | `production.order.transition` |
| 生产 | `ProductionReportService` | `create` | `production.report.create` |
| 生产 | `ProductionReportService` | `reverse` | `production.report.reverse` |
| 库存 | `InventoryService` | `apply` | `inventory.inbound / outbound / adjust` |
| 系统 | `UserService` | `create / update / resetPassword / assignRoles` | `system.user.*` |
| 系统 | `RoleService` | `create / update / delete` | `system.role.*` |

### 2.3 覆盖缺口

以下模块的全部写操作均无审计日志：

| 模块 | 缺失数量 | 影响 |
| --- | --- | --- |
| 生产执行（任务、派工、工序、SN、异常） | 约 18 个方法 | 无法还原"谁下达了任务、谁开工、谁返工、谁作废 SN" |
| 质量（检验、不良品） | 约 9 个方法 | 检验结果与报废处置不可追溯，合规风险最高 |
| 设备（台账、告警处理） | 约 5 个方法 | 告警关闭无责任人记录 |
| 物料（领料、退料） | 约 5 个方法 | 发料与退料审核不可追溯 |
| 库存预警（确认、关闭） | 2 个方法 | 预警处置不可追溯 |
| 系统（部门、菜单） | 6 个方法 | 权限基础设施变更不可追溯 |

## 3. 范围

### 3.1 本期做

- 补齐上表全部关键写操作（共 47 个方法）。
- 日志表增加 `source`、`trace_id` 字段。
- 统一 action 命名规范与快照字段规范。
- 日志查询接口增加 `object_type`、`source`、`trace_id`、`keyword` 过滤。
- AI 写操作的记录规范。
- 覆盖测试。

### 3.2 本期不做

- 日志归档与冷存储。
- 日志防篡改（哈希链、WORM 存储）。
- 通知与告警（如"检测到批量删除"）。
- 只读查询的日志（含 AI 只读调用）。
- 登录日志改造（`sys_login_logs` 已独立实现）。

## 4. 详细设计

### 4.1 字段扩展

新增迁移：

```text
sys_operation_logs
  + source    varchar(16)  default 'human'  comment('操作来源：human/ai/system')
  + trace_id  varchar(64)  nullable         comment('调用链路标识')
  + index(source)
  + index(trace_id)
```

语义：

| source | 含义 | user_id 记录 |
| --- | --- | --- |
| `human` | 用户在界面/API 直接操作 | 操作人 |
| `ai` | AI 提议、人工确认后执行 | **确认人** |
| `system` | 定时任务、MQTT 消费、系统级写入 | null（`user_name` 为"系统"） |

`trace_id` 用于串联同一次业务动作产生的多条日志，以及关联 `sys_ai_tasks`（见 03 文档）。人工操作可以不传，AI 操作必传。

**不新增** `actor_type`、`ai_model` 等字段：AI 的模型、提示词、耗时等信息属于 AI 任务记录，通过 `trace_id` 关联，不污染业务审计表。

### 4.2 覆盖清单

> 说明：表中"现状"为「已有」的条目只做规范校验，不重写；「缺失」的条目需要新增记录。

#### 生产模块

| Service | 方法 | action | actionName | objectType | 现状 |
| --- | --- | --- | --- | --- | --- |
| `ProductionOrderService` | `create` | `production.order.create` | 新增生产订单 | 生产订单 | 缺失 |
| `ProductionOrderService` | `update` | `production.order.update` | 编辑生产订单 | 生产订单 | 缺失 |
| `ProductionOrderService` | `transition` | `production.order.{action}` | 订单{动作名} | 生产订单 | 已有，建议细分 |
| `ProductionTaskService` | `split` | `production.task.split` | 拆分生产任务 | 生产任务 | 缺失 |
| `ProductionTaskService` | `assign` | `production.task.assign` | 生产派工 | 生产任务 | 缺失 |
| `ProductionTaskService` | `transition` | `production.task.{action}` | 任务{动作名} | 生产任务 | 缺失 |
| `ProductionDispatchService` | `create` | `production.dispatch.create` | 新增派工单 | 派工单 | 缺失 |
| `ProductionReportService` | `create` | `production.report.create` | 生产报工 | 报工记录 | 已有 |
| `ProductionReportService` | `reverse` | `production.report.reverse` | 冲销报工 | 报工记录 | 已有 |
| `ProcessRecordService` | `start` | `production.flow.start` | 工序开工 | 工序执行 | 缺失 |
| `ProcessRecordService` | `complete` | `production.flow.complete` | 工序完工 | 工序执行 | 缺失 |
| `ProcessRecordService` | `inspect` | `production.flow.inspect` | 工序检验判定 | 工序执行 | 缺失 |
| `ProcessRecordService` | `submit` | `production.flow.submit` | 提交下道 | 工序执行 | 缺失 |
| `ProcessRecordService` | `receive` | `production.flow.receive` | 下道接收 | 工序执行 | 缺失 |
| `ProcessRecordService` | `exception` | `production.flow.exception` | 工序异常 | 工序执行 | 缺失 |
| `ProcessRecordService` | `rework` | `production.flow.rework` | 工序返工 | 工序执行 | 缺失 |
| `ProductSnService` | `generate` | `production.sn.generate` | 生成产品 SN | 产品 SN | 缺失 |
| `ProductSnService` | `bind` | `production.sn.bind` | 绑定产品 SN | 产品 SN | 缺失 |
| `ProductSnService` | `print` | `production.sn.print` | 打印产品 SN | 产品 SN | 缺失 |
| `ProductSnService` | `void` | `production.sn.void` | 作废产品 SN | 产品 SN | 缺失 |
| `ProductionExceptionService` | `report` | `production.exception.report` | 上报生产异常 | 生产异常 | 缺失 |
| `ProductionExceptionService` | `process` | `production.exception.process` | 处理生产异常 | 生产异常 | 缺失 |
| `ProductionExceptionService` | `close` | `production.exception.close` | 关闭生产异常 | 生产异常 | 缺失 |

补充说明：

- `ProcessRecordService::createForTask()` 由 `ProductionTaskService::split()` 内部调用，**不单独记录**，跟随 `production.task.split` 一并留痕。
- `ProductSnService::prepareLabel()` 仅做打印前置校验，**不记录**。
- `transition` 类方法建议把 `action` 细分为具体动作（如 `production.order.release`），否则无法按"下达/完成/取消"筛选。历史 `production.order.transition` 记录保留，不回改。

#### 质量模块

| Service | 方法 | action | actionName | objectType | 现状 |
| --- | --- | --- | --- | --- | --- |
| `InspectionItemService` | `save` | `quality.standard.save` | 维护检验标准 | 检验标准 | 缺失 |
| `InspectionTaskService` | `create` | `quality.inspection.create` | 新建检验任务 | 检验任务 | 缺失 |
| `InspectionTaskService` | `record` | `quality.inspection.record` | 录入检验结果 | 检验任务 | 缺失 |
| `InspectionTaskService` | `addImages` | `quality.inspection.add-images` | 上传检验图片 | 检验任务 | 缺失 |
| `DefectiveProductService` | `create` | `quality.defect.create` | 登记不良品 | 不良品 | 缺失 |
| `DefectiveProductService` | `start` | `quality.defect.start` | 开始处理不良品 | 不良品 | 缺失 |
| `DefectiveProductService` | `rework` | `quality.defect.rework` | 不良品返工 | 不良品 | 缺失 |
| `DefectiveProductService` | `scrap` | `quality.defect.scrap` | 不良品报废 | 不良品 | 缺失 |
| `DefectiveProductService` | `recheck` | `quality.defect.recheck` | 不良品复检 | 不良品 | 缺失 |

快照要求：

- 检验结果必须记录 `passed`、判定人、检验轮次；实测值明细较多，不逐项写入 `changes`，只记录"合格/不合格"与不合格项数量。
- 报废必须记录 `scrap_quantity` 与 `scrap_reason`。

#### 设备模块

| Service | 方法 | action | actionName | objectType | 现状 |
| --- | --- | --- | --- | --- | --- |
| `EquipmentService` | `create` | `equipment.ledger.create` | 新增设备 | 设备 | 缺失 |
| `EquipmentService` | `update` | `equipment.ledger.update` | 编辑设备 | 设备 | 缺失 |
| `EquipmentAlarmService` | `confirm` | `equipment.alarm.confirm` | 确认设备告警 | 设备告警 | 缺失 |
| `EquipmentAlarmService` | `process` | `equipment.alarm.process` | 处理设备告警 | 设备告警 | 缺失 |
| `EquipmentAlarmService` | `close` | `equipment.alarm.close` | 关闭设备告警 | 设备告警 | 缺失 |

补充说明：

- `EquipmentAlarmService::evaluate()` 由 MQTT 消费触发，告警**创建/升级**建议记录 `source = system`，`user_name = 系统`。若日志量过大，可只记录 `critical` 级别升级，需在实施前确认。
- `EquipmentMessageService::consume()` 每条采样不记录。

#### 物料模块

| Service | 方法 | action | actionName | objectType | 现状 |
| --- | --- | --- | --- | --- | --- |
| `MaterialRequisitionService` | `create` | `material.requisition.create` | 创建领料单 | 领料单 | 缺失 |
| `MaterialRequisitionService` | `issue` | `material.requisition.issue` | 领料发料 | 领料单 | 缺失 |
| `MaterialReturnService` | `create` | `material.return.create` | 创建退料单 | 退料单 | 缺失 |
| `MaterialReturnService` | `approve` | `material.return.approve` | 审核退料 | 退料单 | 缺失 |
| `MaterialReturnService` | `cancel` | `material.return.cancel` | 撤销退料 | 退料单 | 缺失 |

快照要求：

- 发料必须记录每条物料的实发数量与库位；若行数过多，写入 `changes` 时聚合为"物料编码 × 数量"摘要，避免单条日志超过 64KB。

#### 库存模块

| Service | 方法 | action | actionName | objectType | 现状 |
| --- | --- | --- | --- | --- | --- |
| `InventoryService` | `apply` | `inventory.inbound / outbound / adjust` | 入库/出库/库存调整 | 库存 | 已有 |
| `InventoryAlertService` | `confirm` | `inventory.alert.confirm` | 确认库存预警 | 库存预警 | 缺失 |
| `InventoryAlertService` | `close` | `inventory.alert.close` | 关闭库存预警 | 库存预警 | 缺失 |

补充说明：

- `InventoryAlertService::scan()` 是定时任务，不记录（每 5 分钟一次，噪音大）。

#### 系统模块

| Service | 方法 | action | actionName | objectType | 现状 |
| --- | --- | --- | --- | --- | --- |
| `UserService` | 4 个方法 | `system.user.*` | 新增/编辑/重置密码/分配角色 | 用户 | 已有 |
| `RoleService` | 3 个方法 | `system.role.*` | 新增/编辑/删除角色 | 角色 | 已有 |
| `DepartmentService` | `create / update / delete` | `system.department.*` | 部门维护 | 部门 | 缺失 |
| `MenuService` | `create / update / delete` | `system.menu.*` | 菜单维护 | 菜单 | 缺失 |

**不记录**：登录（已有独立的 `sys_login_logs`）；Token 创建与撤销（等价于登录/登出，不改变业务数据）。

#### 汇总

| 状态 | 数量 |
| --- | --- |
| 已覆盖 | 11 个方法 |
| 待补齐 | 47 个方法 |
| 明确不记录 | 4 处（`createForTask`、`prepareLabel`、`scan`、采样消费） |

### 4.3 记录规范

#### 命名

- `action`：`<module>.<entity>.<verb>`，动词用原形。现有 `system.user.created` 等过去式写法**保留历史不动**，新接入统一用原形。
- `actionName`：中文动词短语，如「生产报工」「不良品报废」。
- `objectType`：中文名词，如「生产订单」「检验任务」。
- `objectName`：业务编号优先（订单号、任务号、SN），无编号时用名称。

#### 快照

- `before` / `after` 的键用中文可读标签（沿用现有风格），值保持原始类型。
- 只记录发生变化的字段；未变化字段不传。
- 数量字段保留业务精度（字符串形式的 decimal），不转 float。
- 状态字段记录**中文标签**（现有 `statusLabel()` 模式），不记录英文枚举。

#### 脱敏

以下字段一律不得进入 `changes`：

```text
password, password_confirmation, remember_token,
token, access_token, secret, private_key
```

`summary` 中也不得出现上述内容。

#### 事务

- 日志写入必须位于产生业务变更的同一事务内。
- 禁止在事务提交后补写日志（会出现"数据已改、日志丢失"或反向的不一致）。
- 批量操作（如一次发料多行）写一条汇总日志，不逐行写。

### 4.4 AI 操作规范

#### 记录时机

| 场景 | 是否记录 | 说明 |
| --- | --- | --- |
| AI 只读查询 | 否 | 避免噪音；AI 调用本身记入 `sys_ai_tasks` |
| AI 生成建议但用户未采纳 | 否 | 记入 `sys_ai_tasks`，不进业务审计 |
| AI 建议 + 用户确认 + 系统执行 | **是** | `source = ai`，`user_id = 确认人` |
| AI 自动执行（无人工确认） | **禁止** | 本期不允许任何无确认的 AI 写操作 |

#### 记录字段

```text
source   = 'ai'
user_id  = 人工确认人 ID
user_name= 人工确认人姓名
trace_id = AI 会话 ID（与 sys_ai_tasks.trace_id 相同）
```

人工确认人与实际操作人可能是同一人（用户点了"确认执行"），此时不需要额外字段；如需记录"建议由哪个 Agent 产生"，通过 `trace_id` 查 `sys_ai_tasks`。

#### 执行路径约束

- Agent 不得直接操作数据库或 Model。
- Agent 的写操作必须调用既有 Service 方法，复用其事务、状态机与校验。
- Service 内部不需要感知调用方是 AI；`source` 与 `trace_id` 通过上下文传递，不在每个方法签名中增加参数。

实现建议：新增 `app/Core/Audit/AuditContext`（请求级单例），提供 `source`、`traceId`、`actor` 三个值；`OperationLogService::record()` 在未显式传参时从上下文读取。HTTP 请求默认 `source = human`，AI 执行入口显式设置为 `ai`。

### 4.5 查询与导出

现有接口 `GET /api/v1/system/operation-logs` 支持 `user_id / action / start_date / end_date`。

新增过滤：

| 参数 | 类型 | 说明 |
| --- | --- | --- |
| `object_type` | string | 对象类型 |
| `source` | string | `human / ai / system` |
| `trace_id` | string | 链路标识，精确匹配 |
| `keyword` | string | 模糊匹配 `object_name`、`summary` |

`GET /api/v1/system/operation-logs/options` 的 `actions` 列表需要同步返回 `source` 可选值。

导出：本期不做。若后续需要，单独设计异步导出（复用 03 文档的队列能力）。

**只读约束**：不提供修改、删除日志的接口；不提供"清理历史日志"的接口。

### 4.6 保留与归档

- 保留期：**不少于 180 天（已确认）**。
- 本期不实现自动归档；表数据量增长后按季度评估。
- 不提供删除日志的接口；到期数据由 DBA 按运维流程处理，应用层不主动清理。
- 数据量预估：生产高峰期，工序操作 + 报工 + SN 操作合计约 2000~5000 条/天，180 天约 36 万~90 万条，单表可承受，暂不需要分区。

## 5. 接口变更

| 接口 | 变更 |
| --- | --- |
| `GET /api/v1/system/operation-logs` | 新增 4 个过滤参数 |
| `GET /api/v1/system/operation-logs/options` | 新增 `sources` 列表 |
| 其余业务接口 | 请求/响应不变，仅内部写日志 |

`OperationLogResource` 需要新增 `source`、`trace_id` 字段。

## 6. 数据迁移

1. 新增 `source`（默认 `human`）、`trace_id`（可空）。
2. 历史记录 `source` 全部为 `human`：现有 11 个已覆盖方法均为人工操作，语义正确。
3. 历史 `system.user.created` / `system.role.created` 等过去式 action 保留，不做重命名。
4. 无数据回填需求。

## 7. 验收标准

### 7.1 覆盖

- [ ] 覆盖清单中每个方法执行后，`sys_operation_logs` 新增一条记录。
- [ ] 每条记录的 `action`、`action_name`、`object_type`、`object_id`、`object_name` 非空且与业务对象一致。
- [ ] 状态变更类操作，`changes` 中能读出变更前后的中文状态。
- [ ] 批量发料只产生一条日志，`changes` 包含所有物料摘要。

### 7.2 事务一致性

- [ ] 业务事务抛异常回滚时，不产生日志。
- [ ] 日志写入失败时，业务事务一并回滚。

### 7.3 安全

- [ ] 重置密码、Token 相关操作，日志中不含密码或 Token 明文。
- [ ] 日志接口不提供写入/删除能力。

### 7.4 AI

- [ ] AI 确认执行的操作，`source = ai`，`user_id` 为确认人。
- [ ] 同一 AI 会话产生的多条日志 `trace_id` 相同。
- [ ] 按 `source = ai` 可筛选出全部 AI 参与的操作。
- [ ] AI 只读查询不产生 `sys_operation_logs` 记录。

### 7.5 测试组织

新增 `tests/Feature/Api/AuditCoverageTest.php`，用 Pest dataset 遍历覆盖清单，断言每个写接口产生预期的 `action`。

现有 `tests/Feature/Api/AuditLogTest.php` 保留并扩展。

## 8. 实施拆分

按模块分批，每批包含"补日志 + 补测试"：

| 批次 | 模块 | 方法数 | 说明 |
| --- | --- | --- | --- |
| B1 | 质量 | 9 | 合规优先级最高 |
| B2 | 生产执行 | 20 | 量最大，与 01 文档 S4 写操作校验合并改造 |
| B3 | 物料 + 库存预警 | 7 | |
| B4 | 设备 | 5 | 注意系统自动来源的噪音控制 |
| B5 | 系统（部门、菜单） | 6 | |
| B6 | 字段扩展 + 查询增强 + 上下文 | — | 可与 B1 并行 |

建议顺序：B6（基础设施）→ B1 → B2 → B3 → B4 → B5。

## 9. 风险与已确认决策

### 9.1 已确认决策

| 编号 | 问题 | 结论 |
| --- | --- | --- |
| D3 | 审计日志保留期 | **不少于 180 天**，不提供删除接口 |
| D4 | 是否区分"AI 建议"与"AI 自动" | 本期只允许人机确认模式，`source` 仅保留 `ai` 一个值 |

### 9.2 风险

| 风险 | 影响 | 缓解 |
| --- | --- | --- |
| 日志量增长 | 报工与工序操作频繁 | 单条汇总、索引优化；上线后观察一周 |
| 队列上下文丢失用户 | 异步 AI 任务写日志时 `user_id`/`ip` 为空 | 通过 `AuditContext` 显式传 actor 与 ip（详见 03 文档 §4.6） |
| 改造遗漏 | 新接口忘记写日志 | 用 dataset 覆盖测试；Code Review 检查清单 |
| `changes` 过大 | 发料/检验明细多行 | 聚合摘要，单条日志控制在 16KB 内 |

### 9.3 实施时确认

| 项 | 默认方案 |
| --- | --- |
| 设备告警自动创建（`EquipmentAlarmService::evaluate()`）是否记录 | 只记录 `critical` 级别升级，`source = system`，避免每 5 秒一条的噪音 |
| 检验图片上传是否记录 | 记录，`action = quality.inspection.add-images`，只记文件数量，不记文件名 |
