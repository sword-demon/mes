<?php

namespace App\Modules\Production\Model;

use App\Core\Exceptions\ApiException;
use App\Core\Models\Concerns\HasStatusLabels;
use App\Modules\Bom\Model\Bom;
use App\Modules\Organization\Model\ProductionLine;
use App\Modules\Process\Model\ProcessRoute;
use App\Modules\Product\Model\Product;
use App\Modules\Product\Model\ProductVersion;
use Database\Factories\ProductionOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property int $product_id
 * @property int $product_version_id
 * @property int $bom_id
 * @property int $route_id
 * @property int $production_line_id
 * @property string $bom_version
 * @property string $route_version
 * @property string $planned_quantity
 * @property string $produced_quantity
 * @property string $qualified_quantity
 * @property string $defective_quantity
 * @property Carbon $planned_start_at
 * @property Carbon|null $planned_end_at
 * @property string $status
 * @property string|null $remark
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'code',
    'product_id',
    'product_version_id',
    'bom_id',
    'route_id',
    'production_line_id',
    'bom_version',
    'route_version',
    'planned_quantity',
    'planned_start_at',
    'planned_end_at',
    'status',
    'remark',
])]
#[Table('mes_production_orders')]
#[UseFactory(ProductionOrderFactory::class)]
class ProductionOrder extends Model
{
    /** @use HasFactory<ProductionOrderFactory> */
    use HasFactory, HasStatusLabels;

    public const string DRAFT = 'draft';

    public const string PENDING = 'pending';

    public const string APPROVED = 'approved';

    public const string RELEASED = 'released';

    public const string IN_PROGRESS = 'in_progress';

    public const string PAUSED = 'paused';

    public const string COMPLETED = 'completed';

    public const string CLOSED = 'closed';

    public const string CANCELLED = 'cancelled';

    /**
     * Capability sets stay separate even while they match: one rule may change
     * without silently changing the other two.
     *
     * Orders that accept production reports.
     *
     * @var array<int, string>
     */
    public const array REPORTABLE_STATUSES = [
        self::RELEASED,
        self::IN_PROGRESS,
        self::PAUSED,
    ];

    /**
     * Orders that may issue serial numbers.
     *
     * @var array<int, string>
     */
    public const array GENERATABLE_STATUSES = [
        self::RELEASED,
        self::IN_PROGRESS,
        self::PAUSED,
    ];

    /**
     * Orders that may create material requisitions.
     *
     * @var array<int, string>
     */
    public const array REQUISITIONABLE_STATUSES = [
        self::RELEASED,
        self::IN_PROGRESS,
        self::PAUSED,
    ];

    /**
     * The only legal order actions; the source statuses and target status are defined in one place.
     *
     * @var array<string, array{from: array<int, string>, to: string}>
     */
    public const array ACTIONS = [
        'submit' => ['from' => [self::DRAFT], 'to' => self::PENDING],
        'approve' => ['from' => [self::PENDING], 'to' => self::APPROVED],
        'release' => ['from' => [self::APPROVED], 'to' => self::RELEASED],
        'start' => ['from' => [self::RELEASED], 'to' => self::IN_PROGRESS],
        'pause' => ['from' => [self::IN_PROGRESS], 'to' => self::PAUSED],
        'complete' => ['from' => [self::IN_PROGRESS], 'to' => self::COMPLETED],
        'resume' => ['from' => [self::PAUSED], 'to' => self::IN_PROGRESS],
        'close' => ['from' => [self::COMPLETED], 'to' => self::CLOSED],
        'cancel' => [
            'from' => [self::DRAFT, self::PENDING, self::APPROVED, self::RELEASED, self::IN_PROGRESS, self::PAUSED],
            'to' => self::CANCELLED,
        ],
    ];

    public const array STATUS_LABELS = [
        self::DRAFT => '草稿',
        self::PENDING => '待审核',
        self::APPROVED => '已审核',
        self::RELEASED => '已下达',
        self::IN_PROGRESS => '生产中',
        self::PAUSED => '已暂停',
        self::COMPLETED => '已完成',
        self::CLOSED => '已关闭',
        self::CANCELLED => '已取消',
    ];

    /**
     * Mirrors the column defaults so freshly built models expose them.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::DRAFT,
        'produced_quantity' => 0,
        'qualified_quantity' => 0,
        'defective_quantity' => 0,
    ];

    /**
     * Only a draft may still change; submitting locks the order.
     *
     * @throws ApiException
     */
    public function ensureEditable(): void
    {
        if ($this->isEditable()) {
            return;
        }

        throw new ApiException('生产订单已提交，不可修改', 403, status: 403);
    }

    public function isEditable(): bool
    {
        return $this->status === self::DRAFT;
    }

    public function isReportable(): bool
    {
        return in_array($this->status, self::REPORTABLE_STATUSES, true);
    }

    public function canGenerateSn(): bool
    {
        return in_array($this->status, self::GENERATABLE_STATUSES, true);
    }

    public function isRequisitionable(): bool
    {
        return in_array($this->status, self::REQUISITIONABLE_STATUSES, true);
    }

    public function canSplit(): bool
    {
        return $this->status === self::RELEASED;
    }

    /**
     * @return array<int, string>
     */
    public function allowedActions(): array
    {
        return array_keys(array_filter(
            self::ACTIONS,
            fn (array $action): bool => in_array($this->status, $action['from'], true),
        ));
    }

    /**
     * @throws ApiException
     */
    public function applyAction(string $action): void
    {
        $definition = self::ACTIONS[$action] ?? null;

        if ($definition === null || ! in_array($this->status, $definition['from'], true)) {
            $label = $definition === null
                ? $action
                : self::STATUS_LABELS[$definition['to']];

            throw new ApiException("生产订单当前状态为「{$this->statusLabel()}」，不能变更为「{$label}」", 422);
        }

        $this->update(['status' => $definition['to']]);
    }

    /**
     * @param  Builder<ProductionOrder>  $query
     */
    public function scopeCountable(Builder $query): void
    {
        $query->where('status', '!=', self::CANCELLED);
    }

    /**
     * @param  Builder<ProductionOrder>  $query
     */
    public function scopeReportable(Builder $query): void
    {
        $query->whereIn('status', self::REPORTABLE_STATUSES);
    }

    /**
     * @param  Builder<ProductionOrder>  $query
     */
    public function scopeGeneratableSn(Builder $query): void
    {
        $query->whereIn('status', self::GENERATABLE_STATUSES);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * @return BelongsTo<ProductVersion, $this>
     */
    public function productVersion(): BelongsTo
    {
        return $this->belongsTo(ProductVersion::class, 'product_version_id');
    }

    /**
     * @return BelongsTo<Bom, $this>
     */
    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class, 'bom_id');
    }

    /**
     * @return BelongsTo<ProcessRoute, $this>
     */
    public function route(): BelongsTo
    {
        return $this->belongsTo(ProcessRoute::class, 'route_id');
    }

    /**
     * @return BelongsTo<ProductionLine, $this>
     */
    public function productionLine(): BelongsTo
    {
        return $this->belongsTo(ProductionLine::class, 'production_line_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'planned_quantity' => 'decimal:3',
            'produced_quantity' => 'decimal:3',
            'qualified_quantity' => 'decimal:3',
            'defective_quantity' => 'decimal:3',
            'planned_start_at' => 'datetime',
            'planned_end_at' => 'datetime',
        ];
    }
}
