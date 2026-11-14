# MES 制造执行管理系统（MVP）— 实施 Spec

Status: ready-for-agent

Source: `docs/prd.md`（MES MVP PRD V1.0，含第十七章 docker-compose 编排与第十八章 Seeder 规范）

## Problem Statement

作为一个准备把 MES 项目写进简历的个人开发者，我需要一份可以直接交给自动化 Agent 执行的实施规格。当前手里只有一份 PRD，它描述了 MES 制造执行系统的业务全景（10 个模块、40+ 张业务表、11 类 API、生产报工/工序流转/质量检验/库存/设备 MQTT/产品追溯等完整闭环），但缺少工程落地的关键决策：

- **运行环境不清晰**：宿主机已经跑着 MySQL、Redis、Nginx，以及其它项目的容器；常用端口（80/3306/6379/8000/5173）全部被占用。Docker 编排必须避开这些端口，否则 `docker compose up` 直接端口冲突起不来。
- **后端跑在哪里不清晰**：本机已装 Herd，PHP 8.4 由 Herd 管理，再把 Laravel 塞进 php-fpm 容器属于重复造轮子，反而要处理容器到宿主机、跨域、Nginx 双层反代等无谓复杂度。
- **模拟数据从哪来不清晰**：项目不接真实 ERP/WMS/PLC，如果每次演示都要手工造数据，PRD 承诺的「驾驶舱近七天趋势」「设备状态统计」全是空的，演示效果无从谈起。
- **没有可执行的边界**：没有 spec，Agent 无法判断哪些该做、哪些不做、做到什么程度算完成，也无从判断改动是否破坏了 PRD 里写死的业务规则。

## Solution

交付一个可运行的 MES MVP：后端为 Laravel 13 **纯 API**（无页面渲染、无 Inertia/Blade），搭配 MySQL 8 + Redis（缓存/队列）+ EMQX（MQTT）；前端为 React 19 + TypeScript + Vite + Ant Design + Zustand + ECharts 的独立管理端工程，覆盖 PRD 的 11 个功能模块与 5 条核心业务闭环。

工程落地的三个关键决策：

1. **后端留在宿主机 Herd 且只做纯 API**：Laravel 应用通过 `mes.test` 域名提供 `/api/v1` JSON 接口，不渲染任何页面；前端是独立工程，通过 Vite 代理或构建产物托管访问该接口。Queue Worker、Scheduler、MQTT Consumer 以 Herd CLI 进程常驻运行。
2. **docker-compose 只编排中间件与设备模拟器**：MySQL、Redis、EMQX、设备数据模拟发布器进容器；端口统一使用高位段（MySQL 13307、Redis 16379、MQTT 11883/11888、Dashboard 18083），与宿主机既有服务零冲突。数据库提供「编排内自建」与「复用宿主机已有 MySQL」两种方案，仅通过 `.env` 切换。
3. **模拟数据全部由 Laravel 自带 database 产出**：按业务模块拆分的 Seeder + Factory，幂等可重复执行、业务自洽（报工合格数+不良数=报工数、订单已生产数=下属报工之和、库存流水净额=库存现值、SN 全局唯一），并覆盖 PRD 要求的全量业务状态。

## User Stories

### 环境与运行

1. As a developer, I want 一条命令拉起 MySQL、Redis、EMQX 与设备模拟器, so that 我不需要手工准备中间件就能开工。
2. As a developer, I want 编排使用的宿主机端口与既有服务完全错开, so that 我机器上其它项目照常运行，端口冲突不会打断我。
3. As a developer, I want 在宿主机用 `mes.test` 直接访问后端 API, so that 我不需要为本地环境配置 hosts、端口转发或跨域白名单。
4. As a developer, I want 前端页面与 API 同域（或经 Vite 代理转发）, so that 登录态、Cookie、相对路径请求都能开箱即用。
5. As a developer, I want 通过 `.env` 一处切换「编排内 MySQL」与「宿主机已有 MySQL」, so that 我既能自包含地演示，也能接入我现有的数据库实例。
6. As a developer, I want 常驻进程（队列、调度、MQTT 消费）能用普通 artisan 命令启动, so that 我能在同一个终端里看到它们的日志。
7. As a developer, I want 设备模拟数据以真实 MQTT 消息的形式进入系统, so that 我的 MQTT 消费逻辑是按真实协议验证的，而不是造假数据。
8. As a developer, I want 编排文件区分开发与生产两套, so that 生产环境能关掉调试、关掉 Seeder 自动执行。
9. As a developer, I want 数据库数据卷持久化, so that 容器重建不会丢我调试到一半的数据。
10. As a developer, I want 一次命令重置数据库并重新填充模拟数据, so that 我可以反复从同一个确定起点演示。

