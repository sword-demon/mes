# 03 异步队列需求

> 目标读者：后端开发、运维。本文定义队列的运行环境、任务规范与 AI 长任务的异步化方式。

## 1. 目标

让应用具备可靠的后台任务执行能力，使 LLM 调用等秒级操作不占用 HTTP 请求线程，并保证任务在失败、重启、超时场景下不丢、不重复产生副作用。

验收口径：`dispatch()` 一个耗时 60 秒的任务，HTTP 接口在 200ms 内返回；任务由独立 worker 执行；worker 重启后任务继续执行；执行超时的任务进入 `failed_jobs` 并可人工重试。

## 2. 现状

### 2.1 基础设施

| 项 | 现状 | 位置 |
| --- | --- | --- |
| Redis 服务 | 开发/生产编排中均已提供 | `docker/docker-compose.yml:24-37`、`docker/docker-compose.prod.yml:26` |
| 队列连接 | `.env` 与 `.env.example` 均为 `QUEUE_CONNECTION=redis` | `.env:38`、`.env.example:49` |
| 配置默认值 | `config/queue.php` 默认 `database`，与 `.env` 不一致 | `config/queue.php:16` |
| Redis 端口 | 开发环境 `127.0.0.1:16379` | `.env:48` |
| 重试窗口 | `retry_after = 90` 秒 | `config/queue.php:71` |
| 任务表 | `jobs`、`failed_jobs`、`job_batches` 已创建 | `database/migrations/0001_01_01_000002_create_jobs_table.php` |
| worker 进程 | 开发环境由 `artisan dev` 默认注册一个 `queue:listen --tries=1 --timeout=0`；**生产环境无** | `vendor/laravel/framework/src/Illuminate/Foundation/DevCommands.php:121` |
| scheduler 进程 | **无** | 同上 |
| 任务类 | **无**，全仓无 `ShouldQueue`、无 `dispatch()` | — |

### 2.2 已声明的要求

`docs/prd.md:11.3` 已明确：

- 开发环境由 Herd 运行 Laravel API、Queue Worker、Scheduler 与 MQTT 消费进程；
- 生产环境使用 Docker Compose + 服务器 PHP-FPM，部署 API、Queue Worker、Scheduler、MySQL、Redis、EMQX、React Web。

当前实现只完成了 API 与 MQTT 消费，**Queue Worker 与 Scheduler 缺失**。

`routes/console.php` 已定义两个定时任务：

- `ScanInventoryAlerts` 每 5 分钟；
- `PruneEquipmentData` 每天 03:10。

但没有任何进程执行 `schedule:run` / `schedule:work`，这两个任务实际上不会自动运行。

`composer run dev` 最终执行 `php artisan dev`（`composer.json:51-54`）。该命令在 `ArtisanServiceProvider` 启动时调用 `DevCommands::registerDefaults()`，**默认已注册 `queue:listen --tries=1 --timeout=0`**（`vendor/laravel/framework/src/Illuminate/Foundation/DevCommands.php:121`）；项目自身只额外注册了前端开发服务（`routes/console.php:14`）。

这个默认 listener 不能直接用于 AI 任务，原因有三：

1. `--tries=1`：失败不重试；
2. `--timeout=0`：任务永不超时，长任务会占死唯一进程；
3. 不区分队列：AI 任务与业务任务互相阻塞。

`DevCommands::register()` 在名称相同时按优先级覆盖（`DevCommands.php:150-154`），项目代码注册的同名命令优先级高于 vendor 默认值，因此可以在 `routes/console.php` 中用同名 `queue` 覆盖它。

### 2.3 为什么现在必须补

- **AI 调用是秒级操作**。同步调用会占用 PHP-FPM 进程，50 并发下直接打满进程池。
- **AI 任务需要重试与可观测**。网络抖动、限流、超时都需要独立的重试策略与失败记录。
- **已有定时任务在裸奔**。库存预警和 MQTT 数据清理目前依赖人工触发，属于存量缺陷。

## 3. 范围

### 3.1 本期做

