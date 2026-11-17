<?php

namespace App\Modules\Quality\Model;

use App\Core\Models\Concerns\HasStatusLabels;
use App\Modules\Process\Model\RouteOperation;
use App\Modules\Production\Model\ProductionBatch;
use App\Modules\Production\Model\ProductionOrder;
use App\Modules\System\Model\User;
use Database\Factories\DefectiveProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $production_order_id
 * @property int $route_operation_id
 * @property int $production_batch_id
 * @property int $defect_code_id
 * @property string $quantity
 * @property string|null $remark
 * @property string $status
 * @property int|null $handled_by_id
 * @property string|null $scrap_reason
 * @property string|null $scrap_quantity
 * @property Carbon|null $scrapped_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'production_order_id',
    'route_operation_id',
    'production_batch_id',
    'defect_code_id',
    'quantity',
    'remark',
    'status',
    'handled_by_id',
    'scrap_reason',
    'scrap_quantity',
    'scrapped_at',
])]
#[Table('mes_defective_products')]
#[UseFactory(DefectiveProductFactory::class)]
class DefectiveProduct extends Model
{
    /** @use HasFactory<DefectiveProductFactory> */
    use HasFactory, HasStatusLabels;

    public const string PENDING = 'pending';

    public const string PROCESSING = 'processing';

    public const string PENDING_RECHECK = 'pending_recheck';

    public const string CLOSED = 'closed';

    public const array STATUS_LABELS = [
        self::PENDING => '待处理',
        self::PROCESSING => '处理中',
        self::PENDING_RECHECK => '待复检',
        self::CLOSED => '已关闭',
    ];

    /** @return BelongsTo<ProductionOrder, $this> */
    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    /** @return BelongsTo<RouteOperation, $this> */
    public function routeOperation(): BelongsTo
    {
        return $this->belongsTo(RouteOperation::class, 'route_operation_id');
    }

    /** @return BelongsTo<ProductionBatch, $this> */
    public function productionBatch(): BelongsTo
    {
        return $this->belongsTo(ProductionBatch::class, 'production_batch_id');
    }

    /** @return BelongsTo<DefectCode, $this> */
    public function defectCode(): BelongsTo
    {
        return $this->belongsTo(DefectCode::class, 'defect_code_id');
    }

    /** @return BelongsTo<User, $this> */
    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by_id');
    }

    /** @return HasMany<ReworkOrder, $this> */
    public function reworkOrders(): HasMany
    {
        return $this->hasMany(ReworkOrder::class)->orderBy('id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'scrap_quantity' => 'decimal:3',
            'scrapped_at' => 'datetime',
        ];
    }
}
