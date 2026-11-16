<?php

namespace App\Modules\System\ValueObject;

final readonly class OperationLogEntry
{
    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function __construct(
        public string $action,
        public string $actionName,
        public string $objectType,
        public int|string|null $objectId,
        public string $objectName,
        public array $before = [],
        public array $after = [],
    ) {}
}
