<?php

namespace App\Modules\Inventory\Model;

use App\Core\Models\Concerns\HasStatusLabels;
use App\Modules\Material\Model\Material;
use App\Modules\System\Model\User;
use Database\Factories\InventoryAlertFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $material_id
 * @property string $min_stock
 * @property string $current_quantity
 * @property string $status
 * @property int|null $active_key
 * @property int|null $handled_by_id
 * @property Carbon|null $handled_at
 * @property Carbon|null $resolved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['material_id', 'min_stock', 'current_quantity', 'status', 'active_key', 'handled_by_id', 'handled_at', 'resolved_at'])]
#[Table('mes_inventory_alerts')]
#[UseFactory(InventoryAlertFactory::class)]
class InventoryAlert extends Model
{
    /** @use HasFactory<InventoryAlertFactory> */
    use HasFactory, HasStatusLabels;

    public const string PENDING = 'pending';

    public const string CONFIRMED = 'confirmed';

    public const string CLOSED = 'closed';

    /** @var array<string, string> */
    public const array STATUS_LABELS = [
        self::PENDING => '待确认',
        self::CONFIRMED => '已确认',
        self::CLOSED => '已关闭',
    ];

    /** @return BelongsTo<Material, $this> */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }

    /** @return BelongsTo<User, $this> */
    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by_id');
    }

    protected static function booted(): void
    {
        static::saving(function (InventoryAlert $alert): void {
            $alert->active_key = match ($alert->status) {
                self::PENDING, self::CONFIRMED => 1,
                self::CLOSED => $alert->resolved_at === null ? 1 : null,
                default => null,
            };
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'min_stock' => 'decimal:3',
            'current_quantity' => 'decimal:3',
            'handled_by_id' => 'integer',
            'handled_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
