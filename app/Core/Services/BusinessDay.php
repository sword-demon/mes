<?php

namespace App\Core\Services;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Calendar-day boundaries for timestamps that are stored in UTC.
 *
 * Business days follow the business timezone, so a "2026-11-16" filter covers
 * 2026-11-15 16:00 UTC through 2026-11-16 16:00 UTC.
 */
final class BusinessDay
{
    /**
     * Inclusive start of a business day, expressed in UTC.
     */
    public static function startUtc(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date, self::timezone())->startOfDay()->utc();
    }

    /**
     * Exclusive end of a business day, expressed in UTC.
     */
    public static function endUtc(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date, self::timezone())->addDay()->startOfDay()->utc();
    }

    /**
     * The timezone that defines business days and user-facing dates.
     */
    public static function timezone(): string
    {
        return (string) config('app.business_timezone');
    }

    /**
     * Whether the value is a real calendar date.
     */
    public static function isDate(mixed $value): bool
    {
        return is_string($value) && CarbonImmutable::hasFormat($value, 'Y-m-d');
    }

    /**
     * Read a date filter from the request.
     *
     * Non-string values are ignored so array-shaped filters stay tolerated,
     * while malformed dates are rejected instead of failing mid-query.
     *
     * @throws ValidationException
     */
    public static function resolveDate(mixed $value, string $attribute): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        if (! self::isDate($value)) {
            throw ValidationException::withMessages([$attribute => '日期格式应为 Y-m-d']);
        }

        return $value;
    }
}