### 模拟数据

11. As a developer, I want 预置 1 个工厂、2 个车间、3 条产线、10 名员工、5 台设备、10 种物料、3 种产品、3 条工艺路线, so that 基础数据模块一打开就有内容。
12. As a developer, I want 预置 8 个业务角色与对应用户, so that 权限模块能立刻演示不同角色看到的不同菜单。
13. As a developer, I want 预置 20 条生产订单、30 条生产任务、100 条报工记录、50 条检验记录、30 个物料批次、50 个 SN、20 条设备告警, so that 列表页分页与统计图都有真实体量的数据。
14. As a developer, I want 模拟数据覆盖订单的全部状态（草稿到已关闭，含已暂停、已取消）, so that 我能一眼看出每种状态在 UI 上长什么样。
15. As a developer, I want 模拟数据覆盖设备全部状态（运行、待机、故障、维修、离线）, so that 设备监控页与状态统计图不是单一颜色。
16. As a developer, I want 模拟数据覆盖不合格品全流程状态（待处理到已关闭）, so that 不良品看板能展示完整处理链路。
17. As a developer, I want 报工数据满足合格数 + 不良数 = 报工数, so that 我在演示报工统计时不会出现对不上的数字。
18. As a developer, I want 订单的已生产数量等于其下所有报工之和, so that 生产进度条与实际报工记录始终一致。
19. As a developer, I want SN 全局唯一且都能反查到生产批次, so that 扫码追溯演示不会断链。
20. As a developer, I want 时间数据用相对时间生成（最近 30 天，含今日）, so that 看板的「近七天趋势」不会因为日期推移而变空。
21. As a developer, I want Seeder 可以重复执行而不产生重复数据, so that 我随时能补跑某个模块的 Seeder。
22. As a developer, I want Seeder 内部自带一致性自检，不一致就报错终止, so that 脏数据不会静默进入演示环境。
23. As a developer, I want 设备处于维修/离线时不产生产量增长的历史数据, so that 设备曲线不会出现自相矛盾的趋势。

### 系统管理与权限

24. As a 系统管理员, I want 用用户名密码登录后拿到 Token, so that 后续请求都带身份凭证。
25. As a 系统管理员, I want 登录后能看到自己的角色、权限与菜单, so that 我清楚当前账号能做什么。
26. As a 系统管理员, I want 管理用户的新增、编辑、停用、重置密码, so that 我能维护账号生命周期。
27. As a 系统管理员, I want 给用户分配角色, so that 权限可以按岗位批量授予。
28. As a 系统管理员, I want 管理角色的新增、编辑、删除与启停, so that 我能按组织调整授权范围。
29. As a 系统管理员, I want 为角色勾选菜单、页面、按钮、接口四类权限, so that 授权粒度足够细。
30. As a 系统管理员, I want 前端菜单按当前用户权限动态生成, so that 用户只看到与自己相关的入口。
31. As a 系统管理员, I want 未授权的 API 请求被后端拒绝, so that 前端隐藏按钮不是唯一防线。
32. As a 系统管理员, I want 关键操作写入操作日志, so that 出问题时能查到谁在什么时候做了什么。
33. As a 系统管理员, I want 停用的用户立即无法登录, so that 离职人员账号能立刻失效。

### 基础数据

34. As a 基础数据管理员, I want 维护工厂信息（编码、名称、地址、联系人）, so that 业务数据能归属到具体厂区。
35. As a 基础数据管理员, I want 维护车间并挂到工厂下, so that 组织结构是层级化的。
36. As a 基础数据管理员, I want 维护生产线并挂到车间下，带生产能力与负责人, so that 产线产能有据可查。
37. As a 基础数据管理员, I want 维护班组与员工（工号、部门、技能等级）, so that 派工时能选到人。
38. As a 基础数据管理员, I want 编码唯一且重复时给出明确报错, so that 我不会造出两条同编码的产线。
39. As a 基础数据管理员, I want 已产生业务记录的基础数据只能停用不能物理删除, so that 历史单据不会变成孤儿数据。
40. As a 基础数据管理员, I want 停用的基础数据不出现在新业务的选下拉里, so that 没人能选到已停用的产线。

