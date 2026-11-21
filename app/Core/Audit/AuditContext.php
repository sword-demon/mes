<?php

namespace App\Core\Audit;

use App\Modules\System\Model\User;

/**
 * 当前请求或进程的审计上下文。
 *
 * HTTP 请求默认是人工来源；AI 执行入口与系统进程通过 `set()` 显式标记，
 * 使操作日志在异步场景下仍能记录真实的发起人与链路标识。
 */
class AuditContext
{
    public const SOURCE_HUMAN = 'human';

    public const SOURCE_AI = 'ai';

    public const SOURCE_SYSTEM = 'system';

    /**
     * Every source a log row may carry.
     *
     * @return array<int, string>
     */
    public static function sources(): array
    {
        return [self::SOURCE_HUMAN, self::SOURCE_AI, self::SOURCE_SYSTEM];
    }

    private string $source = self::SOURCE_HUMAN;

    private ?User $actor = null;

    private ?string $ip = null;

    private ?string $traceId = null;

    public function set(
        string $source,
        ?User $actor = null,
        ?string $ip = null,
        ?string $traceId = null,
    ): void {
        $this->source = $source;
        $this->actor = $actor;
        $this->ip = $ip;
        $this->traceId = $traceId;
    }

    public function reset(): void
    {
        $this->source = self::SOURCE_HUMAN;
        $this->actor = null;
        $this->ip = null;
        $this->traceId = null;
    }

    public function source(): string
    {
        return $this->source;
    }

    public function actor(): ?User
    {
        return $this->actor;
    }

    public function ip(): ?string
    {
        return $this->ip;
    }

    public function traceId(): ?string
    {
        return $this->traceId;
    }
}