- 统一队列连接配置，修正 `retry_after`。
- 开发与生产环境的 worker、scheduler 启动方式。
- 任务基类与编写规范（重试、超时、幂等、上下文传递）。
- 队列划分（`default` / `ai`）。
- AI 任务记录表 `sys_ai_tasks`。
- 失败处理与观测手段。
- 一个端到端验证任务与测试。

### 3.2 本期不做

- Horizon（Redis 队列监控面板）。单机部署，`queue:monitor` 足够。
- 队列优先级动态调整、延迟队列业务化。
- 任务级分布式锁服务（用 `ShouldBeUnique` 即可）。
- 多机 worker 调度与自动扩缩容。

## 4. 详细设计

### 4.1 连接与队列划分

统一使用 `redis` 连接。开发者本地若未启动 Redis，可临时设置 `QUEUE_CONNECTION=database`，但**不作为默认**。

```text
config/queue.php
  default = env('QUEUE_CONNECTION', 'redis')      // 与 .env 对齐

connections.redis
  retry_after = env('REDIS_QUEUE_RETRY_AFTER', 240)
  block_for   = 5
  after_commit = true
```

三个关键参数：

| 参数 | 值 | 原因 |
| --- | --- | --- |
| `retry_after` | 240 秒 | **必须大于最长任务 timeout**。当前 90 秒小于 AI 任务的 180 秒超时，会导致任务被第二个 worker 重复领取 |
| `after_commit` | `true` | 事务未提交时不入队，避免 worker 读到未提交数据 |
| `block_for` | 5 秒 | 降低空闲轮询的 Redis 压力 |

队列划分：

| 队列 | 用途 | 建议 timeout | 并发 |
| --- | --- | --- | --- |
| `default` | 业务异步任务（导出、通知、批量操作） | 60 秒 | 2~4 个 worker |
| `ai` | LLM 调用、推理、报告生成 | 180 秒 | 1 个 worker（受供应商限流约束） |

**必须使用独立 worker 进程处理两个队列**，不要用 `--queue=default,ai` 单进程：

- 单进程按顺序取任务，一个 180 秒的 AI 任务会阻塞全部业务任务；
- 两个队列的 timeout 需求不同，单进程只能取最大值，导致业务任务超时判定过晚。

### 4.2 任务编写规范

新增基类：

```php
namespace App\Core\Queue;

abstract class QueuedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public bool $failOnTimeout = true;

    /** @var array<int, string>|string */
    public array|string $queue = 'default';

    public function __construct()
    {
        $this->afterCommit();
    }
}
```

规则：

1. **只传标识，不传模型**。构造参数只接受标量（ID、编号、trace_id），在 `handle()` 中重新查询。避免序列化过期模型与体积膨胀。
2. **必须幂等**。任务可能因超时、worker 重启、重试而执行多次。写操作要以业务唯一键做保护（`firstOrCreate`、`updateOrCreate`、状态前置判断）。
3. **不使用 `Request`**。队列进程没有 HTTP 上下文，`request()->user()` 为 `null`、`request()->ip()` 不可信。需要用户与 IP 时由任务载荷显式传入（见 §4.6）。
4. **不用 `sleep()` 等待**。需要延迟时用 `->delay()`。
5. **单个任务控制在 10 分钟内**。更长的流程拆成多个任务串联。
6. **`failed()` 必须实现**。至少写日志，并把业务记录置为失败态。

### 4.3 AI 任务记录表

所有 `ai` 队列任务都必须有对应的 `sys_ai_tasks` 记录，用于观测、重试与审计关联。

```text
sys_ai_tasks
  id                  bigint
  trace_id            varchar(64)   comment('链路标识')
  type                varchar(64)   comment('任务类型：exception-analysis/quality-advice/...')
  status              varchar(16)   comment('状态：pending/running/succeeded/failed/cancelled')
  actor_id            bigint nullable comment('发起人 ID')
  actor_ip            varchar(45) nullable comment('发起时 IP')
  model               varchar(64) nullable comment('模型标识')
  input               json nullable   comment('输入快照')
  output              json nullable   comment('输出快照')
  error               text nullable   comment('错误信息')
  prompt_tokens       int nullable    comment('输入 token 数')
  completion_tokens   int nullable    comment('输出 token 数')
  latency_ms          int nullable    comment('耗时毫秒')
  started_at          timestamp nullable comment('开始时间')
  finished_at         timestamp nullable comment('结束时间')
  created_at / updated_at
  index(trace_id), index(type, status), index(created_at)
```

