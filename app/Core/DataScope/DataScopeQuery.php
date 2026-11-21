<?php

namespace App\Core\DataScope;

use App\Modules\Organization\Model\ProductionLine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Resolves a data scope into the set of production lines it can see.
 *
 * Query filters and row checks both go through this resolver so the two
 * cannot drift apart.
 */
final class DataScopeQuery
{
    private function __construct() {}

    /**
     * @return array<int, int>
     */
    public static function scopedProductionLineIds(DataScope $scope): array
    {
        if ($scope->level === DataScope::LINE) {
            return $scope->productionLineIds;
        }

        if ($scope->level === DataScope::FACTORY) {
            /** @var Collection<int, int> $ids */
            $ids = ProductionLine::query()
                ->whereHas('workshop', fn (Builder $workshop) => $workshop->where('factory_id', $scope->factoryId))
                ->pluck('id');

            return $ids->map(fn (mixed $id): int => (int) $id)->all();
        }

        return [];
    }

    /**
     * Factory-dimension rows (inventory) know no production line: a `line`
     * user sees the inventory of the factory their lines belong to.
     */
    public static function factoryInScope(?int $factoryId, DataScope $scope): bool
    {
        if ($scope->isAll()) {
            return true;
        }

        if ($factoryId === null) {
            return false;
        }

        return in_array($scope->level, [DataScope::FACTORY, DataScope::LINE], true)
            && $factoryId === $scope->factoryId;
    }
}
