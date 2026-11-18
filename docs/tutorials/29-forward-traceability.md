# 29 产品正向追溯

**目标**：输入产品 SN，查看从订单、BOM、路线、工序、人员、设备到检验和不良品的完整生产历史。

## 1. 接口

```text
GET /api/v1/traceability/sn/{productSn}
```

路由模型绑定 `ProductSn`，不存在时返回 404。

权限：

```text
traceability.forward.show
```

## 2. 数据投影而不是快照

`ForwardTraceService::trace()` 直接从业务表读取：

- `ProductSn`；
- 生产订单、产品、产品版本、产线；
- BOM 与物料；
- 工艺路线与工序；
- 生产批次；
- 工序记录、人员、班组、设备；
- 检验任务与结果；
- 不良品与返工记录；
- BOM 用量与实际领料数量。

没有单独的追溯快照表，避免业务变更后追溯数据与真实记录漂移。

## 3. 关键查询

```php
$processRecords = $sn->processRecords;
$recordIds = $processRecords->modelKeys();

$inspections = InspectionTask::query()
    ->whereIn('process_record_id', $recordIds)
    ->get();

$orderInspections = InspectionTask::query()
    ->where('production_order_id', $sn->production_order_id)
    ->whereNull('process_record_id')
    ->get();

$defects = DefectiveProduct::query()
    ->where('production_order_id', $sn->production_order_id)
    ->where('production_batch_id', $sn->production_batch_id)
    ->get();
```

## 4. 前端时间轴

`frontend/web/src/pages/traceability/forward/index.tsx`：

- 输入 SN；
- 顶部用 Steps / 时间轴展示工序；
- 展开每道工序的人员、设备、时间、检验、不良品；
- 展示 BOM 用量与实际发料量。

SN 放在查询参数里，便于复制链接。

## 5. 测试

```bash
php artisan test --compact tests/Feature/TraceabilitySeederTest.php
```

重点：

- SN 不存在返回 404；
- 能返回订单、BOM、路线、工序、检验、不良品；
- BOM 物料包含实际发料量；
- 追溯数据与各业务表一致；
- 无权限返回 403。

## 练习

用 Seeder 生成的一个 SN 查询正向追溯，核对时间轴上的工序数量是否等于路线工序数量，并找一个有检验记录和不合格品的 SN 对比展示差异。
