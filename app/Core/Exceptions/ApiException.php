<?php

namespace App\Core\Exceptions;

use RuntimeException;

/**
 * Business failure carrying the code and message returned to the client.
 */
class ApiException extends RuntimeException
{
    /**
     * @param  array<string, mixed>|null  $data
     */
    public function __construct(
        string $message,
        int $code = 400,
        public readonly ?array $data = null,
        public readonly ?int $status = null,
    ) {
        parent::__construct($message, $code);
    }

    public function httpStatus(): int
    {
        return $this->status ?? ($this->code >= 400 && $this->code <= 599 ? $this->code : 400);
    }
}
