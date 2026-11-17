<?php

namespace App\Core\Services;

use Illuminate\Support\Carbon;

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
    public static function startUtc(string $date): Carbon
    {
        return Carbon::parse($date, self::timezone())->startOfDay()->utc();
    }

    /**
     * Exclusive end of a business day, expressed in UTC.
     */
    public static function endUtc(string $date): Carbon
    {
        return Carbon::parse($date, self::timezone())->addDay()->startOfDay()->utc();
    }

    /**
     * The timezone that defines business days and user-facing dates.
     */
    public static function timezone(): string
    {
        return (string) config('app.business_timezone', 'Asia/Shanghai');
    }
}