状态流转：

```text
pending ──dispatch──> running ──成功──> succeeded
                         │
                         ├──异常──> failed（可重试回 running）
                         └──取消──> cancelled
```

约束：

- `trace_id` 与 `sys_operation_logs.trace_id` 同源，用于把"AI 分析"与"人工确认后的写操作"串起来。
- `input` / `output` 存结构化 JSON，不存完整提示词明文（提示词模板放在代码中，用版本号标识）。
- 任务重试时复用同一条记录，更新 `status` 与 `error`，不新建记录。

### 4.4 运行环境

#### 开发环境（Herd）

在 `routes/console.php` 注册两个开发进程：

```php
DevCommands::register('php artisan queue:work redis --queue=default --timeout=60 --tries=3 --max-time=3600', 'queue');
DevCommands::register('php artisan queue:work redis --queue=ai --timeout=180 --tries=2 --max-time=3600', 'ai-worker');
DevCommands::register('php artisan schedule:work', 'scheduler');
```

`composer run dev` 后会同时启动前端、业务 worker、AI worker 与 scheduler。

第一行的命令名 `queue` 会覆盖 Laravel 默认注册的 `queue:listen --tries=1 --timeout=0`（项目代码优先级为 `PRIORITY_USERLAND`，高于默认值），因此不需要额外移除默认命令。

同时补一个常驻 MQTT 消费者（若尚未纳入）：

```php
DevCommands::register('php artisan mes:consume-mqtt', 'mqtt');
```

#### 生产环境

现有编排把 PHP 运行在宿主机（PHP-FPM），容器只承载中间件。因此 worker 与 scheduler 用 **Supervisor** 管理：

```ini
[program:mes-queue-default]
command=/usr/bin/php /var/www/mes/artisan queue:work redis --queue=default --timeout=60 --tries=3 --max-time=3600
numprocs=2
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
stdout_logfile=/var/log/mes/queue-default.log

[program:mes-queue-ai]
command=/usr/bin/php /var/www/mes/artisan queue:work redis --queue=ai --timeout=180 --tries=2 --max-time=3600
numprocs=1
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
stdout_logfile=/var/log/mes/queue-ai.log

[program:mes-scheduler]
command=/usr/bin/php /var/www/mes/artisan schedule:work
numprocs=1
autostart=true
autorestart=true
stdout_logfile=/var/log/mes/scheduler.log
```

部署流程追加一步：

```bash
php artisan queue:restart
```

否则 worker 会继续执行旧代码。

若后续把 PHP-FPM 一并容器化，则在 `docker-compose.prod.yml` 中增加 `worker-default`、`worker-ai`、`scheduler` 三个服务，复用同一镜像，`command` 对应上述 artisan 命令，并 `depends_on: mysql/redis` 健康检查。

#### 环境依赖

- **必须启用 `pcntl` 扩展**。Laravel 依赖 `pcntl_alarm` 实现任务超时；没有该扩展时 `--timeout` 不生效，长任务会无限占用 worker。
- 生产环境 PHP CLI 与 FPM 使用同一 `.env`，确保 `QUEUE_CONNECTION=redis`。

### 4.5 失败与重试

| 场景 | 行为 |
| --- | --- |
| 网络错误、供应商 429/5xx | 按 `$backoff` 重试，最多 `$tries` 次 |
| 业务校验失败（如订单不存在） | 不重试，`$this->fail()`，记录 `error` |
| 执行超时 | 标记失败，进入 `failed_jobs`；因任务必须幂等，重复执行无副作用 |
| 重试耗尽 | 进入 `failed_jobs`，业务记录置 `failed` |

操作命令：

