# 18: 队列上下文与审计打通

**What to build:** 队列任务写审计日志时记录真实发起人与 IP；一次 AI 会话产生的多条写操作可以用同一链路标识串起来；连续执行不同用户的任务不会串上下文。

**Blocked by:** 02, 03, 17

**Status:** done

- [x] 任务载荷携带发起人、发起 IP、链路标识
- [x] 任务执行时设置审计上下文，执行结束在 finally 中清理
- [x] 任务内写日志的操作人与 IP 为任务载荷中的值，不是空值或 127.0.0.1
- [x] AI 执行入口写日志时来源标记为 AI
- [x] 同一链路标识下的多条日志可一次查出
- [x] 连续执行两个不同用户的任务，第二个任务不会读到第一个任务的上下文
- [x] 有测试覆盖队列上下文与上下文隔离

## Comments

- `QueuedJob` 载荷新增 `auditActorId` / `auditActorIp` / `auditTraceId`，`AiTaskService::dispatch()` 强制要求发起人与 IP，避免空 IP 回落到 `127.0.0.1`。
- `AiTaskJob` 在 `withAuditContext()` 中设置 `source=ai` 并在 `finally` 重置；按 03 §4.6 归零为 human，而不是恢复上一个任务的状态。
- 测试覆盖载荷属性、真实 `queue:work --queue=ai` 落日志、连续两个用户任务不串上下文，以及按 `trace_id` 经操作日志接口一次查出多条记录。