### 产品与物料

41. As a 产品工程师, I want 维护产品（编码、型号、规格、分类、版本、单位）, so that 订单可以引用产品。
42. As a 产品工程师, I want 同一产品维护多个版本, so that 产品迭代有据可循。
43. As a 工艺工程师, I want 维护物料并区分原材料/半成品/成品/辅助材料/包装材料, so that 物料用途清晰。
44. As a 工艺工程师, I want 为物料设置最低库存与安全库存, so that 库存预警能算出来。
45. As a 工艺工程师, I want 为物料标记是否批次管理, so that 需要追溯的物料才走批次流程。
46. As a 工艺工程师, I want 为产品创建 BOM 并维护子项（用量、损耗率）, so that 订单能算出物料需求。
47. As a 工艺工程师, I want 一个产品有多个 BOM 版本, so that 设计变更不影响历史订单。
48. As a 工艺工程师, I want BOM 支持审核, so that 未审核的 BOM 不能被订单误用。
49. As a 工艺工程师, I want 创建订单时锁定 BOM 版本, so that 事后改 BOM 不会篡改历史订单用料。

### 工艺路线

50. As a 工艺工程师, I want 维护工序（编码、类型、标准工时、是否需检）, so that 路线可以由工序拼装。
51. As a 工艺工程师, I want 为产品创建工艺路线并给工序排序, so that 生产有明确的执行顺序。
52. As a 工艺工程师, I want 为路线上的每道工序设置标准工时与参数, so that 产能可以测算。
53. As a 工艺工程师, I want 工艺路线需要审核才能使用, so that 未验证的路线不会被下达到产线。
54. As a 工艺工程师, I want 订单锁定工艺路线版本, so that 路线调整不影响在制订单。
55. As a 工艺工程师, I want 系统不允许跳过必经工序, so that 不会出现漏检的产品流下去。

### 生产管理

56. As a 生产经理, I want 创建生产订单（产品、产线、计划数量、计划时间）, so that 生产有源头单据。
57. As a 生产经理, I want 提交订单审核, so that 未经确认的计划不会占用产能。
58. As a 生产经理, I want 审核通过后下达订单, so that 订单进入可执行状态。
59. As a 生产经理, I want 暂停与取消订单, so that 计划变更能如实反映。
60. As a 生产经理, I want 订单完成后关闭并锁定, so that 完成单据不再被改动。
61. As a 生产经理, I want 从订单拆分出生产任务并分配产线与班组, so that 大批量订单能被拆解执行。
62. As a 车间主任, I want 创建派工单把任务分派到具体人员与工序, so that 操作员知道自己干什么。
63. As a 车间主任, I want 查看派工记录, so that 我能追溯谁接了哪道工序。
64. As a 生产经理, I want 按状态、产品、产线、日期筛选订单列表, so that 我能快速定位问题单据。

### 生产执行

65. As a 生产操作员, I want 对已下达的任务提交报工（合格数、不良数）, so that 产量被真实记录。
66. As a 生产操作员, I want 批量报工, so that 一个批次能一次性录入。
67. As a 生产操作员, I want 系统拒绝超过任务剩余数量的报工, so that 产量不会虚增。
68. As a 生产操作员, I want 系统校验合格数 + 不良数 = 报工数, so that 数据自洽。
69. As a 生产操作员, I want 报工自动更新任务进度与订单累计产量, so that 我不用再手工统计。
70. As a 生产操作员, I want 已提交的报工不能删除，只能冲销, so that 审计链完整。
71. As a 生产操作员, I want 报工必须关联实际作业人员, so that 追溯能落到人。
72. As a 车间主任, I want 按工序开始/完成/提交/接收, so that 工序流转有节奏控制。
73. As a 车间主任, I want 上道工序未完成时无法启动下道工序, so that 工序顺序被强制遵守。
74. As a 质检员, I want 需要检验的工序必须检验通过才能流转, so that 不合格品不会流到下一站。
75. As a 车间主任, I want 工序异常与返工必须填写原因, so that 质量问题可归因。
76. As a 仓库/操作员, I want SN 自动生成且全局唯一, so that 单个产品可被精确追踪。
77. As a 仓库/操作员, I want SN 支持查询、打印标签、绑定与作废, so that 实物与系统对得上。
78. As a 车间主任, I want 上报生产异常（设备/物料/工艺/质量/人员）并附图片, so that 异常有据可查且可闭环。
79. As a 车间主任, I want 异常从上报到处理到关闭有状态流转, so that 异常不会烂尾。

