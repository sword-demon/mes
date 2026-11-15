<?php

namespace Database\Seeders;

use App\Modules\Quality\Model\DefectCode;
use App\Modules\Quality\Model\InspectionItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class QualitySeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach ([
                ['VOLTAGE', '工作电压', 'V', 5, 4.8, 5.2],
                ['TEMPERATURE', '工作温度', '℃', 25, 0, 60],
                ['LENGTH', '产品长度', 'mm', 100, 99, 101],
            ] as [$code, $name, $unit, $target, $minimum, $maximum]) {
                $item = InspectionItem::query()->updateOrCreate(['code' => $code], [
                    'name' => $name, 'unit' => $unit, 'status' => true,
                ]);
                $item->standard()->updateOrCreate([], [
                    'standard_value' => $target, 'min_value' => $minimum, 'max_value' => $maximum,
                ]);
            }

            foreach ([['D001', '表面划伤', '外观'], ['D002', '电压异常', '电气'], ['D003', '长度超差', '尺寸']] as [$code, $name, $category]) {
                DefectCode::query()->updateOrCreate(['code' => $code], [
                    'name' => $name, 'category' => $category, 'status' => true,
                ]);
            }
        });
    }
}
