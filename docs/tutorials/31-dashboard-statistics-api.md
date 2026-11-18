# 31 驾驶舱统计接口

**目标**：把驾驶舱需要的统计口径统一放在后端，保证“今日、趋势、产线、质量、设备、最新列表”都来自同一套有效数据规则。

## 1. 接口

```text
GET /api/v1/dashboard/today
GET /api/v1/dashboard/production-trend
GET /api/v1/dashboard/production-lines
GET /api/v1/dashboard/quality
GET /api/v1/dashboard/equipment-status
GET /api/v1/dashboard/latest-orders
GET /api/v1/dashboard/latest-reports
GET /api/v1/dashboard/latest-exceptions
GET /api/v1/dashboard/latest-alarms
```

每个接口有独立权限：`dashboard.overview`、`dashboard.trend`、`dashboard.quality`、`dashboard.equipment`。

## 2. 业务日和时区

应用默认存储 UTC，但业务日使用 `Asia/Shanghai`。`BusinessDay` 负责：

```php
$today = now(BusinessDay::timezone())->toDateString();
$start = BusinessDay::startUtc($today);
$end = BusinessDay::endUtc($today);
```

这样“今日”不会因为服务器时区而错位。

## 3. 有效业务数据

统计报工时使用：

```php
ProductionReport::query()->effective()
```

它排除已冲销报工。订单统计排除 `cancelled`，设备统计只算启用设备。

## 4. 今日指标

`today()` 返回：

```text
order_count                 今日创建的订单数
planned_quantity            今日订单计划数量
produced_quantity           今日有效报工数量
qualified_quantity          今日合格数
defective_quantity          今日不良数
running_equipment_count     当前运行设备数
```

数量使用字符串/千分位换算，避免浮点误差。

## 5. 趋势和产线

`productionTrend()`：

- 固定 7 天；
- 按上海业务日分组；
- 每天返回一个 `{date, quantity}`，没有报工补 0。

`productionLines()`：

- 取启用的产线；
- 汇总有效报工数量；
- 任务有产线时优先用任务产线，否则用订单产线。

## 6. 质量和设备

`quality()`：

- 合格率 = 合格数 / (合格数 + 不良数)；
- 不良原因按缺陷代码聚合；
- 没有报工时合格率为 0，不报除零错误。

`equipmentStatus()` 按五种设备状态补 0 计数。

## 7. 最新列表

`latestOrders()`、`latestReports()`、`latestExceptions()`、`latestAlarms()` 都按时间倒序取 5 条，并加载展示所需关联。

异常和告警列表按上报/触发时间倒序取最新 5 条，不额外过滤处理状态；状态由前端标签展示。

## 8. 测试

```bash
php artisan test --compact tests/Feature/Api/DashboardTest.php
```

重点：

- 冲销报工、取消订单不计入；
- 7 天时间按上海业务日切分；
- 无数据时返回 0 而不是 null；
- 每个驾驶舱权限独立校验；
- 最新列表只返回有效业务；
- Seeder 的时间分布让驾驶舱打开非空。

## 练习

冲销一条今天的报工，再请求：

```bash
curl http://mes.test/api/v1/dashboard/today -H 'Authorization: Bearer <token>'
```

观察实际产量、合格数、不良数是否同步减少。