### 质量管理

80. As a 质检主管, I want 维护检验项目与标准值/上下限, so that 判定有客观依据。
81. As a 质检主管, I want 维护缺陷代码库, so that 不良原因可统计可分析。
82. As a 质检员, I want 创建 IPQC/OQC 检验任务并关联订单与工序, so that 检验有上下文。
83. As a 质检员, I want 录入检验项目的实测值, so that 系统能自动判定合格与否。
84. As a 质检员, I want 上传检验图片作为证据, so that 判定可复核。
85. As a 质检主管, I want 查询检验记录, so that 质量趋势可分析。
86. As a 质检员, I want 不良品登记并记录不良原因与数量, so that 不良可追溯到批次。
87. As a 质检主管, I want 对不良品做返工/报废处理, so that 不良品有归宿。
88. As a 质检员, I want 返工后复检再关闭, so that 未复检的不良不能直接结案。

### 物料与库存

89. As a 仓库管理员, I want 查询库存（仓库、库位、批次、当前/可用/冻结）, so that 我知道实际有多少料。
90. As a 仓库管理员, I want 做入库、出库与库存调整, so that 账面与实物可以对齐。
91. As a 仓库管理员, I want 每一次库存变化都产生流水, so that 差异可追责。
92. As a 仓库管理员, I want 库存低于最低库存时预警, so that 我能提前补料。
93. As a 生产人员, I want 创建领料单并关联生产订单, so that 用料有依据。
94. As a 仓库管理员, I want 发料时自动扣减可用库存, so that 不需要手工维护账目。
95. As a 仓库管理员, I want 拒绝超出可用库存的领料, so that 库存不会被领成负数。
96. As a 生产人员, I want 创建退料单退回未用完的物料, so that 剩余料能回到库存。
97. As a 仓库管理员, I want 拒绝超过实际领料量的退料, so that 退料不会虚增库存。
98. As a 仓库管理员, I want 库存变更在事务中完成, so that 失败时不会出现流水与库存不一致。

### 设备管理

99. As a 设备管理员, I want 维护设备台账（编码、类型、车间、产线、型号、负责人）, so that 设备有档案。
100. As a 设备管理员, I want 看到设备实时状态（运行/待机/故障/维修/离线）, so that 我知道产线健康度。
101. As a 设备管理员, I want 通过 MQTT 消费模拟设备数据并存入历史表, so that 设备曲线有数据来源。
102. As a 设备管理员, I want 设备数据包含温度、转速、压力、状态、产量、运行时间, so that 监控维度够用。
103. As a 设备管理员, I want 指标超阈值时自动生成告警, so that 异常能被及时发现。
104. As a 设备管理员, I want 告警可确认、处理、关闭并分等级, so that 告警有闭环。
105. As a 设备管理员, I want 按设备查询历史数据曲线, so that 我能分析趋势与定位故障。

### 追溯

106. As a 质量经理, I want 输入 SN 查出完整生产历史（订单、批次、BOM 版本、用料、工艺路线、工序、人员、设备、检验、不良处理）, so that 单个产品的全链路可还原。
107. As a 质量经理, I want 用时间轴/流程图展示产品生产全过程, so that 追溯结果一眼能读懂。
108. As a 质量经理, I want 用物料批次反查生产出的所有产品 SN, so that 出现物料问题时能快速圈定影响范围。

### 看板与可视化

109. As a 厂长, I want 首页看到今日订单数、计划数、实际数、合格数、不良数、运行设备数, so that 我一进系统就知道今天产线状况。
110. As a 厂长, I want 近七天生产数量趋势图, so that 我能看出产能节奏。
111. As a 厂长, I want 各产线生产数量对比图, so that 我能发现低效产线。
112. As a 厂长, I want 产品合格率统计与不良原因分布图, so that 质量短板可见。
113. As a 厂长, I want 设备运行状态统计图, so that 设备停机情况一目了然。
114. As a 厂长, I want 最新订单/报工/异常/告警四个最新列表, so that 我能快速进入要处理的事。

