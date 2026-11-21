# 01 数据权限需求

> 目标读者：后端开发、测试。本文定义"用户能看到哪些行"的规则，不涉及"能不能调用接口"——后者已由 `permission:` 中间件实现。

## 1. 目标

让 `data.scope.all / factory / line` 从**只读的展示值**变成**后端强制执行的查询与写入约束**，使不同工厂、不同产线的用户只能访问归属范围内的生产数据。

验收口径：一个仅有 `production_line_id = A` 的用户，通过任何接口（列表、详情、看板、追溯、导出）都无法获得产线 B 的数据。

## 2. 现状与差距

### 2.1 已有能力

| 能力 | 位置 | 状态 |
| --- | --- | --- |
| 权限码解析 | `app/Modules/System/Service/AccessControlService.php:24-41` | 可用 |
| 数据范围解析 | `AccessControlService::dataScope(): string`（`:63-78`） | 已实现，未执行 |
| 超管绕过 | `AccessControlService::isSuperAdmin()`（`:52-55`） | 可用 |
| 数据范围权限码 | `database/seeders/SystemSeeder.php:246-250` | 已种子化 |
| 角色范围映射 | `SystemSeeder.php` 的 `ROLES` 常量 | 已种子化 |

`dataScope()` 返回 `all | factory | line | none`，无授权时 fail closed 返回 `none`，这部分逻辑正确，保留。

### 2.2 差距

1. **唯一的调用点是 `/api/v1/me`**（`app/Modules/System/Controller/MeController.php:30`），业务查询没有任何过滤。
2. **用户没有工厂/产线归属**。`sys_users` 只有 `department_id`、`employee_id`（`app/Modules/System/Model/User.php:26-36`），没有 `factory_id`、`production_line_id`；`mes_employees` 只有 `department_id`、`team_id`，`mes_teams` 只属于车间，整条链路推不出"用户负责哪条产线"。
3. **组织层级存在但未被使用**：`mes_factories → mes_workshops → mes_production_lines`，`mes_teams → mes_workshops`，`mes_employees → mes_teams`。
4. **仓库不属于任何工厂**：`mes_warehouses` 只有 `code / name / status`，库存无法按工厂隔离。
5. **库存预警是全局聚合**：`mes_inventory_alerts` 按 `material_id` 唯一（`mes_inventory_alerts_material_active_unique`），没有工厂维度。

### 2.3 影响面

| 角色 | 当前范围 | 越权后果 |
| --- | --- | --- |
| `workshop_director` / `production_operator` / `quality_inspector` | `line` | 能看到全厂订单、报工、质检记录 |
| `factory_admin` / `production_manager` / `quality_manager` / `warehouse_manager` / `equipment_manager` | `factory` | 多工厂部署时能看到其他工厂数据 |
| `super_admin` | `all` | 无越权问题 |

## 3. 范围

### 3.1 本期做

- 用户与工厂、产线的归属关系（数据模型 + 管理接口）。
- 所有列表、详情、统计、追溯接口的读取过滤。
- 所有写操作的目标对象归属校验。
- 仓库归属工厂，库存与库存预警按工厂隔离。
- 迁移工具与回填脚本。
- 覆盖三种范围的 Feature Test。

### 3.2 本期不做

- 基础数据（产品、物料、BOM、工艺路线、工序）按工厂隔离。
- 字段级权限（同一行内隐藏部分字段）。
- 部门维度的数据权限。
- 动态数据范围规则（如"按创建人"、"按班组"）。

## 4. 详细设计

### 4.1 用户归属模型

**决策：工厂为单归属，产线为多归属。**

理由：

- 一个账号只属于一个工厂，是 MES 的常态，且便于回填与校验。
- 一人负责多条产线是真实场景（车间主任、质检员、设备管理员），单字段 `production_line_id` 无法表达，后期改造成本高于现在建关联表。
- 与现有 `sys_user_roles` 的多对多模式一致。

新增迁移：

```text
sys_users
  + factory_id  bigint nullable  comment('所属工厂 ID')  ->constrained('mes_factories')->nullOnDelete()

sys_user_production_lines
  + id                  bigint
  + user_id             bigint  comment('用户 ID')
  + production_line_id  bigint  comment('生产产线 ID')
  + created_at / updated_at
  + unique(user_id, production_line_id)
```

