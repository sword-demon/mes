<?php

namespace App\Modules\Production\Model;

use App\Modules\Organization\Model\Employee;
use App\Modules\Organization\Model\Team;
use App\Modules\Process\Model\RouteOperation;
use Database\Factories\ProductionDispatchFactory;
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
 * @property int $production_task_id
 * @property int $route_operation_id
 * @property int $employee_id
 * @property int $team_id
 * @property Carbon $dispatched_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'code',
    'production_task_id',
    'route_operation_id',
    'employee_id',
    'team_id',
    'dispatched_at',
])]
#[Table('mes_dispatch_orders')]
#[UseFactory(ProductionDispatchFactory::class)]
class ProductionDispatch extends Model
{
    /** @use HasFactory<ProductionDispatchFactory> */
    use HasFactory;

    public static function codeFor(string $taskCode, int $sequence): string
    {
        return $taskCode.'-D'.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT);
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
        return ['dispatched_at' => 'datetime'];
    }
}
