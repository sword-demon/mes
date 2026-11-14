# 02: 后端骨架与统一响应契约

**What to build:** 后端骨架就位：模块化目录、统一响应格式、统一异常处理与环境配置就绪，为后续所有业务切片提供稳定底座。

**Blocked by:** None (can start immediately)

**Status:** done

- [x] app/Modules 下按 System/Organization/BasicData/Product/Process/Production/Quality/Material/Equipment/Traceability 划分模块目录，各含 Controller/FormRequest/Service/Model/Resource
- [x] 所有 API 返回统一格式 { code, message, data, timestamp }，业务异常以对应 code + message 返回
- [x] api/v1 前缀、throttleApi、API 异常自动 JSON 渲染已生效
- [x] Controller 只处理 HTTP、FormRequest 校验、Service 承载业务逻辑的分层约束落到代码结构（System 模块已按此实现）
- [x] 队列走 Redis、缓存走 Redis、MQTT 与 Redis 连接项按 .env 约定读取
