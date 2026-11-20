<?php

namespace App\Modules\Production\Model\Concerns;

use App\Core\DataScope\DataScope;
use App\Core\DataScope\DataScopeQuery;
use App\Modules\Production\Model\ProductionOrder;
use App\Modules\Production\Model\ProductionTask;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Data scope for models that hang off a production task: they belong to the
 * line of the task, which falls back to the line of its order once the task
 * has been assigned to one.
 *
 * The host model must expose a `productionTask()` relation and implement
 * `HasDataScope` with this trait's `dataScopeProductionLineId()`.
 *
 * @property-read ProductionTask|null $productionTask
 */
trait ScopedThroughTask
{
    /**
     * The task the scoped row hangs off; declared abstract so a host model
     * without the relation fails at class resolution, not at first query.
     */
    abstract public function productionTask(): BelongsTo;

    /**
     * @param  Builder<Model>  $query
     */
    public function scopeInDataScope(Builder $query, DataScope $scope): void
    {
        if ($scope->isAll()) {
            return;
        }

        $lineIds = DataScopeQuery::scopedProductionLineIds($scope);

        if ($lineIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereIn(
            $this->getTable().'.production_task_id',
            ProductionTask::query()
                ->where(function (Builder $task) use ($lineIds): void {
                    $task->whereIn('mes_production_tasks.production_line_id', $lineIds)
                        ->orWhere(function (Builder $fallback) use ($lineIds): void {
                            // Once a task has its own line, that line wins over
                            // the order's: an assignment to an out-of-scope
                            // line must hide the row, not leak it.
                            $fallback->whereNull('mes_production_tasks.production_line_id')
                                ->whereIn(
                                    'mes_production_tasks.production_order_id',
                                    ProductionOrder::query()
                                        ->whereIn('production_line_id', $lineIds)
                                        ->select('id'),
                                );
                        });
                })
                ->select('id'),
        );
    }

    public function dataScopeProductionLineId(): ?int
    {
        return $this->productionTask?->dataScopeProductionLineId();
    }
}
