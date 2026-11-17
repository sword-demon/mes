# MES 制造执行管理系统

一套面向离散制造场景的轻量级 MES MVP，覆盖基础数据、BOM 与工艺路线、生产订单、任务派工、工序流转、生产报工、质量检验、物料库存、设备数据采集与产品追溯的完整闭环。

系统采用前后端分离架构：Laravel 提供 `/api/v1` RESTful API，React 管理端承载 42 个业务菜单页面，UniApp 移动端与 Web 共用同一套接口。当前移动端已完成登录接入，生产报工与 SN 扫码为 V1.1 占位页面。

## 技术栈

| 层级 | 技术 |
| --- | --- |
| 后端 | PHP 8.4、Laravel 13、Laravel Sanctum、Eloquent ORM |
| 数据库 | MySQL 8.4 |
| 缓存 / 会话 | Redis 7 |
| Web 管理端 | React 19、TypeScript、Vite 8、Ant Design 6、ECharts 6、React Router 7、Zustand、TanStack Query、Axios |
| 移动端 | UniApp、Vue 3、TypeScript、uView Plus |
| 设备接入 | MQTT、EMQX 5.8、php-mqtt/client |
| 接口文档 | OpenAPI / Swagger UI，入口为 `/api-docs` |
| 测试与质量 | Pest 5、PHPStan / Larastan、Laravel Pint、Vitest |
| 本地运行 | Laravel Herd（`mes.test`）、Docker Compose（MySQL / Redis / EMQX / 设备模拟器） |

## 功能模块

| 模块 | 页面数 | 当前能力 |
| --- | ---: | --- |
| 首页驾驶舱 | 4 | 今日订单、计划数、产量、合格数、不良数、运行设备；近 7 天趋势、产线对比、合格率、设备状态、不良原因；最新订单 / 报工 / 异常 / 告警 |
| 基础数据 | 9 | 工厂、车间、产线、班组、人员、产品与版本、物料、BOM 及明细、工序 |
| 工艺管理 | 1 | 工艺路线维护、路线工序配置、提交审核、审核通过 / 停用 |
| 生产管理 | 4 | 生产订单状态流转、任务拆分、派工、生产报工与报工冲销 |
| 生产执行 | 3 | 工序开始 / 完成 / 检验 / 流转 / 返工、产品 SN 生成与绑定、生产异常处理 |
| 质量管理 | 4 | 检验项目与缺陷代码、IPQC / OQC 检验任务、检验记录与图片、不良品返工 / 复检 / 报废 |
| 物料管理 | 6 | 库存查询与流水、入库、出库、库存调整、库存预警、领料、退料 |
| 设备管理 | 4 | 设备台账、MQTT 实时监控、告警确认 / 处理 / 关闭、历史数据与保留期清理 |
| 追溯管理 | 2 | 按 SN 正向追溯生产全过程；按物料批次反向追溯受影响订单、批次与 SN |
| 系统管理 | 5 | 用户、角色权限、菜单、部门、操作日志与登录日志 |

系统另有登录页与 403 无权限页。当前 Web 端合计 **42 个菜单页面**，均已接入后端接口与路由权限守卫。

## 页面预览

以下截图在本地 `http://localhost:15173` 逐页访问后采集，分辨率为 1600×1000。

![生产数据总览](docs/images/page-dashboard-overview.png)

> 首页驾驶舱：聚合今日生产指标、近七天趋势、各产线产量、产品合格率、设备状态与不良原因，并展示最新业务动态。

| 生产订单 | 工序流转 | 设备监控 |
| --- | --- | --- |
| ![生产订单](docs/images/page-production-orders.png) | ![工序流转](docs/images/page-execution-flows.png) | ![设备监控](docs/images/page-equipment-monitor.png) |
| 订单审核、下达、开工、暂停、完成与关闭 | 工序加工、检验、提交下道、接收与异常处理 | 设备状态、温度、转速、压力、累计产量与采样时间 |

## 全部页面截图

### 首页驾驶舱

| 生产数据总览 | 生产趋势 | 质量统计 | 设备状态 |
| --- | --- | --- | --- |
| ![生产数据总览](docs/images/page-dashboard-overview.png) | ![生产趋势](docs/images/page-dashboard-trend.png) | ![质量统计](docs/images/page-dashboard-quality.png) | ![设备状态](docs/images/page-dashboard-equipment.png) |

