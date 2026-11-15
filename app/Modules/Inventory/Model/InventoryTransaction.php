<?php

namespace App\Modules\Inventory\Model;

use App\Modules\Material\Model\Material;
use App\Modules\System\Model\User;
use Database\Factories\InventoryTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $inventory_id
 * @property int $material_id
 * @property string $business_type
 * @property float $quantity
 * @property float $before_quantity
 * @property float $after_quantity
 * @property string|null $reference_no
 * @property string|null $remark
 * @property int|null $created_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'inventory_id',
    'material_id',
    'business_type',
    'quantity',
    'before_quantity',
    'after_quantity',
    'reference_no',
    'remark',
    'created_by_id',
])]
#[Table('mes_inventory_transactions')]
#[UseFactory(InventoryTransactionFactory::class)]
class InventoryTransaction extends Model
{
    /** @use HasFactory<InventoryTransactionFactory> */
    use HasFactory;

    public const string INBOUND = 'inbound';

    public const string OUTBOUND = 'outbound';

    public const string ADJUSTMENT = 'adjustment';

    /**
     * @return array<string, string>
     */
    public static function businessTypeLabels(): array
    {
        return [
            self::INBOUND => '入库',
            self::OUTBOUND => '出库',
            self::ADJUSTMENT => '库存调整',
        ];
    }

    /**
     * @return BelongsTo<Inventory, $this>
     */
    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class, 'inventory_id');
    }

    /**
     * @return BelongsTo<Material, $this>
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'inventory_id' => 'integer',
            'material_id' => 'integer',
            'quantity' => 'float',
            'before_quantity' => 'float',
            'after_quantity' => 'float',
            'created_by_id' => 'integer',
        ];
    }
}
