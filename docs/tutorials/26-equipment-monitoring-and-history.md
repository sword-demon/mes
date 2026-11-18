# 26 设备状态监控与历史曲线

**目标**：展示每台设备的实时状态和关键指标，按时间区间查询历史曲线，并对密集数据做降采样。

## 1. 监控接口

```text
GET /api/v1/equipments/monitor
```

返回：

- 启用设备列表；
- 每台设备最新一条 `EquipmentData`；
- 状态计数；
- 历史查询上限。

只有拥有 `equipment.monitor.index` 权限的用户才加载 `latestData` 和状态聚合；普通查看设备列表的人不会拿到监控明细。

## 2. 历史接口

```text
GET /api/v1/equipments/{equipment}/history?start_at=...&end_at=...
```

限制：

- 必须同时提供开始和结束时间；
- 结束必须晚于开始；
- 最长 31 天；
- 最多返回 500 个点。

## 3. 降采样

`EquipmentService::history()` 按时间范围动态计算 bucket：

```text
bucketSeconds = ceil(rangeSeconds / 500)
```

每个 bucket 取：

- 温度/转速/压力平均值；
- 最新一条样本的 `recorded_at` 和 `output`。

SQL 用窗口函数实现，SQLite/PostgreSQL/MySQL 分别使用对应的时间戳表达式，保证测试和本地/生产一致。

## 4. 前端

`frontend/web/src/pages/equipment/monitor/index.tsx` 展示设备卡片和关键指标；
`frontend/web/src/pages/equipment/history/index.tsx` 用 ECharts 绘制温度、转速、压力、产量曲线。

图表选项在 `frontend/web/src/pages/equipment/metrics.ts` / 组件中统一维护。

## 5. 测试

```bash
php artisan test --compact tests/Feature/Api/EquipmentMonitorTest.php
```

验收：

- 监控返回启用设备及最新数据；
- 历史只返回 `message_type=data`；
- 时间区间正确过滤；
- 密集数据被降到 ≤ 500 点；
- 超过 31 天返回 422；
- 结束时间不晚于开始时间返回 422；
- 无权限时监控字段降级。

## 练习

为同一设备生成 1000 条一分钟采样，查询 24 小时历史，确认返回点数不超过 500，且曲线保留首尾时间范围。