### 基础数据

| 工厂管理 | 车间管理 | 产线管理 |
| --- | --- | --- |
| ![工厂管理](docs/images/page-basic-data-factories.png) | ![车间管理](docs/images/page-basic-data-workshops.png) | ![产线管理](docs/images/page-basic-data-lines.png) |

| 班组管理 | 人员管理 | 产品管理 |
| --- | --- | --- |
| ![班组管理](docs/images/page-basic-data-teams.png) | ![人员管理](docs/images/page-basic-data-employees.png) | ![产品管理](docs/images/page-basic-data-products.png) |

| 物料管理 | BOM 管理 | 工序管理 |
| --- | --- | --- |
| ![物料管理](docs/images/page-basic-data-materials.png) | ![BOM 管理](docs/images/page-basic-data-boms.png) | ![工序管理](docs/images/page-basic-data-processes.png) |

### 工艺管理

| 工艺路线 |
| --- |
| ![工艺路线](docs/images/page-process-routes.png) |

### 生产管理

| 生产订单 | 生产任务 | 生产派工 | 生产报工 |
| --- | --- | --- | --- |
| ![生产订单](docs/images/page-production-orders.png) | ![生产任务](docs/images/page-production-tasks.png) | ![生产派工](docs/images/page-production-dispatch.png) | ![生产报工](docs/images/page-production-reports.png) |

### 生产执行

| 工序流转 | SN 管理 | 生产异常 |
| --- | --- | --- |
| ![工序流转](docs/images/page-execution-flows.png) | ![SN 管理](docs/images/page-execution-sn.png) | ![生产异常](docs/images/page-execution-exceptions.png) |

### 质量管理

| 检验标准 | 检验任务 | 检验记录 | 不良品管理 |
| --- | --- | --- | --- |
| ![检验标准](docs/images/page-quality-standards.png) | ![检验任务](docs/images/page-quality-tasks.png) | ![检验记录](docs/images/page-quality-records.png) | ![不良品管理](docs/images/page-quality-defects.png) |

### 物料管理

| 库存查询 | 库存预警 | 入库管理 |
| --- | --- | --- |
| ![库存查询](docs/images/page-material-inventory.png) | ![库存预警](docs/images/page-material-inventory-alerts.png) | ![入库管理](docs/images/page-material-inbound.png) |

| 出库管理 | 领料管理 | 退料管理 |
| --- | --- | --- |
| ![出库管理](docs/images/page-material-outbound.png) | ![领料管理](docs/images/page-material-requisitions.png) | ![退料管理](docs/images/page-material-returns.png) |

### 设备管理

| 设备台账 | 设备监控 | 设备告警 | 历史数据 |
| --- | --- | --- | --- |
| ![设备台账](docs/images/page-equipment-ledger.png) | ![设备监控](docs/images/page-equipment-monitor.png) | ![设备告警](docs/images/page-equipment-alarms.png) | ![历史数据](docs/images/page-equipment-history.png) |

### 追溯管理

| 产品追溯 | 物料追溯 |
| --- | --- |
| ![按 SN 正向追溯](docs/images/page-traceability-forward.png) | ![按物料批次反向追溯](docs/images/page-traceability-backward.png) |

### 系统管理

| 用户管理 | 角色管理 | 菜单管理 |
| --- | --- | --- |
| ![用户管理](docs/images/page-system-users.png) | ![角色管理](docs/images/page-system-roles.png) | ![菜单管理](docs/images/page-system-menus.png) |

| 部门管理 | 操作日志 |
| --- | --- |
| ![部门管理](docs/images/page-system-departments.png) | ![操作日志](docs/images/page-system-logs.png) |

### 入口与异常页

| 登录页 | 403 无权限页 |
| --- | --- |
| ![登录页](docs/images/page-login.png) | ![403 无权限页](docs/images/page-403.png) |

## 系统架构

