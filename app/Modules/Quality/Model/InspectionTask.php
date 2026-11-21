<?php

namespace App\Modules\Quality\Model;

use App\Core\DataScope\DataScope;
use App\Core\DataScope\DataScopeQuery;
use App\Core\DataScope\HasDataScope;
use App\Modules\Process\Model\RouteOperation;
use App\Modules\Production\Model\ProcessRecord;
use App\Modules\Production\Model\ProductionOrder;
use App\Modules\System\Model\User;
use Database\Factories\InspectionTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $production_order_id
 * @property int|null $process_record_id
 * @property int|null $route_operation_id
 * @property int $inspection_cycle
 * @property string $inspection_type
 * @property string|null $result
 * @property int|null $inspector_id
 * @property Carbon|null $inspected_at
 * @property string|null $remark
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'production_order_id',
    'process_record_id',
    'route_operation_id',
    'inspection_cycle',
    'inspection_type',
    'result',
    'inspector_id',
    'inspected_at',
    'remark',
])]
#[Table('mes_inspection_tasks')]
#[UseFactory(InspectionTaskFactory::class)]
class InspectionTask extends Model implements HasDataScope
{
    /** @use HasFactory<InspectionTaskFactory> */
    use HasFactory;

    public const string IPQC = 'ipqc';

    public const string OQC = 'oqc';

    public const string PENDING = 'pending';

    public const string PASSED = 'passed';

    public const string FAILED = 'failed';

    public const string EXPIRED = 'expired';

    public const array TYPE_LABELS = [
        self::IPQC => 'IPQC 过程检验',
        self::OQC => 'OQC 出货检验',
    ];

    public const array RESULT_LABELS = [
        self::PENDING => '待检验',
        self::PASSED => '合格',
        self::FAILED => '不合格',
        self::EXPIRED => '已过期',
    ];

    public function status(): string
    {
        return $this->result ?? self::PENDING;
    }

    public function statusLabel(): string
    {
        return self::RESULT_LABELS[$this->status()];
    }

    /** @return BelongsTo<ProductionOrder, $this> */
    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    /**
     * An inspection task is anchored on its order; the execution only ties
     * the IPQC cycle to a step, so the order's line decides visibility.
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

    /** @return BelongsTo<ProcessRecord, $this> */
    public function processRecord(): BelongsTo
    {
        return $this->belongsTo(ProcessRecord::class, 'process_record_id');
    }

    /** @return BelongsTo<RouteOperation, $this> */
    public function routeOperation(): BelongsTo
    {
        return $this->belongsTo(RouteOperation::class, 'route_operation_id');
    }

    /** @return BelongsTo<User, $this> */
    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }

    /** @return HasMany<InspectionResult, $this> */
    public function results(): HasMany
    {
        return $this->hasMany(InspectionResult::class)->orderBy('id');
    }

    /** @return HasMany<InspectionImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(InspectionImage::class)->orderBy('id');
    }

    /** @return BelongsToMany<InspectionItem, $this> */
    public function requiredItems(): BelongsToMany
    {
        return $this->belongsToMany(InspectionItem::class, 'mes_inspection_task_items', 'inspection_task_id', 'inspection_item_id')
            ->withTimestamps();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'inspection_cycle' => 'integer',
            'inspected_at' => 'datetime',
        ];
    }
}
