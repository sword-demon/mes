<?php

namespace App\Core\Queue;

final readonly class AiTaskResult
{
    /**
     * @param  array<string, mixed>  $output
     */
    public function __construct(
        public array $output,
        public ?int $promptTokens = null,
        public ?int $completionTokens = null,
    ) {}
}
