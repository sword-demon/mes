<?php

namespace App\Modules\Material\Model;

use App\Modules\Production\Model\ProductionOrder;
use App\Modules\System\Model\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property int $requisition_id
 * @property int $production_order_id
 * @property string $status
 * @property string|null $remark
 * @property int $created_by_id
 * @property int|null $approved_by_id
 * @property Carbon|null $approved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['code', 'requisition_id', 'production_order_id', 'status', 'remark', 'created_by_id', 'approved_by_id', 'approved_at'])]
#[Table('mes_material_returns')]
class MaterialReturn extends Model
{
    public const string PENDING = 'pending';

    public const string APPROVED = 'approved';

    public const string CANCELLED = 'cancelled';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::PENDING,
    ];

    /**
     * @return BelongsTo<MaterialRequisition, $this>
     */
    public function requisition(): BelongsTo
    {
        return $this->belongsTo(MaterialRequisition::class, 'requisition_id');
    }

    /**
     * @return BelongsTo<ProductionOrder, $this>
     */
    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    /**
     * @return HasMany<MaterialReturnItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(MaterialReturnItem::class, 'material_return_id');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::APPROVED => '已审核',
            self::CANCELLED => '已撤销',
            default => '待审核',
        };
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
        ];
    }
}
