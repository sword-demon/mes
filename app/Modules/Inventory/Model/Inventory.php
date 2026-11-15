<?php

namespace App\Modules\Inventory\Model;

use App\Modules\Material\Model\Material;
use Database\Factories\InventoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * PRD 6.5 names the table mes_inventory; the repo pluralizes table names.
 * Frozen quantity is a reservation state, not a stock movement, so ledger rows
 * track only current_quantity.
 *
 * @property int $id
 * @property int $material_id
 * @property int $location_id
 * @property string $batch_no
 * @property float $current_quantity
 * @property float $frozen_quantity
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'material_id',
    'location_id',
    'batch_no',
    'current_quantity',
    'frozen_quantity',
])]
#[Table('mes_inventories')]
#[UseFactory(InventoryFactory::class)]
class Inventory extends Model
{
    /** @use HasFactory<InventoryFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Material, $this>
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }

    /**
     * @return BelongsTo<StorageLocation, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class, 'location_id');
    }

    /**
     * @return HasMany<InventoryTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class, 'inventory_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'material_id' => 'integer',
            'location_id' => 'integer',
            'current_quantity' => 'float',
            'frozen_quantity' => 'float',
        ];
    }
}
