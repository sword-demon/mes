<?php

namespace App\Modules\Quality\Service;

use App\Core\Exceptions\ApiException;
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
                $standard = $item->standard()->lockForUpdate()->first();
                $minimum = (float) ($values['min_value'] ?? $standard?->min_value);
                $maximum = (float) ($values['max_value'] ?? $standard?->max_value);
                $target = (float) ($values['standard_value'] ?? $standard?->standard_value);

                if ($minimum > $maximum) {
                    throw new ApiException('最小值不能大于最大值', 422);
                }

                if ($target < $minimum || $target > $maximum) {
                    throw new ApiException('标准值必须位于最小值与最大值之间', 422);
                }

                $item->standard()->updateOrCreate([], $values);
            }

            return $item->load('standard');
        });
    }
}
