<?php

namespace App\Core\OpenApi;

final class OpenApiConfig
{
    public static function enabledByDefault(string $environment): bool
    {
        return in_array($environment, ['local', 'testing'], true);
    }
}
