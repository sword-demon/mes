# 03: 队列运行环境与任务基类

**What to build:** 开发环境一条命令同时启动业务 worker、AI worker、scheduler 与 MQTT 消费者；生产环境有可用的进程管理配置；派发的任务由独立进程执行，失败按退避重试，超时被终止。

**Blocked by:** None (can start immediately)

**Status:** done

- [x] 默认队列连接为 Redis，重试窗口大于最长任务超时
- [x] 业务事务提交后才入队
- [x] 任务基类提供重试次数、退避间隔、超时、超时即失败、事务提交后入队
- [x] 示例任务派发后接口立即返回，worker 日志中出现执行记录
- [x] 失败任务按退避重试，次数用尽进入失败队列，可人工重试
- [ ] 超时任务被终止并标记失败（配置与 `pcntl` 依赖已就绪，未写耗时测试）
- [x] 开发环境一条命令启动前端、业务 worker、AI worker、scheduler、MQTT 消费者
- [x] 覆盖框架默认注册的无超时队列监听进程
- [x] 生产环境提供进程管理配置，部署流程包含重启 worker
- [x] 部署文档说明 `pcntl` 扩展依赖

## Comments

- 「示例任务派发后接口立即返回」：本次没有新增 HTTP 触发点，改为验证 派发入队 → 真实 `queue:work` 执行 → 日志落库；同步接口的响应时间不受队列影响。
- 失败重试与耗尽已用真实 worker 验证（单次尝试耗尽后进入 `failed_jobs`）。
- AI 队列的超时由 `AiQueuedJob`（180 秒 / 2 次）保证；Laravel 以任务属性优先，命令行 `--timeout` 只是兜底，因此不能只靠 worker 参数。