```mermaid
flowchart TB
    subgraph Client["客户端"]
        Web["React 管理端<br/>React 19 · TypeScript · Ant Design 6<br/>ECharts · Zustand · TanStack Query"]
        Mobile["UniApp 移动端<br/>Vue 3 · TypeScript · uView Plus"]
    end

    subgraph Access["接入层"]
        Vite["Vite Dev Server :15173<br/>/api 代理"]
        Herd["Laravel Herd / Nginx<br/>mes.test"]
    end

    subgraph Backend["Laravel 13 · PHP 8.4"]
        Auth["Sanctum Token 认证<br/>用户状态校验 · RBAC · 数据范围"]
        API["RESTful API /api/v1<br/>Controller → FormRequest → Service → Model"]
        OpenAPI["OpenAPI / Swagger UI<br/>/api-docs"]
        Scheduler["Laravel Scheduler<br/>库存预警扫描 · 设备数据清理"]
    end

    subgraph Data["数据层"]
        MySQL[("MySQL 8.4<br/>业务数据")]
        Redis[("Redis 7<br/>Session / Cache / Queue")]
    end

    subgraph Device["设备数据链路"]
        Simulator["MQTT 设备模拟器"]
        EMQX["EMQX 5.8"]
        Consumer["mes:consume-mqtt<br/>设备消息消费"]
    end

    Web --> Vite
    Mobile --> Vite
    Vite -->|/api 代理| Herd
    Herd --> Auth
    Auth --> API
    API --> OpenAPI
    API --> MySQL
    API --> Redis
    Scheduler --> MySQL
    Simulator -->|状态 / 数据 / 告警| EMQX
    EMQX --> Consumer
    Consumer -->|EquipmentMessageService| API
```

架构要点：

- 后端按业务领域拆分为 `app/Modules/*`，每个模块包含 Controller、FormRequest、Service、Model、Resource；跨模型事务和状态流转集中在 Service 层。
- 所有接口统一使用 `/api/v1` 前缀，返回 `{ code, message, data, timestamp }` 响应结构；登录后使用 Sanctum Bearer Token 认证。
- 权限由“用户 → 角色 → 权限 → 菜单”驱动，并支持 `all / factory / line` 数据范围；前端根据 `/me` 返回的菜单渲染导航和路由守卫。
- MySQL 保存业务数据；Redis 承担 Session、Cache 与 Queue 基础设施；OpenAPI 文档与接口实现同源生成。
- 设备模拟器按 `mes/{factory}/{device}/{status|data|alarm}` 发布消息，`mes:consume-mqtt` 消费后写入设备历史、更新实时状态并评估告警。

## 核心业务流程

### 1. 生产订单状态机

```mermaid
stateDiagram-v2
    state "草稿" as Draft
    state "待审核" as Pending
    state "已审核" as Approved
    state "已下达" as Released
    state "生产中" as InProgress
    state "已暂停" as Paused
    state "已完成" as Completed
    state "已关闭" as Closed
    state "已取消" as Cancelled

    [*] --> Draft
    Draft --> Pending: 提交审核
    Pending --> Approved: 审核通过
    Approved --> Released: 下达
    Released --> InProgress: 开工
    InProgress --> Paused: 暂停
    Paused --> InProgress: 恢复
    InProgress --> Completed: 完成
    Completed --> Closed: 关闭
    Draft --> Cancelled: 取消
    Pending --> Cancelled: 取消
    Approved --> Cancelled: 取消
    Released --> Cancelled: 取消
    InProgress --> Cancelled: 取消
    Paused --> Cancelled: 取消
    Closed --> [*]
    Cancelled --> [*]
```

生产订单创建时会校验产品、BOM、工艺路线和产线状态，并把 BOM 版本与工艺路线版本固定在订单上，避免后续版本变更影响已下达订单。

### 2. 生产执行与工序流转

```mermaid
flowchart TB
    Order["生产订单已下达"] --> Split["按计划数量拆分生产任务"]
    Split --> Records["按工艺路线生成工序记录"]
    Records --> Dispatch["派工：产线 / 班组 / 负责人"]
    Dispatch --> Start["工序开始加工"]
    Start --> Complete["完成加工"]
    Complete --> Inspect{"工序是否需检验"}
    Inspect -->|否| Transfer["待流转"]
    Inspect -->|是| Wait["待检验"]
    Wait -->|检验通过| Inspected["检验通过"]
    Wait -->|检验不合格| Rework["返工 / 上报异常"]
    Rework --> Start
    Inspected -->|提交下道| Transfer
    Transfer -->|下道接收| Transferred["已流转"]
    Transferred -->|还有后续工序| Start
    Transferred -->|末道完成| Report["生产报工<br/>合格数 + 不良数"]
    Report --> Accumulate["累计任务 / 订单产量"]
    Accumulate --> Finish{"任务数量完成？"}
    Finish -->|是| Done["生产任务完成"]
    Finish -->|否| Start
```

