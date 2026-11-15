<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tasks created before the item snapshot existed used the enabled item
     * set at judgement time; seed the same set so they stay recordable.
     */
    public function up(): void
    {
        $itemIds = DB::table('mes_inspection_items')->where('status', true)->pluck('id');

        if ($itemIds->isEmpty()) {
            return;
        }

        DB::table('mes_inspection_tasks')->orderBy('id')->each(function (object $task) use ($itemIds): void {
            $now = now();

            DB::table('mes_inspection_task_items')->insertOrIgnore(
                $itemIds->map(fn (int $itemId): array => [
                    'inspection_task_id' => $task->id,
                    'inspection_item_id' => $itemId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all(),
            );
        });
    }

    /**
     * The backfill is not reversed; dropping the pivot would break tasks.
     */
    public function down(): void {}
};
