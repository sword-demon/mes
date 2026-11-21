<?php

namespace App\Core\DataScope;

/**
 * 用户的数据访问范围。
 *
 * `all` 不受限制；`factory` 限定到一个工厂；`line` 限定到若干产线；
 * `none` 表示没有任何可见数据。
 */
final readonly class DataScope
{
    public const ALL = 'all';

    public const FACTORY = 'factory';

    public const LINE = 'line';

    public const NONE = 'none';

    /**
     * @param  array<int, int>  $productionLineIds
     */
    public function __construct(
        public string $level,
        public ?int $factoryId = null,
        public array $productionLineIds = [],
    ) {}

    public static function all(): self
    {
        return new self(self::ALL);
    }

    public static function none(): self
    {
        return new self(self::NONE);
    }

    public function isAll(): bool
    {
        return $this->level === self::ALL;
    }

    public function isNone(): bool
    {
        return $this->level === self::NONE;
    }

    /**
     * @return array{level: string, factory_id: int|null, production_line_ids: array<int, int>}
     */
    public function toArray(): array
    {
        return [
            'level' => $this->level,
            'factory_id' => $this->factoryId,
            'production_line_ids' => $this->productionLineIds,
        ];
    }
}
