<?php

namespace App\Modules\Production\Model;

use App\Core\Exceptions\ApiException;
use App\Core\Models\Concerns\HasStatusLabels;
use App\Modules\Organization\Model\Employee;
use App\Modules\Organization\Model\ProductionLine;
use App\Modules\Organization\Model\Team;
use App\Modules\Process\Model\ProcessRoute;
use App\Modules\Product\Model\Product;
use Database\Factories\ProductionTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property int $production_order_id
 * @property int $product_id
 * @property int $route_id
 * @property int|null $production_line_id
 * @property int|null $team_id
 * @property int|null $owner_id
 * @property string $planned_quantity
 * @property string $produced_quantity
 * @property string $qualified_quantity
 * @property string $defective_quantity
 * @property Carbon $planned_start_at
 * @property Carbon|null $planned_end_at
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'code',
    'production_order_id',
    'product_id',
    'route_id',
    'production_line_id',
    'team_id',
    'owner_id',
    'planned_quantity',
    'planned_start_at',
    'planned_end_at',
    'status',
])]
#[Table('mes_production_tasks')]
#[UseFactory(ProductionTaskFactory::class)]
class ProductionTask extends Model
{
    /** @use HasFactory<ProductionTaskFactory> */
    use HasFactory, HasStatusLabels;

    public const string PENDING = 'pending';

    public const string IN_PROGRESS = 'in_progress';

    public const string PAUSED = 'paused';

    public const string COMPLETED = 'completed';

    /**
     * Assignment moves a pending task into production; a task may only
     * complete once it has started.
     *
     * @var array<string, array<int, string>>
     */
    public const array TRANSITIONS = [
        self::PENDING => [self::IN_PROGRESS],
        self::IN_PROGRESS => [self::PAUSED, self::COMPLETED],
        self::PAUSED => [self::IN_PROGRESS, self::COMPLETED],
        self::COMPLETED => [],
    ];

    public const array STATUS_LABELS = [
        self::PENDING => '待分配',
        self::IN_PROGRESS => '生产中',
        self::PAUSED => '已暂停',
        self::COMPLETED => '已完成',
    ];

    /**
     * Mirrors the column defaults so freshly built models expose them.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::PENDING,
        'produced_quantity' => 0,
        'qualified_quantity' => 0,
        'defective_quantity' => 0,
    ];

    /**
     * Derived from reported quantities so every report automatically moves the
     * progress bar without a second column that could drift.
     */
    public function progressPercent(): float
    {
        $planned = (float) $this->planned_quantity;

        if ($planned <= 0) {
            return 0.0;
        }

        return min(100.0, round((float) $this->produced_quantity / $planned * 100, 1));
    }

    /**
     * @throws ApiException
     */
    public function transitionTo(string $target): void
    {
        if (! in_array($target, self::TRANSITIONS[$this->status] ?? [], true)) {
            $label = self::STATUS_LABELS[$target] ?? $target;

            throw new ApiException("生产任务当前状态为「{$this->statusLabel()}」，不能变更为「{$label}」", 422);
        }

        $this->update(['status' => $target]);
    }

    /**
     * @return BelongsTo<ProductionOrder, $this>
     */
    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
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
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'owner_id');
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
