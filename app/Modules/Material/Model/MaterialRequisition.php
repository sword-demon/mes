<?php

namespace App\Modules\Material\Model;

use App\Core\DataScope\DataScope;
use App\Core\DataScope\DataScopeQuery;
use App\Core\DataScope\HasDataScope;
use App\Modules\Production\Model\ProductionOrder;
use App\Modules\System\Model\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property int $production_order_id
 * @property string $status
 * @property string|null $remark
 * @property int $created_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['code', 'production_order_id', 'status', 'remark', 'created_by_id'])]
#[Table('mes_material_requisitions')]
class MaterialRequisition extends Model implements HasDataScope
{
    public const string PENDING = 'pending';

    public const string ISSUED = 'issued';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::PENDING,
    ];

    /**
     * @return BelongsTo<ProductionOrder, $this>
     */
    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    /**
     * Requisitions hang off an order, so the order's line decides visibility.
     *
     * @param  Builder<self>  $query
     */
    public function scopeInDataScope(Builder $query, DataScope $scope): void
    {
        if ($scope->isAll()) {
            return;
        }

        $lineIds = DataScopeQuery::scopedProductionLineIds($scope);

        if ($lineIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereIn(
            $this->getTable().'.production_order_id',
            ProductionOrder::query()->whereIn('production_line_id', $lineIds)->select('id'),
        );
    }

    public function dataScopeProductionLineId(): ?int
    {
        return $this->productionOrder?->dataScopeProductionLineId();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * @return HasMany<MaterialRequisitionItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(MaterialRequisitionItem::class, 'requisition_id');
    }
}