### 交付形态

115. As a 面试者, I want 一个能一键复现的开发环境脚本, so that 我在面试现场不会因环境问题卡住。
116. As a 面试者, I want 演示时的数据是确定性的、可重置的, so that 每次演示效果一致。
117. As a 面试者, I want API 文档（Swagger/OpenAPI）自动生成, so that 我能展示规范化的一面。

## Implementation Decisions

### 运行环境与编排

- **后端不进容器，且是纯 API 后端**：PHP 8.4 由宿主机 Herd 管理，Laravel 应用通过 `mes.test` 域名对外提供 `/api/v1` JSON 接口；不注册 web 路由、不渲染 Blade 视图、不使用 Inertia 页面组件。前端是独立工程，构建产物由 Herd 托管，页面与 API 同源，统一请求 `http://mes.test/api/v1`，无需 CORS 配置（仅当使用独立 Vite 端口调试时，才在 Laravel 中放行本地开发地址到 CORS 白名单）。
- **仓库按「后端 / 前端」分目录**：`app/`、`routes/`、`database/`、`config/` 属于 Laravel；`frontend/web/`（管理端）与 `frontend/mobile/`（移动端骨架）为各自独立的 npm 工程，拥有独立 `package.json`、依赖与构建流程；`docker/` 存放中间件编排。Laravel 侧不感知前端存在。
- **前端 Vite 代理**：开发期 `frontend/web` 的 Vite dev server 将 `/api` 代理到 `http://mes.test`（而非 `http://localhost:8000`），避免开发服务器打到不存在的端口。
- **常驻进程以 CLI 形式运行**：Queue Worker（`queue:work`）、Scheduler（`schedule:work`）、MQTT Consumer（`mes:consume-mqtt`）均由宿主机 artisan 命令启动，不使用容器常驻。
- **docker-compose 只编排中间件与设备模拟器**：包含 MySQL 8、Redis 7、EMQX、模拟设备数据发布器；另提供一套生产编排文件用于服务器环境。
- **端口统一高位化，避开宿主机既有服务**：宿主机 80（nginx-arm/Herd）、3306（已有 MySQL，且另有 13306 映射）、6379（已有 Redis）、8000、5173 均被占用。编排内固定使用 MySQL 13307、Redis 16379、MQTT 11883、WebSocket 11888、EMQX Dashboard 18083。容器内端口保持服务默认（3306/6379/1883/8883/18083），仅宿主机侧映射高位。所有端口可通过环境变量覆盖。
- **容器网络互访约定**：容器之间使用 compose 服务名（mysql / redis / emqx）互访；宿主机侧应用使用 `127.0.0.1:<映射端口>`。若后端运行在容器内且使用宿主机 MySQL，则改用 `host.docker.internal`（macOS/Windows 原生支持，Linux 需配置 `extra_hosts`）。
- **数据库双方案**：
  - 方案 A（默认）：编排内自建 MySQL，宿主机映射 13307，Laravel 经 `127.0.0.1:13307` 连接。
  - 方案 B：复用宿主机已有 MySQL（`127.0.0.1:13306`），需预建 `mes` 库与账号，字符集 `utf8mb4`。
  - 两方案仅通过 `.env` 的 `DB_HOST`/`DB_PORT` 切换，业务代码零差异。
- **中间件持久化**：MySQL 数据卷持久化，容器重建不丢数据；生产环境关闭 APP_DEBUG、禁止把源码目录挂载进容器、不自动执行 Seeder。

### 应用配置

- 宿主机 `.env` 中固定：`APP_URL=http://mes.test`、`DB_CONNECTION=mysql`、`REDIS_HOST=127.0.0.1`、`REDIS_PORT=16379`、`MQTT_HOST=127.0.0.1`、`MQTT_PORT=11883`、`QUEUE_CONNECTION=redis`、`CACHE_STORE=redis`、`SESSION_DRIVER=redis`。
- `SEED_ON_BOOT` 决定初始化时是否填充模拟数据，开发默认 true，生产默认 false。

