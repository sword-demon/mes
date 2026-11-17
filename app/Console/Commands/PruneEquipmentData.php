<?php

namespace App\Console\Commands;

use App\Modules\Equipment\Model\EquipmentData;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('mes:prune-equipment-data {--days= : 保留天数，默认读取配置}')]
#[Description('清理超过保留期的设备历史数据')]
class PruneEquipmentData extends Command
{
    public function handle(): int
    {
        $option = $this->option('days');
        $days = $option === null || $option === ''
            ? (int) config('equipment.data_retention_days', 31)
            : filter_var($option, FILTER_VALIDATE_INT);

        if ($days === false || $days < 1) {
            $this->error('--days 必须为正整数');

            return self::INVALID;
        }

        $cutoff = now()->subDays($days)->utc()->format('Y-m-d H:i:s.u');
        $deleted = 0;

        EquipmentData::query()
            ->where('recorded_at', '<', $cutoff)
            ->select('id')
            ->chunkById(1000, function ($rows) use (&$deleted): void {
                $ids = $rows->pluck('id');

                if ($ids->isEmpty()) {
                    return;
                }

                $deleted += EquipmentData::query()->whereIn('id', $ids)->delete();
            });

        $this->info("已清理 {$deleted} 条超过 {$days} 天的设备数据");

        return self::SUCCESS;
    }
}