报工必须满足“合格数 + 不良数 = 报工数”，且不能超过任务剩余数量；报工冲销会保留原记录并回滚任务与订单的累计数量。工序流转通过行锁保证上道工序完成、数量与 SN 校验在并发下仍然一致。

### 3. 物料与库存

```mermaid
flowchart LR
    Inbound["入库<br/>采购 / 退料 / 调整"] --> Balance["库存余额<br/>物料 + 库位 + 批次"]
    Balance --> Ledger["库存流水<br/>入库 / 出库 / 调整"]
    Order["生产订单"] --> Suggest["领料建议<br/>按 BOM 用量与损耗率"]
    Suggest --> Requisition["领料单"]
    Requisition --> Issue["发料<br/>库存出库 + 关联领料单"]
    Issue --> Ledger
    Issue --> Return["退料单"]
    Return --> Approve["审核退料"]
    Approve --> Inbound
    Balance --> Alerts["定时扫描<br/>低于物料安全库存"]
    Alerts --> Alert["库存预警<br/>待确认 / 已确认 / 关闭"]
```

- 库存按“物料 + 库位 + 批次”建账，入库、出库、调整都会生成不可变的库存流水。
- 领料建议以生产订单 BOM 用量为基准，发料时校验可用库存并写入出库流水。
- 退料单审核通过后回写库存；库存预警由 Scheduler 每 5 分钟扫描并自动创建或关闭。

### 4. 设备数据采集

```mermaid
flowchart LR
    Simulator["设备模拟器<br/>每 5 秒发布"] --> EMQX["EMQX 5.8"]
    EMQX --> Consumer["mes:consume-mqtt"]
    Consumer --> Validate["主题 / JSON / 时间戳 / 设备校验"]
    Validate --> Dedupe["按设备 + 消息类型 + 采集时间幂等去重"]
    Dedupe --> History["设备历史数据"]
    Dedupe --> Status["设备实时状态"]
    Dedupe --> Alarm["告警评估"]
    History --> Dashboard["设备监控 / 历史数据 / 驾驶舱"]
    Status --> Dashboard
    Alarm --> Dashboard
    Scheduler["Scheduler 03:10"] --> Prune["清理超过保留期的设备数据"]
```

### 5. 质量检验与追溯

```mermaid
flowchart LR
    subgraph Forward["正向追溯：SN → 生产全过程"]
        SN["产品 SN"] --> FOrder["生产订单<br/>BOM / 工艺路线"]
        FOrder --> FRecord["工序记录<br/>人员 / 班组 / 设备"]
        FRecord --> FInspect["检验记录 / 不良品"]
        FOrder --> FMaterial["领料实发数量"]
    end

    subgraph Backward["反向追溯：物料批次 → 产品"]
        Batch["物料批次"] --> BTx["库存出库流水"]
        BTx --> BReq["领料单"]
        BReq --> BOrder["生产订单"]
        BOrder --> BBatch["生产批次 / 产品 SN"]
    end
```

- IPQC 关联待检验工序，按检验项目和上下限自动判定；不合格会推动工序返工，并生成不良品记录。
- 不良品支持开始处理、返工、复检、报废，状态在 `待处理 → 处理中 → 待复检 → 关闭` 之间流转。
- 正向追溯从 SN 投影订单、工序、检验、不良品和 BOM 实发用量；反向追溯从物料批次找到出库流水、领料单、生产订单及受影响的产品批次 / SN。

## 目录结构