### 后端架构

- **模块化目录**：按业务领域划分 System、Organization、BasicData、Product、Process、Production、Quality、Material、Equipment、Traceability 十个模块，各自容纳 Controller / FormRequest / Service / Model / Resource。
- **分层约束**：Controller 只处理 HTTP；FormRequest 负责参数校验；Service 承载业务逻辑；Model 负责模型与关联；核心业务（报工、库存变更、订单状态流转）必须走数据库事务；复杂状态流转抽为独立的领域方法（状态机式），不散落在控制器分支里。
- **RBAC 权限模型**：菜单、页面、按钮、API 接口、数据五类权限；用户多角色；角色勾选权限；前端菜单按当前用户权限动态生成；后端每个受保护接口独立校验权限，不依赖前端隐藏按钮。
- **统一响应格式**：所有 API 返回 `{ code, message, data, timestamp }`，业务异常以对应 code + message 返回。
- **统一 API 前缀**：`/api/v1`，接口路径按 PRD 第七章定义（auth / system / products / materials / boms / routes / production / quality / inventory / equipments / traceability 等），列表接口支持分页与筛选。
- **审计与留痕**：关键操作写操作日志与登录日志；报工记录不可物理删除，只能冲销；基础数据与业务数据不物理删除，用状态位停用。
- **幂等与防重**：生产报工做重复提交防护；库存变更在事务内完成并同步写流水。

### 核心业务规则（来自 PRD，作为实现约束）

- 生产订单状态机：草稿 → 待审核 → 已审核 → 已下达 → 生产中 → 已完成 → 已关闭，另有已暂停、已取消两个补充状态。
- 报工规则：仅已下达任务可报工；报工量不得超过任务剩余量；合格数 + 不良数 = 报工数；必须关联操作人员；报工后自动更新任务进度；已提交记录只能冲销。
- 工序流转规则：必须按工艺路线执行；上道工序未完成不得执行下道；需检验的工序必须检验通过；返工必须记录原因。
- SN 规则：全局唯一；关联生产订单与生产批次；记录每道工序状态；可关联物料批次、设备与操作人员。
- BOM 规则：一个产品多个版本；审核通过后方可用于订单；订单创建时锁定 BOM 版本。
- 工艺路线规则：一个产品多条路线；审核后使用；订单创建时锁定版本；不允许跳过必经工序。
- 库存规则：领料不超过可用库存；退料不超过实际领料量；任何库存变化必须产生流水；库存操作保证事务一致性。
- 库存预警：当前库存低于最低库存时触发预警。
- 设备规则：MQTT 主题形如 `mes/{factory}/{device}/status|data|alarm`；消费端解析后保存历史数据、更新设备当前状态、超阈值生成告警。

### 模拟数据体系

- **全部由 Laravel 自带 database 产出**：使用 `database/seeders` + `database/factories`，不引入任何外部数据源或第三方造数服务。
- **Seeder 目录结构**：`DatabaseSeeder` 仅负责按业务依赖顺序调用各模块 Seeder：System → Organization → Product → Process → Production → Quality → Material → Equipment → Traceability。
- **模块归属**：SystemSeeder（用户/角色/权限/菜单/部门，8 角色约 20 用户）、OrganizationSeeder（1 工厂/2 车间/3 产线/班组/10 员工）、ProductSeeder（3 产品/10 物料/4 BOM 及子项，含同一产品的多版本与未审核版本）、ProcessSeeder（8 工序/3 路线及路线工序）、ProductionSeeder（20 订单/30 任务/派工/100 报工/批次/50 SN）、QualitySeeder（检验项目/标准/50 检验记录/不合格品）、MaterialSeeder（仓库/库位/30 物料批次/库存/流水）、EquipmentSeeder（5 设备/状态/历史数据/20 告警）、TraceabilitySeeder（产品物料绑定/生产历史/追溯关系，随生产数据联动生成）。
- **随机 vs 确定性**：随机数据走 Factory；确定性基础数据（角色、权限、字典、状态机取值）直接写在 Seeder 中。
- **幂等**：重复执行不产生重复数据、不因主键冲突报错。
- **一致性自检（Seeder 执行后校验，不通过即抛异常终止）**：
  1. 每条报工满足 合格数 + 不良数 = 报工数；
  2. 订单已生产数量 = 其下所有报工记录数量之和；
  3. 报工数量 ≤ 所属任务计划数量；
  4. 库存流水净变动 = 库存表当前值；
  5. SN 全局唯一且关联的生产批次存在；
  6. 设备处于维修/离线状态时不产生产量增长类历史数据。