```bash
php artisan queue:failed              # 查看失败任务
php artisan queue:retry all           # 重试全部
php artisan queue:retry {id}          # 重试单个
php artisan queue:forget {id}         # 删除单条
php artisan queue:flush               # 清空失败任务（谨慎）
```

`failed_jobs` 已有表，无需新建。

告警：本期先写 `Log::error()`，接入通知渠道后由 02 文档的告警体系承接。

### 4.6 上下文传递

这是最容易踩的坑：`OperationLogService` 通过构造函数注入 `Request`（`app/Modules/System/Service/OperationLogService.php:13`），在队列进程中：

- `$request->user()` 返回 `null`；
- `$request->ip()` 返回 `127.0.0.1` 或空字符串。

如果 AI 任务在队列中执行写操作并写审计日志，日志会丢失操作人。

解决方案：

1. 任务载荷显式携带 `actor_id`、`actor_ip`、`trace_id`：

```php
final class AnalyzeExceptionJob extends QueuedJob
{
    public function __construct(
        public readonly int $exceptionId,
        public readonly int $actorId,
        public readonly string $actorIp,
        public readonly string $traceId,
    ) {}
}
```

2. 新增 `app/Core/Audit/AuditContext`（请求级/进程级单例）：

```php
final class AuditContext
{
    public function set(string $source, ?int $actorId, ?string $ip, ?string $traceId): void;
    public function source(): string;
    public function actor(): ?User;
    public function ip(): ?string;
    public function traceId(): ?string;
}
```

3. `OperationLogService::record()` 在未显式传参时优先读 `AuditContext`，再退回 `Request`。
4. 任务 `handle()` 开头设置 `AuditContext`，任务结束（含 `finally`）时清理，避免长驻 worker 的上下文污染。

`AuditContext` 是 01、02、03 三份文档的共同依赖，建议优先实施。

### 4.7 观测

| 手段 | 命令/位置 | 用途 |
| --- | --- | --- |
| 队列积压 | `php artisan queue:monitor redis:default,redis:ai --max=100` | 超过阈值返回非零退出码，可接监控 |
| 失败任务 | `php artisan queue:failed` | 人工处理 |
| 任务日志 | `storage/logs/laravel.log` | 含 `trace_id` |
| AI 任务统计 | `sys_ai_tasks` 按 `type`、`status` 聚合 | 成功率、平均耗时、token 消耗 |

建议增加一个 Artisan 命令 `mes:queue-report`，输出各队列积压、近 24 小时失败数、AI 任务成功率，供运维日常巡检。本期可选。

### 4.8 并发与限流

- `ai` 队列固定 1 个 worker，避免供应商限流与费用失控。
- 若未来需要提高并发，使用 Laravel 的 `RateLimited` job middleware，按供应商配额限制：

```php
public function middleware(): array
{
    return [new RateLimited('llm-provider')];
}
```

并在 `AppServiceProvider` 定义 `llm-provider` 限流器。

- `default` 队列 worker 数不超过 CPU 核数，且与 PHP-FPM 进程池总和不超过机器承载。

## 5. 接口变更

本期不新增业务接口。可选新增只读接口：

```text
GET /api/v1/ai/tasks/{trace_id}
```

返回 `sys_ai_tasks` 的状态与结果，供前端轮询 AI 任务进度。若实现，需权限码 `ai.tasks.show`，并按 01 文档的数据范围校验 `actor_id`。

## 6. 配置变更

| 文件 | 变更 |
| --- | --- |
| `config/queue.php` | 默认连接改为 `redis`；`retry_after` 改为 240；`after_commit` 改为 `true`；`block_for` 改为 5 |
| `.env.example` | 增加 `REDIS_QUEUE_RETRY_AFTER=240` |
| `routes/console.php` | 注册 worker、AI worker、scheduler、MQTT 的 `DevCommands` |
| 部署文档 | 补充 Supervisor 配置与 `queue:restart` 步骤 |

无数据库迁移（`jobs` / `failed_jobs` 已存在），仅新增 `sys_ai_tasks` 表。

## 7. 验收标准

