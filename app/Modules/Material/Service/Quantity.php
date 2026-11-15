<?php

namespace App\Modules\Material\Service;

/**
 * Material quantities are stored as decimal(12,3); business checks use integer
 * milli-units so floating point never decides an over/under comparison.
 */
final class Quantity
{
    public static function toMilli(mixed $value): int
    {
        $normalized = number_format((float) $value, 3, '.', '');

        return (int) bcmul($normalized, '1000', 0);
    }

    public static function toDecimal(int $milli): string
    {
        return number_format($milli / 1000, 3, '.', '');
    }

    public static function toFloat(int $milli): float
    {
        return (float) self::toDecimal($milli);
    }
}