约束：

- `scope = factory` 或 `scope = line` 的用户，`factory_id` 必填。
- `scope = line` 的用户，至少要有一条 `sys_user_production_lines` 记录；否则该用户看不到任何数据（fail closed）。
- `scope = all` 的用户不要求配置。

### 4.2 范围解析

保留 `dataScope(): string` 以兼容 `/api/v1/me` 的 `data_scope` 字段，新增结构化对象：

```php
namespace App\Core\DataScope;

final readonly class DataScope
{
    /**
     * @param  array<int, int>  $productionLineIds
     */
    public function __construct(
        public string $level,               // all | factory | line | none
        public ?int $factoryId = null,
        public array $productionLineIds = [],
    ) {}

    public function isAll(): bool;
    public function isNone(): bool;
}
```

新增方法：

```php
AccessControlService::resolveDataScope(User $user): DataScope
```

解析规则：

| 条件 | level | factoryId | productionLineIds |
| --- | --- | --- | --- |
| 超管 | `all` | null | `[]` |
| 有 `data.scope.all` | `all` | null | `[]` |
| 有 `data.scope.factory` | `factory` | `user.factory_id` | `[]` |
| 有 `data.scope.line` | `line` | `user.factory_id` | 关联表产线 |
| 无任何 `data.scope.*` | `none` | null | `[]` |
| `factory`/`line` 但 `factory_id` 为空 | `none` | null | `[]` |
| `line` 但关联表为空 | `none` | null | `[]` |

**fail closed 原则**：配置缺失时返回 `none`，不降级为 `all`。

### 4.3 模型归属路径

按"如何到达产线"把模型分成四类。每类实现统一的 Eloquent local scope：

```php
namespace App\Core\DataScope;

interface HasDataScope
{
    /**
     * @param  Builder<static>  $query
     */
    public function scopeInDataScope(Builder $query, DataScope $scope): void;
}
```

调用方式：`ProductionOrder::query()->inDataScope($scope)`。

#### A. 直接持有 `production_line_id`

| 表 | 字段 | 说明 |
| --- | --- | --- |
| `mes_production_orders` | `production_line_id`（必填） | `whereIn` |
| `mes_production_tasks` | `production_line_id`（可空） | 为空时按订单产线兜底 |
| `mes_equipments` | `production_line_id` + `workshop_id` | `whereIn` |

#### B. 通过生产任务

| 表 | 路径 |
| --- | --- |
| `mes_process_records` | `production_task_id → mes_production_tasks.production_line_id` |
| `mes_production_reports` | `production_task_id → mes_production_tasks.production_line_id` |
| `mes_dispatch_orders` | `production_task_id → mes_production_tasks.production_line_id` |

实现用 `whereHas('productionTask', fn ($q) => $q->inDataScope($scope))`，不额外 join。

#### C. 通过生产订单

| 表 | 路径 |
| --- | --- |
| `mes_production_batches` | `production_order_id` |
| `mes_product_sns` | `production_order_id` |
| `mes_production_exceptions` | `production_order_id`，为空时走 `process_record_id` |
| `mes_inspection_tasks` | `production_order_id` |
| `mes_defective_products` | `production_order_id` |
| `mes_material_requisitions` | `production_order_id` |
| `mes_material_returns` | `production_order_id` / `requisition_id` |

#### D. 通过设备或仓库

| 表 | 路径 |
| --- | --- |
| `mes_equipment_data` | `equipment_id → mes_equipments.production_line_id` |
| `mes_equipment_alarms` | `equipment_id → mes_equipments.production_line_id` |
| `mes_storage_locations` | `warehouse_id → mes_warehouses.factory_id` |
| `mes_inventories` | `location_id → mes_storage_locations → mes_warehouses` |
| `mes_inventory_transactions` | `inventory_id → mes_inventories → ...` |
| `mes_inventory_alerts` | 本期不纳入行级过滤，保持全局口径（见 §4.10） |

### 4.4 基础数据边界

**建议方案：基础数据全局共享，不做工厂隔离（待确认，见 §9.2 Q1）。**

涉及模块：`Product`、`Material`、`Bom`、`Process`（工序）、`ProcessRoute`、`Factory`、`Workshop`、`ProductionLine`、`Team`、`Employee`。

