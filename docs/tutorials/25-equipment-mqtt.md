# 25 MQTT 模拟数据与消费进程

**目标**：让模拟设备以真实 MQTT 消息进入系统，Laravel 消费进程订阅、校验、落库并更新设备状态。

## 1. 主题和消息

模拟器发布：

```text
mes/{factory}/{device}/status
mes/{factory}/{device}/data
mes/{factory}/{device}/alarm
```

消息体：

```json
{
  "device_code": "EQ-SMT-01",
  "timestamp": "2026-11-18T03:00:00+00:00",
  "status": "running",
  "temperature": 57.3,
  "speed": 1120,
  "pressure": 0.71,
  "output": 128,
  "runtime_seconds": 3600
}
```

## 2. 消费命令

```bash
php artisan mes:consume-mqtt
php artisan mes:consume-mqtt --max-messages=10
```

`ConsumeMqtt`：

- 读取 `MQTT_FACTORY`；
- 连接 EMQX；
- 订阅三类主题，QoS 1；
- 等待三个订阅都确认；
- 每条消息交给 `EquipmentMessageService::consume()`；
- 支持 SIGINT/SIGTERM 优雅退出；
- 记录无效消息数量，避免坏消息刷屏。

## 3. 消息校验

`EquipmentMessageService` 校验：

- 主题必须是四段且工厂匹配；
- 消息不超过 `MQTT_MAX_PAYLOAD_BYTES`；
- JSON 合法；
- `device_code` 与主题设备一致；
- 时间戳格式合法且不能超前服务器 5 分钟；
- 状态、温度、转速、压力、产量范围合法；
- 告警消息额外要求 `alarm_code`、`message`、`level`；
- 设备必须存在。

## 4. 幂等和历史数据

历史数据唯一键：

```text
equipment_id + message_type + recorded_at
```

相同采样重复到达不会重复插入。只有新样本才更新设备状态和评估告警。

## 5. 状态更新

模拟器的 `standby` 映射为 `idle`，`maintenance` 映射为 `repair`。更新状态时只接受“不早于当前最新样本”的数据，避免迟到消息把状态改回旧值。

## 6. 测试

```bash
php artisan test --compact tests/Feature/EquipmentMessageServiceTest.php
php artisan test --compact tests/Feature/MqttProtocolTest.php
```

重点：

- 非法主题/JSON/字段被拒绝；
- 重复样本不重复插入；
- 设备状态更新；
- 维修/离线设备没有产量增长；
- `--max-messages` 能退出；
- 真实 EMQX 联调用例在无 EMQX 环境下跳过。

## 练习

启动 compose 和模拟器，执行：

```bash
docker compose --env-file .env -f docker/docker-compose.yml logs -f simulator
php artisan mes:consume-mqtt --max-messages=5
```

检查 `mes_equipment_data` 是否新增 5 条有效样本。
