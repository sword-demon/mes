# 27 设备告警闭环

**目标**：设备指标超阈值时自动生成告警，并支持确认、处理、关闭和分级。

## 1. 告警等级

```text
info      提示
warning   警告
critical  严重
```

等级权重：

```text
info < warning < critical
```

同一设备同一告警码只保持一个未关闭告警；如果新样本等级更高，会升级原告警。

## 2. 指标和阈值

`EquipmentAlarm` 定义温度、转速、压力阈值：

```php
public const array THRESHOLDS = [
    'temperature' => ['info' => 60.0, 'warning' => 64.0, 'critical' => 68.0],
    'speed' => ['info' => 1200, 'warning' => 1350, 'critical' => 1450],
    'pressure' => ['info' => 0.70, 'warning' => 0.80, 'critical' => 0.85],
];
```

设备故障状态也会产生 `device_fault` 告警。

## 3. 自动评估

`EquipmentAlarmService::evaluate()`：

- `data` 消息：逐指标比较阈值；
- `alarm` 消息：按消息里的告警码和等级记录；
- 已存在同码未关闭告警：只在等级更高时升级；
- 不存在：新建 `pending` 告警，记录设备、指标、阈值、实测值、触发时间。

## 4. 处理状态

```text
pending → confirmed → processing → closed
```

- 确认：记录确认人/时间；
- 处理：填写处理说明；
- 关闭：可更新处理说明，记录关闭时间。

状态跳步返回 422。

## 5. 前端和 Seeder

`frontend/web/src/pages/equipment/alarms/index.tsx` 支持按设备、等级、状态、时间筛选，并执行确认/处理/关闭。

`EquipmentSeeder` 生成 20 条告警，覆盖三种等级和四种状态。

## 6. 测试

```bash
php artisan test --compact tests/Feature/Api/EquipmentAlarmTest.php
php artisan test --compact tests/Feature/EquipmentSeederTest.php
```

验收：

- 越阈值生成告警；
- 同码告警不重复；
- 更高等级会升级；
- 处理必须填写说明；
- 状态跳步拒绝；
- 列表筛选正确；
- Seeder 覆盖各等级/状态。

## 练习

构造三个温度样本：61、65、69，观察同一告警是否从 `info` 升级到 `critical`，而不是生成三条。
