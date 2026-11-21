<?php

namespace App\Modules\System\Model;

use Database\Factories\OperationLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $user_name
 * @property string $action
 * @property string $action_name
 * @property string $object_type
 * @property int|null $object_id
 * @property string $object_name
 * @property string $summary
 * @property array<string, array{before: mixed, after: mixed}>|null $changes
 * @property string $source
 * @property string|null $trace_id
 * @property string $ip
 * @property Carbon|null $created_at
 */
#[Fillable([
    'user_id',
    'user_name',
    'action',
    'action_name',
    'object_type',
    'object_id',
    'object_name',
    'summary',
    'changes',
    'source',
    'trace_id',
    'ip',
])]
#[Table('sys_operation_logs')]
#[UseFactory(OperationLogFactory::class)]
class OperationLog extends Model
{
    /** @use HasFactory<OperationLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
