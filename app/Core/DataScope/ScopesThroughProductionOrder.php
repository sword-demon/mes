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
        DataScopeQuery::applyLineScope($query, $scope, function (array $lineIds) use ($query): void {
            $query->whereIn(
                $this->getTable().'.production_order_id',
                ProductionOrder::query()->whereIn('production_line_id', $lineIds)->select('id'),
            );
        });
    }
}
