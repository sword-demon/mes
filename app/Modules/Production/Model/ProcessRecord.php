<?php

namespace App\Modules\Production\Model;

use App\Modules\Organization\Model\Employee;
use App\Modules\Organization\Model\Team;
use App\Modules\Process\Model\RouteOperation;
use Database\Factories\ProcessRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $production_task_id
 * @property int $route_operation_id
 * @property int $sequence
 * @property string|null $sn
 * @property int|null $employee_id
 * @property int|null $team_id
 * @property int|null $equipment_id
 * @property string $status
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $inspected_at
 * @property Carbon|null $submitted_at
 * @property Carbon|null $received_at
 * @property string|null $exception_reason
 * @property Carbon|null $exception_at
 * @property string|null $rework_reason
 * @property Carbon|null $rework_at
 * @property int $rework_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'production_task_id',
    'route_operation_id',
    'sequence',
    'sn',
    'employee_id',
    'team_id',
    'equipment_id',
    'status',
    'started_at',
    'completed_at',
    'inspected_at',
    'submitted_at',
    'received_at',
    'exception_reason',
    'exception_at',
    'rework_reason',
    'rework_at',
    'rework_count',
])]
#[Table('mes_process_records')]
#[UseFactory(ProcessRecordFactory::class)]
class ProcessRecord extends Model
{
    /** @use HasFactory<ProcessRecordFactory> */
    use HasFactory;

    public const string PENDING = 'pending';

    public const string PROCESSING = 'processing';

    public const string INSPECTING = 'inspecting';

    public const string INSPECTED = 'inspected';

    public const string PENDING_TRANSFER = 'pending_transfer';

    public const string TRANSFERRED = 'transferred';

    public const string REWORKING = 'reworking';

    public const array STATUS_LABELS = [
        self::PENDING => '待加工',
        self::PROCESSING => '加工中',
        self::INSPECTING => '待检验',
        self::INSPECTED => '检验通过',
        self::PENDING_TRANSFER => '待流转',
        self::TRANSFERRED => '已流转',
        self::REWORKING => '返工中',
    ];

    /**
     * Mirrors the column defaults so freshly built models expose them.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::PENDING,
        'rework_count' => 0,
    ];

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function requiresInspection(): bool
    {
        return (bool) $this->routeOperation->process->requires_inspection;
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'equipment_id' => 'integer',
            'rework_count' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'inspected_at' => 'datetime',
            'submitted_at' => 'datetime',
            'received_at' => 'datetime',
            'exception_at' => 'datetime',
            'rework_at' => 'datetime',
        ];
    }
}
