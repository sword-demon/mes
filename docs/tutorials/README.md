# MES MVP 分篇教程索引

本目录按 issue 拆分教程，每篇聚焦一个可独立验收的能力。

## 基础底座

| 教程 | 主题 |
| --- | --- |
| [总教程：Compose → Laravel API → React](../mes-mvp-tutorial.md) | 01–03：基础设施、后端骨架、前端请求层 |
| [RBAC 权限模型](../mes-rbac-tutorial.md) | 04：用户、角色、菜单与接口鉴权 |
| [05 动态菜单与按钮权限](05-dynamic-menu-and-button-permissions.md) | `/me` 菜单树、`Can`、403 |

## 主数据与工艺

| 教程 | 主题 |
| --- | --- |
| [06 工厂车间产线人员](06-organization-master-data.md) | 组织架构、父级过滤、删除保护 |
| [07 产品与版本](07-product-management.md) | 产品、版本、分类 |
| [08 物料管理](08-material-management.md) | 物料类型、库存阈值、批次管理 |
| [09 BOM 版本与审核锁定](09-bom-version-and-audit.md) | BOM 子项、审核、可用范围 |
| [10 工序与工艺路线](10-process-and-routing.md) | 工序、路线、审核、顺序 |

## 生产执行

| 教程 | 主题 |
| --- | --- |
| [11 生产订单状态机](11-production-order-state-machine.md) | 九态流转、版本快照 |
| [12 生产任务拆分](12-production-task-splitting.md) | 任务拆分、派工、进度 |
| [13 生产派工](13-production-dispatch.md) | 工序派给人员、重复校验 |
| [14 生产报工](14-production-report.md) | 数量校验、进度累计、冲销 |
| [15 工序流转](15-process-flow.md) | 顺序、检验卡口、返工 |
| [16 SN 条码管理](16-sn-management.md) | SN 生成、绑定、打印、作废 |

## 质量、物料与设备

| 教程 | 主题 |
| --- | --- |
| [17 检验项目、标准与缺陷代码](17-quality-standards-and-defect-codes.md) | 检验标准、缺陷库 |
| [18 检验任务与自动判定](18-inspection-tasks-and-auto-judgement.md) | IPQC/OQC、自动判定、图片 |
| [19 不合格品处理](19-defective-products.md) | 返工、复检、报废 |
| [20 库存出入库与流水](20-inventory-and-ledger.md) | 库存、可用量、流水 |
| [21 库存预警](21-inventory-alerts.md) | 定时扫描、确认、关闭 |
| [22 领料与发料](22-material-requisition-and-issue.md) | BOM 建议量、发料扣库存 |
| [23 退料](23-material-return.md) | 可退量、审核回库 |
| [24 设备台账](24-equipment-ledger.md) | 设备状态、产线归属 |
| [25 MQTT 模拟数据与消费](25-equipment-mqtt.md) | 主题、校验、落库 |
| [26 设备监控与历史曲线](26-equipment-monitoring-and-history.md) | 实时监控、降采样 |
| [27 设备告警闭环](27-equipment-alarms.md) | 阈值、等级、确认/处理/关闭 |

## 异常、追溯与看板

| 教程 | 主题 |
| --- | --- |
| [28 生产异常闭环](28-production-exceptions.md) | 上报、图片、处理、关闭 |
| [29 产品正向追溯](29-forward-traceability.md) | SN → 订单/工序/检验/物料 |
| [30 物料反向追溯](30-backward-traceability.md) | 批次 → 订单/批次/SN |
| [31 驾驶舱统计接口](31-dashboard-statistics-api.md) | 统计口径、业务日、有效报工 |
| [32 驾驶舱前端页面](32-dashboard-frontend.md) | 卡片、ECharts、最新列表 |

## 系统与交付

| 教程 | 主题 |
| --- | --- |
| [33 操作日志与登录日志](33-audit-logs.md) | 审计、变更摘要、查询 |
| [34 全量演示数据与一致性自检](34-demo-seeders-and-consistency.md) | Seeder 顺序、体量、六条自检 |
| [35 移动端骨架](35-mobile-skeleton.md) | UniApp、请求层、登录态 |
| [36 OpenAPI 接口文档](36-openapi-documentation.md) | 自动生成、Swagger、环境开关 |
| [37 生产编排与部署](37-production-deployment.md) | Compose、Nginx、systemd |

## 建议学习顺序

1. 先读总教程和 RBAC 教程；
2. 按 06–10 掌握主数据与工艺；
3. 按 11–16 走通生产执行；
4. 按 17–27 补质量、物料、设备；
5. 按 28–32 串联异常、追溯和看板；
6. 最后读 33–37 了解审计与交付。

每篇末尾都有“练习”或“验收标准”，建议在本地用 curl 或页面亲手验证一次。