### 7.1 基础能力

- [ ] `composer run dev` 后，`default` 与 `ai` 两个 worker 进程存活。
- [ ] `schedule:work` 运行后，`ScanInventoryAlerts` 每 5 分钟执行一次（日志可验证）。
- [ ] `PruneEquipmentData` 在 03:10 执行。
- [ ] 生产环境 Supervisor 重启后 worker 自动拉起。

### 7.2 任务行为

- [ ] `dispatch()` 后接口立即返回，任务在 worker 日志中出现。
- [ ] 业务事务回滚时，`after_commit` 保证任务不入队。
- [ ] 任务抛出异常后按 `$backoff` 重试，次数用尽进入 `failed_jobs`。
- [ ] 执行超过 `$timeout` 的任务被终止并标记失败。
- [ ] `php artisan queue:restart` 后，新任务由新代码执行。
- [ ] 同一任务被重复投递时，业务数据不产生重复记录（幂等验证）。

### 7.3 上下文

- [ ] 队列任务中写审计日志，`user_id` 为任务载荷中的 `actor_id`，`ip` 为 `actor_ip`。
- [ ] AI 任务产生的日志 `source = ai`，`trace_id` 与 `sys_ai_tasks.trace_id` 一致。
- [ ] 连续执行两个不同用户的任务后，第二个任务不会读到第一个任务的 `AuditContext`。

### 7.4 AI 任务

- [ ] AI 任务执行全过程在 `sys_ai_tasks` 有记录，状态从 `pending → running → succeeded/failed`。
- [ ] 失败任务记录 `error` 与 `latency_ms`。
- [ ] 一个 180 秒的 AI 任务执行期间，`default` 队列的其他任务正常执行（验证进程隔离）。

### 7.5 测试组织

新增 `tests/Feature/QueueTest.php`：

- 使用 `Queue::fake()` 断言 dispatch 行为；
- 使用 `Bus::fake()` 断言任务链；
- 真实执行路径用 `queue:work --once` 在测试中跑一次（或使用 `RefreshDatabase` + 同步队列）。

新增 `tests/Unit/QueuedJobTest.php` 校验基类默认值（tries、timeout、backoff）。

## 8. 实施拆分

| 阶段 | 内容 | 依赖 |
| --- | --- | --- |
| Q1 | 配置修正 + `AuditContext` + 任务基类 | 无 |
| Q2 | dev 进程注册 + supervisor 配置 + 部署文档 | Q1 |
| Q3 | `sys_ai_tasks` 表 + 任务记录服务 | Q1 |
| Q4 | 首个 AI 任务（建议：生产异常分析）接入 | Q2、Q3 |
| Q5 | 观测命令与失败告警 | Q3 |

Q1 中的 `AuditContext` 与 02 文档强相关，建议同时实施。

## 9. 风险与待确认

| 风险 | 影响 | 缓解 |
| --- | --- | --- |
| `retry_after` 小于任务 timeout | 任务被重复领取，产生重复副作用 | 固定 240 秒并写入配置注释；任务强制幂等 |
| 缺少 `pcntl` | 超时不生效，worker 被长任务占死 | 部署检查清单增加 `php -m \| grep pcntl` |
| 队列上下文丢失用户 | 审计日志操作人为空 | `AuditContext` + 任务载荷显式传参 |
| AI 费用失控 | 单日 token 消耗不可控 | `ai` 队列单 worker + 按用户/类型配额 + `sys_ai_tasks` 统计 |
| Redis 不可用 | 任务全部阻塞 | Redis 已有 `appendonly yes` 持久化；应用侧降级为同步需单独设计，本期不降级 |
| scheduler 重复执行 | 多实例部署时任务重复 | `withoutOverlapping` 已使用；单实例部署前提下可接受 |

需确认：

1. 生产环境是否允许在宿主机安装 Supervisor？若不允许，需要改为容器化部署。
2. AI 任务单次调用的超时上限（当前按 180 秒设计），是否需要更长？
3. 是否需要 `mes:queue-report` 巡检命令，还是直接接入现有监控？