- **状态覆盖**：生产订单 9 种状态、生产任务 4 种状态、报工 3 种（正常/批量/已冲销）、工序 7 种状态、不良品 4 种状态、检验记录合格/不合格、设备 5 种状态。
- **时间分布**：使用 Carbon 相对生成，分布在最近 30 天内并保证今日有足量订单与报工，保证看板「近七天趋势」与「今日统计」非空；禁止硬编码固定日期。

### MQTT 模拟设备数据

- 设备模拟发布器作为 compose 服务运行，按 Topic 规范向 EMQX 发布 status/data/alarm 三类消息。
- 消息体至少包含：设备编码、时间戳、状态、温度、转速、压力、产量。
- 状态按预设概率变化，偶发故障告警；状态为维修/离线时不发产量增长。
- Laravel 侧独立消费者进程订阅 → 解析 → 写设备历史表 → 更新设备当前状态 → 超阈值生成告警。

### 前端（React 管理端）

- 技术栈：React 19 + TypeScript + Vite + React Router + Ant Design + Zustand + Axios + ECharts + Day.js + @tanstack/react-query + QRCode。沿用 `laravel new` 自带的 React 脚手架（含 Tailwind 作为样式补充），UI 组件库用 Ant Design。
- 目录按视图域划分：dashboard / system / basic-data / process / production / quality / material / equipment / traceability，配套 api、stores、components、hooks、utils、types。
- 菜单结构与 PRD 第八章一致；菜单与按钮按权限动态渲染。
- 图表统一用 ECharts；看板四类图表（近七天趋势、产线对比、合格率、不良原因分布）与设备状态统计。
- 移动端目录 `frontend/mobile` 采用 UniApp（uView Plus），本期只建骨架（登录、报工、SN 扫码三页的目录与路由），完整移动端功能留待 V1.1。

### 移动端

- 位置 `frontend/mobile/`，独立 npm 工程（UniApp + uView Plus），独立构建产物。
- 本期范围仅骨架：页面目录、路由、请求层与登录态打通，不实现业务功能。

### 交付顺序

按 PRD 第十二章八阶段推进：初始化与权限 → 基础数据/产品/物料/BOM → 工艺路线/订单/任务 → 派工/报工/工序流转 → 质量/库存 → 设备与模拟 MQTT → 追溯与看板 → 测试优化与部署。每一阶段须可独立演示。

## Testing Decisions

### 测什么、怎么测

- **只测外部可观察行为**：断言 HTTP 响应（状态码、响应体结构与字段值）、数据库最终状态、以及队列/事件产生的外部结果。不测私有方法内部实现，不测具体 SQL 写法，不 mock 掉被测业务本身。
- **主缝为 HTTP API 层**：绝大多数行为从 `/api/v1/*` 端点进入，用真实请求走完整中间件、鉴权、FormRequest、Service、事务、Model 链路。这是最高的可用缝，能同时覆盖鉴权、校验与业务规则。
- **纯逻辑补充单测**：状态机流转规则、SN 生成规则、数量校验（合格+不良=报工）等无 IO 的判定放在 Unit 层直接测，避免为它们绕一圈 HTTP。

### 分层策略

- **Feature（API 特性测试）**：覆盖登录与鉴权、RBAC 菜单/接口权限、基础数据 CRUD 与唯一性约束、产品/BOM/工艺路线的创建与审核锁定、生产订单完整状态流转、生产任务与派工、报工（数量上限、合格+不良=报工、自动更新进度、冲销）、工序流转（顺序强制、检验卡口、返工原因）、SN 生成与作废、异常上报闭环、检验任务与自动判定、不良品处理闭环、库存入库出库调整、领料退料与库存上限、库存流水与预警、MQTT 消费后设备状态/历史/告警更新、正向与反向追溯、看板统计接口。
- **Seeder 一致性测试（新增，明确要做）**：
  - 执行完整 Seeder 后断言一致性自检的 6 条规则全部成立（报工数量平衡、订单累计量、任务上限、库存流水净额、SN 唯一且批次可查、设备状态与产量不矛盾）；
  - 断言状态覆盖：订单/任务/报工/工序/不良品/设备各自的每种状态在库中都至少有 1 条记录；
  - 断言数据量达到 PRD 约定的量级下限（20 订单 / 30 任务 / 100 报工 / 50 检验 / 30 物料批次 / 50 SN / 20 告警）；
  - 断言幂等：连续执行两次 Seeder，各表行数不增长且不抛异常；
  - 断言时间分布：最近 7 天内存在报工记录，且今日存在订单与报工记录。
