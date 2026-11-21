<?php

namespace App\Modules\Inventory\Model;

use App\Core\DataScope\DataScope;
use App\Core\DataScope\HasFactoryScope;
use Database\Factories\StorageLocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $warehouse_id
 * @property string $code
 * @property string $name
 * @property bool $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['warehouse_id', 'code', 'name', 'status'])]
#[Table('mes_storage_locations')]
#[UseFactory(StorageLocationFactory::class)]
class StorageLocation extends Model implements HasFactoryScope
{
    /** @use HasFactory<StorageLocationFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    /**
     * A location inherits its warehouse's factory.
     *
     * @param  Builder<self>  $query
     */
    public function scopeInDataScope(Builder $query, DataScope $scope): void
    {
        if ($scope->isAll()) {
            return;
        }

        $query->whereHas('warehouse', fn (Builder $warehouse) => $warehouse
            ->when(
                $scope->factoryId !== null,
                fn (Builder $warehouse) => $warehouse->where('factory_id', $scope->factoryId),
                fn (Builder $warehouse) => $warehouse->whereRaw('1 = 0'),
            ));
    }

    public function dataScopeFactoryId(): ?int
    {
        return $this->warehouse?->factory_id;
    }

    /**
     * @return HasMany<Inventory, $this>
     */
    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class, 'location_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'warehouse_id' => 'integer',
            'status' => 'boolean',
        ];
    }
}
