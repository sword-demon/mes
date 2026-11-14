<?php

namespace App\Modules\Process\Model;

use Database\Factories\RouteOperationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $route_id
 * @property int $process_id
 * @property int $sequence
 * @property float $standard_hours
 * @property string|null $parameters
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['route_id', 'process_id', 'sequence', 'standard_hours', 'parameters'])]
#[Table('mes_route_operations')]
#[UseFactory(RouteOperationFactory::class)]
class RouteOperation extends Model
{
    /** @use HasFactory<RouteOperationFactory> */
    use HasFactory;

    /**
     * Mirrors the column default so freshly built models expose it.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'standard_hours' => 0,
    ];

    /**
     * @return BelongsTo<ProcessRoute, $this>
     */
    public function route(): BelongsTo
    {
        return $this->belongsTo(ProcessRoute::class, 'route_id');
    }

    /**
     * @return BelongsTo<Process, $this>
     */
    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class, 'process_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'standard_hours' => 'float',
        ];
    }
}