- **Unit**：订单状态机允许/拒绝的转移、SN 编码格式与唯一性、报工数量校验函数等纯判定。

### 工程约定

- 使用项目既有的 Pest 测试风格与 `RefreshDatabase`（Feature 套件已绑定），数据库为 SQLite 内存库，不依赖 compose 中的 MySQL。
- 既有先例：项目当前只有 Laravel 默认的 ExampleTest（Feature 与 Unit 各一个），无更丰富的先例可循；新测试应遵循 Pest 风格、`expect()` 断言，测试文件按被测模块分文件。
- MQTT 消费逻辑以「给定一条消息 → 断言设备历史、当前状态、告警是否落库」的方式测，不做真实网络联调。
- 静态检查沿用项目既有脚本：Pint（格式）、Larastan/PHPStan（静态分析）、`npm run types:check`（前端类型）。

## Out of Scope

- 真实 ERP、WMS、PLC 对接。
- 复杂 APS 智能排程。
- AI 预测性维护。
- 财务成本核算。
- 多工厂集团级协同与多租户。
- 复杂工作流引擎。
- 移动端业务功能实现——PRD 技术选型里列了移动端，但本期只建 `frontend/mobile` 骨架（登录、报工、SN 扫码三页目录与请求层），移动端扫码报工完整功能留待 V1.1。
- OEE 设备综合效率分析、SPC 统计过程控制（分别在 V1.4 / V2.1）。
- 生产环境 Kubernetes / CI-CD 流水线建设。
- 把 Laravel 本身容器化（已明确由 Herd / 服务器 PHP-FPM 运行）。

## Further Notes

- 本 spec 的范围以 `docs/prd.md` 为准；PRD 是业务需求的唯一事实来源，若实现中发现 PRD 未覆盖的细节，按 PRD 章节编号回溯确认后再定。
- 端口选择基于当前宿主机实际占用情况（80/3306/6379/8000/5173 均被占用）。若日后宿主机情况变化，只需调整环境变量，不需要改编排结构。
- 前端技术栈已定稿：沿用 `laravel new` 自带的 React 19 + Vite + Tailwind 脚手架，UI 换成 Ant Design、状态用 Zustand、图表用 ECharts；PRD 第 2.1 技术选型表、第 8.1 前端技术栈表、第 8.3 前端目录结构已同步更新（Vue 3/Element Plus/Pinia → React/Ant Design/Zustand，目录视图层 views → pages，composables → hooks）。PRD 中「Element Plus」等组件名属实现细节，后续可按需微调措辞。
- 现有 `frontend/web` 脚手架里已有 `/login` 与 `/` 两个页面占位和指向 `localhost:8000` 的 Vite 代理，实施时需把代理目标改为 `http://mes.test`，并按 PRD 第八章重写路由与页面结构。
- Laravel 侧已具备的 API 基础可复用：`bootstrap/app.php` 已配置 `apiPrefix: 'api/v1'`、`throttleApi`、API 异常自动 JSON 渲染、访客重定向置空；`routes/api.php` 已有 `POST /auth/tokens`（登录发 Token）与 `GET /me`（当前用户信息）及 Sanctum 鉴权组。新的认证接口沿用现有 RESTful 风格 `auth/tokens`（POST 签发 Token、DELETE 吊销 Token）与 `GET /me`，PRD 第七章 7.1 已按此同步更新。
- 领域词汇以 PRD 为准：生产订单、生产任务、生产派工、生产报工、工序流转、SN、生产异常、检验任务、不良品、库存/流水、设备台账/状态/历史/告警、正向追溯、反向追溯、驾驶舱。代码中的模型与表命名沿用 PRD 第六章的 `mes_*` 与 `sys_*` 表名约定。
