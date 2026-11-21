<?php

namespace App\Modules\Material\Service;

final class MaterialSummary
{
    /**
     * Format `code×quantity` pairs as one log line.
     *
     * @param  iterable<int, array{0: string, 1: mixed}>  $lines
     */
    public static function format(iterable $lines): string
    {
        $summary = [];

        foreach ($lines as [$code, $quantity]) {
            $summary[] = $code.'×'.Quantity::toDecimal(Quantity::toMilli($quantity));
        }

        return implode('、', $summary);
    }
}
