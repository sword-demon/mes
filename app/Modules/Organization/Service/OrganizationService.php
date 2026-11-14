<?php

namespace App\Modules\Organization\Service;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

class OrganizationService
{
    /**
     * List an organization entity ordered by id.
     *
     * Filters are only applied when present, so the same method serves both the
     * management tables and the dropdowns that pass `status=1`. A dropdown for
     * enabled rows also requires the parents in `$enabledRelations` to be
     * enabled, so a disabled factory never feeds a new line or team.
     *
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $model
     * @param  array<int, string>  $with
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>  $searchColumns
     * @param  array<int, string>  $enabledRelations
     * @return LengthAwarePaginator<int, TModel>
     */
    public function paginate(
        string $model,
        array $with,
        array $filters,
        array $searchColumns = ['code', 'name'],
        array $enabledRelations = [],
    ): LengthAwarePaginator {
        // An unparsable `status` is ignored rather than silently read as false.
        $status = array_key_exists('status', $filters)
            ? filter_var($filters['status'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            : null;

        return $model::query()
            ->with($with)
            ->when($filters['factory_id'] ?? null, fn ($query, $id) => $query->where('factory_id', $id))
            ->when($filters['workshop_id'] ?? null, fn ($query, $id) => $query->where('workshop_id', $id))
            ->when($filters['team_id'] ?? null, fn ($query, $id) => $query->where('team_id', $id))
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->when($status === true, function ($query) use ($enabledRelations): void {
                foreach ($enabledRelations as $relation) {
                    $query->whereHas($relation, fn ($inner) => $inner->where('status', true));
                }
            })
            ->when($filters['keyword'] ?? null, function ($query, string $keyword) use ($searchColumns): void {
                $query->where(function ($inner) use ($keyword, $searchColumns): void {
                    foreach ($searchColumns as $column) {
                        $inner->orWhere($column, 'like', "%{$keyword}%");
                    }
                });
            })
            ->orderBy('id')
            ->paginate(max(1, min((int) ($filters['per_page'] ?? 15), 100)));
    }
}
