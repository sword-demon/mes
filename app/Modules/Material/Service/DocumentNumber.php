<?php

namespace App\Modules\Material\Service;

use App\Core\Services\BusinessDay;
use Illuminate\Database\Eloquent\Model;

final class DocumentNumber
{
    /**
     * @param  class-string<Model>  $model
     */
    public static function next(string $prefix, string $model): string
    {
        // shortcut: the unique code column rejects the rare collision; add a retry if volume makes it visible.
        do {
            $code = $prefix.now(BusinessDay::timezone())->format('YmdHis').'-'.str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while ($model::query()->where('code', $code)->exists());

        return $code;
    }
}