理由：

1. 这些是主数据，跨工厂复用是常态（同一产品可能在多个工厂生产）。
2. 隔离需要给每张表加 `factory_id`，并处理引用完整性（BOM 引用物料、订单引用 BOM 与路线），改动面远大于收益。
3. 当前 MVP 实际单工厂运行。

风险与缓解：

- 任何 `factory_admin` 都能修改被其他工厂引用的主数据。缓解方式是收紧写权限码，不通过数据范围解决。
- 本决策写入 ADR，避免后续被误认为是遗漏。

**例外**：`mes_warehouses` 必须加 `factory_id`，因为库存是工厂级业务数据，不是主数据。

### 4.5 查询过滤

#### 覆盖清单

| 模块 | 接口 | 过滤方式 |
| --- | --- | --- |
| Dashboard | 9 个接口（`today`、`production-trend`、`production-lines`、`quality`、`equipment-status`、`latest-*` 4 个） | 见 §4.6 |
| Production | `production.orders.index` | A |
| Production | `production.tasks.index` | A（产线为空时按订单） |
| Production | `production.dispatches.index / mine` | B |
| Production | `production.reports.index` | B |
| Production | `production.process-records.index` | B |
| Production | `production.exceptions.index` | C |
| Production | `production.sns.index / show` | C |
| Quality | `quality.inspection-tasks.index` | C |
| Quality | `quality.defective-products.index` | C |
| Material | `materials.requisitions.index` | C |
| Material | `materials.returns.index` | C |
| Inventory | `inventory.index` | D |
| Inventory | `inventory.alerts.index` | 本期不纳入行级过滤（见 §4.10） |
| Equipment | `equipments.index`、`equipments.monitor`、`equipments.history`、`equipments.alarms.index` | A / D |
| Traceability | `traceability.forward.show`、`traceability.backward.show` | 见 §4.7 |
| System | `system.users.index`、`system.operation-logs.index` | 按 `factory_id` 过滤用户；操作日志见 02 文档 |

说明：

- 生产订单、任务、检验任务、不良品、领料单、退料单、设备均**没有独立的 `show` 接口**，详情由列表页携带或前端按 ID 二次查询，因此只列 `index`。
- `mes_production_batches` 有表无独立接口，批次数据通过 SN 列表与追溯接口返回，不单列。
- 权限码 `production.flows.index` 对应的实际路由是 `/api/v1/production/process-records`。

#### 实现位置

在 Controller 的 `index` / `show` 中显式调用，不使用 Eloquent Global Scope。

理由：

1. Global Scope 会自动作用于 MQTT 消费、定时任务、CLI 等无用户上下文场景，容易误伤。
2. 现有 Controller 均为显式查询，显式调用与现有风格一致，便于测试定位。
3. Agent 工具层调用 Service 时，也必须显式传范围，避免"隐式生效"带来的认知负担。

为防止遗漏，增加一条架构测试：扫描所有 API 路由中的 `index` 方法，断言其查询构建链包含 `inDataScope`。该测试可以作为软约束，允许显式白名单（如基础数据）。

### 4.6 统计与看板

`app/Modules/Dashboard/Service/DashboardService.php` 的全部查询都必须先应用范围：

| 方法 | 需过滤的表 |
| --- | --- |
| `today()` | `mes_production_orders`、`mes_production_reports`、`mes_equipments` |
| `productionTrend()` | `mes_production_reports` |
| `productionLines()` | `mes_production_reports` + `mes_production_tasks` + `mes_production_orders` |
| `quality()` | `mes_production_reports`、`mes_defective_products` |
| `equipmentStatus()` | `mes_equipments` |
| `latestOrders()` | `mes_production_orders` |
| `latestReports()` | `mes_production_reports` |
| `latestExceptions()` | `mes_production_exceptions` |
| `latestAlarms()` | `mes_equipment_alarms` |

注意：`today()` 目前的统计口径是按订单 `created_at`，与"今日计划"语义不完全一致，本次不改口径，只加过滤。

### 4.7 追溯

追溯接口是越权风险最高的入口，因为入参是业务编号而非 ID，无法通过 ID 关系预判归属。

**正向追溯**（SN → 全链路）：

