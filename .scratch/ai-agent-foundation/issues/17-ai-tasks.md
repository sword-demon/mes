# 17: AI 任务记录与端到端验证

**What to build:** 建立 AI 任务记录，状态从待执行到执行中再到成功或失败；用一个不调用模型的任务跑通派发、执行、结果落库全链路。

**Blocked by:** 03

**Status:** done

- [x] AI 任务记录包含链路标识、任务类型、状态、发起人、模型标识、输入输出、错误、token 用量、耗时、起止时间
- [x] 任务派发前创建待执行记录
- [x] 任务开始执行时更新为执行中
- [x] 执行结束写入成功或失败、输出、耗时
- [x] 重试复用同一条记录，不新建
- [x] 示例任务端到端成功，结果可查询
- [x] 失败任务记录错误信息且状态为失败
- [x] 有测试覆盖状态流转与失败路径

## Comments

- `AiTaskService::dispatch()` 先建 `pending` 记录再入队；`AiTaskJob` 重复投递时跳过 `succeeded/cancelled`，`failed` 允许重试并复用同一条记录。
- 任务结果支持 `output` 与 token 用量（`AiTaskResult`）；失败回调写 `Log::error` 并落 `failed`、错误信息与耗时。错误文本按 16000 字符截断，避免中文撑爆 MySQL `TEXT`。
- 示例 `ExampleAiTaskJob` 不调用模型，已用真实 `queue:work --queue=ai` 验证 `pending → running → succeeded` 与失败路径。
- 审计上下文（actor/ip/trace 写入 `AuditContext`）留给 #18；本期未新增 HTTP 查询接口，结果通过 `AiTask` 记录查询。
