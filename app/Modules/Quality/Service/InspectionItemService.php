<?php

namespace App\Modules\Quality\Service;

use App\Modules\Quality\Model\InspectionItem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class InspectionItemService
{
    /** @param array<string, mixed> $data */
    public function save(array $data, ?InspectionItem $item = null): InspectionItem
    {
        return DB::transaction(function () use ($data, $item): InspectionItem {
            $item ??= new InspectionItem;
            $item->fill(Arr::only($data, ['code', 'name', 'unit', 'status']))->save();
            $values = Arr::only($data, ['standard_value', 'min_value', 'max_value']);
            if ($values !== []) {
                $item->standard()->updateOrCreate([], $values);
            }

            return $item->load('standard');
        });
    }
}
