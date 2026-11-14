# 25: MQTT 模拟数据与消费进程

**What to build:** 模拟设备以真实 MQTT 消息进入系统，Laravel 消费进程订阅并落库，MQTT 链路按真实协议被验证。

**Blocked by:** 01, 24

**Status:** ready-for-agent

- [ ] 设备模拟发布器作为 compose 服务运行，按 mes/{factory}/{device}/status|data|alarm 主题发布消息
- [ ] 消息体包含设备编码、时间戳、状态、温度、转速、压力、产量
- [ ] 宿主机侧提供 mes:consume-mqtt 消费命令，订阅三类主题并解析消息
- [ ] 消费后写入设备历史数据并更新设备当前状态
- [ ] 模拟器在维修/离线状态下不发送产量增长的数据
