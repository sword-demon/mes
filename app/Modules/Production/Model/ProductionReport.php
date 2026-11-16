<?php

namespace App\Modules\Production\Model;

use App\Modules\Equipment\Model\Equipment;
use App\Modules\Organization\Model\Employee;
use App\Modules\Organization\Model\Team;
use App\Modules\Process\Model\RouteOperation;
use Database\Factories\ProductionReportFactory;
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
 * @property int $production_task_id
 * @property int $route_operation_id
 * @property int $employee_id
 * @property int|null $team_id
 * @property int|null $equipment_id
 * @property string $quantity
 * @property string $qualified_quantity
 * @property string $defective_quantity
 * @property string $type
 * @property Carbon $reported_at
 * @property Carbon|null $reversed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'code',
    'production_task_id',
    'route_operation_id',
    'employee_id',
    'team_id',
    'equipment_id',
    'quantity',
    'qualified_quantity',
    'defective_quantity',
    'type',
    'reported_at',
    'reversed_at',
])]
#[Table('mes_production_reports')]
#[UseFactory(ProductionReportFactory::class)]
class ProductionReport extends Model
{
    /** @use HasFactory<ProductionReportFactory> */
    use HasFactory;

    public const string NORMAL = 'normal';

    public const string BATCH = 'batch';

    public const string REVERSED = 'reversed';

    public const array TYPE_LABELS = [
        self::NORMAL => '正常',
        self::BATCH => '批量',
        self::REVERSED => '已冲销',
    ];

    /**
     * Mirrors the column default so freshly built models expose it.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => self::NORMAL,
    ];

    public static function codeFor(string $taskCode, int $sequence): string
    {
        return $taskCode.'-R'.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT);
    }

    /**
     * @param  Builder<ProductionReport>  $query
     */
    public function scopeEffective(Builder $query): void
    {
        $query->where($query->qualifyColumn('type'), '!=', self::REVERSED)
            ->whereHas(
                'productionTask.productionOrder',
                fn (Builder $order) => $order->where('status', '!=', ProductionOrder::CANCELLED),
            );
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    /**
     * @return BelongsTo<ProductionTask, $this>
     */
    public function productionTask(): BelongsTo
    {
        return $this->belongsTo(ProductionTask::class, 'production_task_id');
    }

    /**
     * @return BelongsTo<RouteOperation, $this>
     */
    public function routeOperation(): BelongsTo
    {
        return $this->belongsTo(RouteOperation::class, 'route_operation_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    /**
     * @return BelongsTo<Equipment, $this>
     */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class, 'equipment_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'qualified_quantity' => 'decimal:3',
            'defective_quantity' => 'decimal:3',
            'equipment_id' => 'integer',
            'reported_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }
}