1. 先按 SN 查询 `mes_product_sns`，应用 `inDataScope`；
2. 查不到则返回 404，不泄露"该 SN 存在但无权访问"；
3. 后续所有关联查询基于已命中记录的 `production_order_id` 展开，天然受限。

**反向追溯**（物料批次 → 受影响产品）：

1. 物料批次号是全局字符串，入口本身没有归属；
2. 查询 `mes_inventory_transactions` 时按仓库归属过滤；
3. 由出库流水得到的领料单、订单、批次、SN 全部再应用一次 `inDataScope`；
4. 如果过滤后结果为空，返回"未找到该批次的使用记录"，不返回空结构但带总数。

**接口契约不变**：`data_granularity` 字段保留，只改变可见范围。

### 4.8 写操作校验

#### 校验规则

| 操作 | 校验对象 | 失败响应 |
| --- | --- | --- |
| 创建订单 | 请求中的 `production_line_id` | 403 `生产产线不在数据范围内` |
| 拆分任务 | 订单归属 + 请求中的 `production_line_id` | 403 |
| 派工 / 报工 / 工序操作 | 目标任务的产线 | 403 |
| 质检任务创建 | 目标订单 / 工序记录的产线 | 403 |
| 不良品处理 | 目标订单的产线 | 403 |
| 领料 / 退料 | 目标订单的产线 | 403 |
| 库存出入库 / 调整 | 目标库位所属仓库的工厂 | 403 |
| 设备台账 / 告警处理 | 目标设备的产线 | 403 |
| SN 生成 / 绑定 / 作废 | 目标订单的产线 | 403 |
| 用户管理 | 目标用户的 `factory_id` | 403 |

#### 实现位置

在 **Service 层**校验，不在 Controller。原因：Service 是当前所有写操作的唯一入口，Agent、队列任务、Artisan 命令都会经过它；只在 Controller 校验会被绕过。

新增：

```php
namespace App\Core\DataScope;

class DataScopeGuard
{
    public function __construct(
        private readonly AccessControlService $access,
        private readonly Request $request,
    ) {}

    public function scope(): DataScope;

    /**
     * @throws ApiException 403 当对象不在当前用户的数据范围内
     */
    public function ensure(Model $model): void;

    /**
     * @throws ApiException 403 当产线不在当前用户的数据范围内
     */
    public function ensureLine(?int $productionLineId): void;
}
```

改造方式：在需要校验的 Service 构造函数注入 `DataScopeGuard`，在写方法开头调用 `ensure*()`。这会触及约 16 个业务 Service，工作量与 02 文档的日志改造重叠，**建议两项合并实施**：每个写方法同时补 `ensure()` 与 `operationLogs->record()`。

#### 响应码

- 列表查询：静默过滤，不返回越权行。
- 详情 / 写操作：返回 403，消息明确（内部系统，用户已知自己有模块权限，404 会造成排查困难）。
- 追溯入口：返回 404，避免通过 SN 探测其他工厂的数据存在性。

### 4.9 边界情况

| 场景 | 处理 |
| --- | --- |
| 任务 `production_line_id` 为空 | 按所属订单的产线归属 |
| 生产异常 `production_order_id` 与 `process_record_id` 都为空 | **创建时拒绝**（已确认）：至少关联其一，否则返回 422；历史遗留的无归属记录仅 `all` 范围可见 |
| 设备未分配产线 | 仅 `all` 范围可见 |
| 历史数据中产线已删除 | 外键为 `restrictOnDelete`，不会出现；若出现脏数据，仅 `all` 可见 |
| 用户在多个角色中拥有不同 scope | 取最宽（`all` > `factory` > `line`），沿用 `dataScope()` 现有逻辑 |
| 用户 `status = false` | 由 `EnsureUserIsActive` 中间件拦截，不进入范围解析 |
| 无用户上下文的进程（MQTT、定时任务、CLI） | 不应用数据权限，这些进程只做系统级写入 |

### 4.10 库存与预警

#### 仓库归属

`mes_warehouses` 增加：

```text
+ factory_id  bigint nullable  comment('所属工厂 ID')  ->constrained('mes_factories')->nullOnDelete()
```

库存列表、流水、库位查询通过 `location_id → warehouse.factory_id` 过滤。

