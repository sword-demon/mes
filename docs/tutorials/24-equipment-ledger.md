# 24 设备台账

**目标**：维护设备主数据，让设备可以关联车间、产线、负责人，并被报工和工序执行记录引用。

## 1. 设备状态

```text
running   运行
idle      待机
fault     故障
repair    维修
offline   离线
```

模型还定义设备类型列表，前端表单从 `Equipment::TYPES` 取选项。

## 2. 设备字段

```text
code / name / type
workshop_id / production_line_id
model / status / enabled / owner_id
```

设备必须同时属于一个车间和该车间下的一条产线。`EquipmentService` 在创建和编辑时校验：

```php
ProductionLine::query()
    ->whereKey($lineId)
    ->where('workshop_id', $workshopId)
    ->exists();
```

## 3. 两个查询范围

```php
public function scopeAvailable(Builder $query): Builder
public function scopeEnabled(Builder $query): Builder
```

- `enabled`：只排除停用设备；
- `available`：还要排除故障、维修、离线等不可用状态。

报工和工序开始时使用 `available` 校验设备。

## 4. 接口

```text
GET  /api/v1/equipments
POST /api/v1/equipments
PUT  /api/v1/equipments/{equipment}
GET  /api/v1/equipments/options
```

列表支持车间、产线、类型、状态、关键字筛选。

## 5. Seeder

`EquipmentSeeder` 生成 5 台设备，覆盖五种状态：

```text
EQ-SMT-01 / EQ-REFLOW-01 / EQ-CNC-01 / EQ-ASM-01 / EQ-TEST-01
```

同时生成 20 条演示告警和一小时历史采样，监控与历史页面打开即有数据。

## 6. 前端

`frontend/web/src/pages/equipment/ledger/index.tsx` 提供设备台账列表、新增、编辑和停用。

## 7. 测试

```bash
php artisan test --compact tests/Feature/Api/EquipmentTest.php
php artisan test --compact tests/Feature/EquipmentSeederTest.php
```

验收：

- 设备编码唯一；
- 产线不属于车间时拒绝；
- 停用设备不出现在可用下拉；
- 报工/工序不能使用不可用设备；
- Seeder 覆盖五种状态；
- 设备能关联报工和工序记录。

## 练习

创建一台设备并挂到 WS01/PL01，然后尝试把它挂到 WS02 下的产线，观察服务端错误。
