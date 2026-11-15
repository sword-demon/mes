<?php

namespace App\Core\Services;

use App\Core\Exceptions\ApiException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

class EntityService
{
    /**
     * List an entity ordered by id.
     *
     * Filters are only applied when present, so the same method serves both the
     * management tables and the dropdowns that pass `status=1`. A dropdown for
     * enabled rows also hides rows whose parent in `$enabledRelations` is
     * disabled, so a disabled factory never feeds a new line or team. A row
     * with no parent (an employee without a team) stays visible.
     *
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $model
     * @param  array<int, string>  $with
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>  $searchColumns
     * @param  array<int, string>  $enabledRelations
     * @param  array<int, string>  $filterColumns  request keys filtered as exact column matches
     * @return LengthAwarePaginator<int, TModel>
     */
    public function paginate(
        string $model,
        array $with,
        array $filters,
        array $searchColumns = ['code', 'name'],
        array $enabledRelations = [],
        array $filterColumns = [],
    ): LengthAwarePaginator {
        return $this->paginateQuery(
            $model::query()->with($with),
            $filters,
            $searchColumns,
            $enabledRelations,
            $filterColumns,
        );
    }

    /**
     * Paginate an already constrained query with the same filter and
     * enablement rules as `paginate()`.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>  $searchColumns
     * @param  array<int, string>  $enabledRelations
     * @param  array<int, string>  $filterColumns  request keys filtered as exact column matches
     * @return LengthAwarePaginator<int, TModel>
     */
    public function paginateQuery(
        Builder $query,
        array $filters,
        array $searchColumns = ['code', 'name'],
        array $enabledRelations = [],
        array $filterColumns = [],
    ): LengthAwarePaginator {
        // An unparsable `status` is ignored rather than silently read as false.
        $status = array_key_exists('status', $filters)
            ? filter_var($filters['status'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            : null;

        foreach ($filterColumns as $column) {
            $query->when($filters[$column] ?? null, fn ($inner, $value) => $inner->where($column, $value));
        }

        return $query
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->when($status === true, function ($query) use ($enabledRelations): void {
                foreach ($enabledRelations as $relation) {
                    // Only a disabled parent disqualifies the row; a missing one
                    // (an employee without a team) must stay selectable.
                    $query->whereDoesntHave($relation, fn ($inner) => $inner->where('status', false));
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

    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $model
     * @param  array<string, mixed>  $data
     * @return TModel
     */
    public function create(string $model, array $data): Model
    {
        return $model::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model $entity, array $data): Model
    {
        $entity->update($data);

        return $entity;
    }

    /**
     * Delete a record only while nothing else points at it.
     *
     * A record that already carries business data must be disabled instead, so
     * historical documents never lose the row they reference.
     *
     * @param  array<string, string>  $references  relation name => error message
     *
     * @throws ApiException
     */
    public function delete(Model $entity, array $references): void
    {
        foreach ($references as $relation => $message) {
            if ($entity->{$relation}()->exists()) {
                throw new ApiException($message, 403, status: 403);
            }
        }

        $entity->delete();
    }
}