**兼容处理**：`factory_id` 为空的仓库视为"未分配"，其库存只对 `all` 范围可见；迁移脚本把现有仓库绑定到默认工厂。

#### 库存预警（已确认：本期不改造）

`mes_inventory_alerts` **保持现有结构**，不新增 `factory_id`；`InventoryAlertService::scan()` 继续按"全部仓库合计"判断最低库存。

这是有意接受的范围例外，依据：

1. 预警按物料聚合，同一物料可能分布在本工厂与其他工厂的多个仓库，拆分需要改表结构与唯一键，属于独立需求。
2. 预警字段是物料、最低库存、当前库存、状态，不含订单、客户或工艺信息，敏感度低于生产业务数据。
3. `inventory.alerts.index` 当前仅授予 `warehouse_manager`（`scope = factory`），影响面可控。

已知后果：

- `factory` 范围用户看到的预警水位包含其他工厂仓库的库存，数字偏大，可能延迟本工厂的补货判断。
- `line` 范围用户没有 `inventory.alerts.index` 权限，不受影响。

后续若多工厂实际运行、预警误判影响补货，再单独立项：给 `mes_inventory_alerts` 加 `factory_id`，唯一键改为 `(material_id, factory_id, active_key)`，`scan()` 按工厂分组聚合。

## 5. 接口变更

| 接口 | 变更 |
| --- | --- |
| `GET /api/v1/me` | 响应不变，`data_scope` 继续返回字符串 |
| `GET /api/v1/system/users` | 支持按 `factory_id` 过滤；非 `all` 用户只能看到本工厂用户 |
| `POST/PUT /api/v1/system/users` | 支持写入 `factory_id`、`production_line_ids` |
| `GET /api/v1/basic-data/factories` 等 | 不变（基础数据全局共享） |
| `GET /api/v1/dashboard/*` | 响应结构不变，数值按范围收窄 |
| `GET /api/v1/traceability/forward` | 范围外 SN 返回 404 |
| `GET /api/v1/traceability/backward` | 范围外记录不返回，空结果返回 404 |

新增管理能力（可选，二选一）：

- 在用户新增/编辑接口中承载 `production_line_ids`；
- 或新增 `PUT /api/v1/system/users/{user}/data-scope` 专用接口。

建议前者，减少接口数量。

## 6. 数据迁移

### 6.1 迁移步骤

1. 新增 `sys_users.factory_id`、`sys_user_production_lines`、`mes_warehouses.factory_id`。
2. 数据回填（提供 Artisan 命令 `mes:data-scope:backfill`）：
   - 所有用户绑定到第一个启用工厂；
   - `scope = line` 的用户绑定该工厂下的全部产线（先宽后严，避免上线即不可见）；
   - 所有仓库绑定到第一个启用工厂。
3. 上线后由管理员按实际情况收窄。

### 6.2 灰度策略

数据权限一旦开启，未配置的用户会立刻看不到数据。上线前必须执行：

```bash
php artisan mes:data-scope:report
```

命令输出未配置 `factory_id` 的用户、`line` 范围但没有产线的用户、未绑定工厂的仓库，作为上线检查清单。

**不建议**提供"默认 all"的开关：这会让灰度期变成永久后门。若必须灰度，用配置项按角色逐步启用，并设定移除时间。

### 6.3 回滚

- [ ] 代码回滚即可恢复原行为（过滤逻辑随代码走）。
- [ ] 新增列可保留，不影响旧代码。
- [ ] 库存预警本期未改结构，回滚不涉及预警数据。

## 7. 验收标准

### 7.1 读取

- [ ] `line` 用户请求 `production.orders.index`，只返回 `production_line_id ∈ 本人产线` 的订单。
- [ ] `factory` 用户请求 `production.orders.index`，只返回本工厂产线下的订单。
- [ ] `all` 用户（超管）返回全部。
- [ ] 无 `data.scope.*` 权限的用户调用任何列表接口返回 403 或空集，且不得返回全量。
- [ ] `line` 用户用其他产线的 SN 请求 `production.sns.show`，返回 403。
- [ ] `line` 用户请求 `dashboard`，今日产量、质量汇总、设备状态、最新列表均只含本产线数据。
- [ ] `line` 用户请求设备历史，只能查到本产线设备的数据点。
- [ ] `line` 用户请求库存列表，只能看到本工厂仓库的库存。
- [ ] 库存预警保持全局口径：`factory` 用户看到的预警数字包含全部工厂仓库，属于已确认的例外，不作为缺陷。
- [ ] 正向追溯：其他工厂的 SN 返回 404。
- [ ] 反向追溯：其他工厂的物料批次使用记录不出现在结果中。