```text
app/
  Core/                    # 异常、OpenAPI、业务日期、通用服务
  Modules/
    Organization/          # 工厂、车间、产线、班组、人员
    Product/               # 产品与产品版本
    Bom/                   # BOM 与 BOM 明细
    Process/               # 工序、工艺路线、路线工序
    Production/            # 订单、任务、派工、报工、工序记录、SN、异常
    Quality/               # 检验标准、检验任务、检验记录、不良品
    Inventory/             # 库存、库存流水、库存预警
    Material/              # 领料、退料
    Equipment/             # 设备台账、设备数据、设备告警
    Dashboard/             # 驾驶舱聚合查询
    Traceability/          # 正向追溯、反向追溯
    System/                # 用户、角色、菜单、部门、日志、认证
  Console/Commands/        # MQTT 消费、库存扫描、设备数据清理、创建管理员
frontend/
  web/                     # React 管理端
  mobile/                  # UniApp 移动端
database/                  # migrations、seeders、factories
docker/                    # MySQL、Redis、EMQX、设备模拟器
routes/api.php             # /api/v1 路由与权限中间件
docs/                      # PRD、部署文档、页面截图
```

## 本地运行

### 环境要求

- PHP 8.4、Composer
- Node.js 20+、npm
- Docker（MySQL 8.4、Redis 7、EMQX 5.8）
- Laravel Herd（推荐，默认域名 `mes.test`）

### 启动步骤

```bash
# 1. 安装后端依赖并初始化环境
composer install
cp .env.example .env
php artisan key:generate

# 2. 启动 MySQL、Redis、EMQX 与设备模拟器
docker compose --env-file .env -f docker/docker-compose.yml up -d

# 3. 初始化数据库与演示数据
SEED_PASSWORD='你的演示密码' php artisan migrate --seed

# 4. 启动 Web 管理端（Vite 会把 /api 代理到 http://mes.test）
cd frontend/web
npm install
npm run dev
```

浏览器访问：

- Web 管理端：`http://localhost:15173`
- Laravel API：`http://mes.test/api/v1`
- OpenAPI 文档：`http://mes.test/api-docs`

### 设备数据与定时任务

```bash
# 订阅 EMQX 设备消息，写入设备历史、状态与告警
php artisan mes:consume-mqtt

# 本地运行 Scheduler（库存预警扫描、设备历史清理）
php artisan schedule:work
```

### 移动端

```bash
cd frontend/mobile
npm install
npm run dev:h5    # H5 开发，端口 15174
```

移动端说明见 [`frontend/mobile/README.md`](frontend/mobile/README.md)。

### 本地端口

| 服务 | 地址 |
| --- | --- |
| Web 管理端 | `http://localhost:15173` |
| 移动端 H5 | `http://localhost:15174` |
| Laravel 应用 | `http://mes.test` |
| MySQL | `127.0.0.1:13307` |
| Redis | `127.0.0.1:16379` |
| EMQX MQTT | `127.0.0.1:11883` |
| EMQX Dashboard | `http://localhost:18083` |

## 测试与质量

```bash
# 后端：Pint + PHPStan + Pest
composer test

# 仅运行后端测试
php artisan test --compact

# Web 端类型检查与单元测试
cd frontend/web
npm run types:check
npm test

# 移动端类型检查与单元测试
cd frontend/mobile
npm run typecheck
npm test
```

当前 Web 端 42 个菜单页面、登录页与 403 页面均已通过浏览器逐页访问并留存截图，截图文件位于 [`docs/images`](docs/images)。

## 文档

- [产品需求文档](docs/prd.md)
- [MES MVP 入门教程：从 Compose 到 Laravel API 与 React 管理端](docs/mes-mvp-tutorial.md)
- [MES RBAC 权限模型教程：用户、角色、菜单与接口鉴权](docs/mes-rbac-tutorial.md)
- [Docker 中间件编排](docs/docker-compose.md)
- [生产部署说明](docs/production-deployment.md)
- [已沉淀解决方案](docs/solutions)
- 本地 OpenAPI 文档：`http://mes.test/api-docs`

## 范围说明

当前版本定位为 MES MVP，已实现可完整演示的生产执行闭环。以下能力不在本期范围：真实 ERP / WMS / PLC 对接、APS 智能排程、AI 预测性维护、财务成本核算、多工厂集团协同、复杂工作流引擎。移动端生产报工与 SN 扫码将在 V1.1 接入现有 `/api/v1` 契约。
