# 12: 设备台账、监控与告警

**What to build:** 设备列表、监控、历史曲线、告警按工厂过滤；跨工厂不能修改台账或处理告警；台账创建与编辑、告警确认与处理与关闭留下日志。

**Blocked by:** 01, 02

**Status:** done

- [x] 设备列表、监控、历史曲线按产线或工厂过滤
- [x] 告警列表按设备归属过滤
- [x] 跨范围台账修改与告警处理返回 403
- [x] 台账创建、编辑各产生一条日志
- [x] 告警确认、处理、关闭各产生一条日志，处理记录处理备注
- [x] 系统自动创建的告警按系统来源记录，或明确排除并说明理由
- [x] 现有设备测试通过

## Comments

- 告警的自动触发日志（equipment.alarm.trigger）只记 **critical** 级别（02 §9.3 默认方案），source 由消费进程的 AuditContext 决定：`mes:consume-mqtt` 启动时标记为 system，告警列表/监控/历史的用户请求仍是 human。
- 设备历史曲线用 ensure（范围外 403）而非 404：路由键是设备 id（内部标识），与 SN 详情按公开编码查 404 的场景不同。
- **Guard 已改 fail-closed 并有测试**：未实现任何 scope 接口的模型进入 `ensure()` 会抛错（对外通用 403 文案、内部类名进日志）——这是两轮审查（ticket 07/07+09）点名后本张踩实的行为变更，EquipmentAlarm 首次接入时漏 implements 的缺陷正是被它暴露的。
- DataScopeTest 的设备用例曾出现假覆盖（告警夹具只有范围内数据），已补范围外告警；DataScopeQuery 对 EquipmentAlarm/EquipmentData 的重复行归属实现保留（两处仅设备子查询，下沉收益低）。
