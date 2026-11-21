<?php

namespace App\Core\DataScope;

use App\Modules\Production\Model\ProductionOrder;
use Illuminate\Database\Eloquent\Builder;

/**
 * Rows that belong to an order inherit the order's production line.
 */
trait ScopesThroughProductionOrder
{
    /**
     * @param  Builder<self>  $query
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
            $this->getTable().'.production_order_id',
            ProductionOrder::query()->whereIn('production_line_id', $lineIds)->select('id'),
        );
    }
}