### 7.2 写入

- [ ] `line` 用户创建订单时传其他产线 `production_line_id`，返回 403。
- [ ] `line` 用户对范围外任务报工，返回 403。
- [ ] `line` 用户对范围外订单创建领料单，返回 403。
- [ ] `factory` 用户调整其他工厂仓库库存，返回 403。
- [ ] 用户管理：`factory` 用户不能修改其他工厂用户。
- [ ] 创建生产异常时 `production_order_id` 与 `process_record_id` 同时为空，返回 422（已确认：至少关联其一）。

### 7.3 兼容

- [ ] `GET /api/v1/me` 响应结构与字段值不变。
- [ ] 超管所有接口行为不变。
- [ ] MQTT 消费、`ScanInventoryAlerts`、`PruneEquipmentData` 正常运行。
- [ ] 现有全部 Feature Test 通过（需为测试用户补 `factory_id` 与产线绑定）。

### 7.4 测试组织

新增 `tests/Feature/Api/DataScopeTest.php`，用 Pest dataset 覆盖三种范围，至少包含：

- 每个业务模块一个列表用例（生产订单、任务、报工、工序、检验、不良品、领料、库存、设备、追溯）；
- 一个详情越权用例；
- 一个写入越权用例；
- 一个 `none` 范围用例。

## 8. 实施拆分

| 阶段 | 内容 | 产出 |
| --- | --- | --- |
| S1 | 迁移 + 归属模型 + 范围解析对象 | 数据模型可配置，`resolveDataScope()` 有单测 |
| S2 | 模型 `inDataScope` + 生产/质量/物料只读过滤 | M1 里程碑 |
| S3 | Dashboard + 库存 + 设备 + 追溯过滤 | 只读全覆盖 |
| S4 | `DataScopeGuard` + 写操作校验 | M3 里程碑 |
| S5 | 迁移命令 + 报告命令 + 文档 | 可上线 |

S4 建议与 02 文档的日志改造合并，因为触及同一批 Service 方法。

## 9. 风险与已确认决策

### 9.1 已确认决策

| 编号 | 问题 | 结论 | 落地方式 |
| --- | --- | --- | --- |
| D1 | 生产异常是否允许不关联订单/工序 | **不允许** | 创建时强制至少关联其一，否则 422；历史无归属记录仅 `all` 范围可见 |
| D2 | 库存预警是否按工厂拆分 | **本期不改造**，保持全局聚合 | 作为已知例外记录；多工厂实际运行且影响补货时再立项 |
| D3 | 审计日志保留期 | **不少于 180 天** | 见 02 文档 §4.6 |

### 9.2 待确认

| 编号 | 问题 | 建议方案 | 不确认的后果 |
| --- | --- | --- | --- |
| Q1 | 基础数据是否按工厂隔离 | 不隔离，全局共享，另立 ADR | 若要求隔离，本需求的改动面会扩大到 Product / Material / Bom / Process / ProcessRoute 全链路 |
| Q2 | `mes_warehouses` 是否新增 `factory_id` | 新增 | 不新增则库存无法按工厂过滤，库存列表与出入库校验只能停留在 `all` 范围 |

### 9.3 风险

| 项 | 影响 | 缓解 |
| --- | --- | --- |
| 基础数据不隔离 | 多工厂场景下主数据可被跨工厂修改 | Q1 待确认；确认后另立 ADR，后续按需增加工厂级主数据 |
| 仓库加 `factory_id` 为破坏性变更 | 现有仓库需回填 | 回填脚本 + 上线检查 |
| `line` 用户未配置产线 | 登录后看不到数据 | 上线前用 `mes:data-scope:report` 检查 |
| 跨工厂模拟数据缺失 | 难以验证多工厂行为 | 在 Seeder 中增加第二个工厂与产线 |
