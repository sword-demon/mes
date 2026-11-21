<?php

namespace App\Modules\System\Model;

use Database\Factories\AiTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $trace_id
 * @property string $type
 * @property string $status
 * @property int|null $actor_id
 * @property string|null $actor_ip
 * @property string|null $model
 * @property array<string, mixed>|null $input
 * @property array<string, mixed>|null $output
 * @property string|null $error
 * @property int|null $prompt_tokens
 * @property int|null $completion_tokens
 * @property int|null $latency_ms
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'trace_id',
    'type',
    'status',
    'actor_id',
    'actor_ip',
    'model',
    'input',
    'output',
    'error',
    'prompt_tokens',
    'completion_tokens',
    'latency_ms',
    'started_at',
    'finished_at',
])]
#[Table('sys_ai_tasks')]
#[UseFactory(AiTaskFactory::class)]
class AiTask extends Model
{
    /** @use HasFactory<AiTaskFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public const TYPE_EXAMPLE = 'example';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'actor_id' => 'integer',
            'input' => 'array',
            'output' => 'array',
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'latency_ms' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
