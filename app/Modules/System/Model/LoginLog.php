<?php

namespace App\Modules\System\Model;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $account
 * @property string $ip
 * @property bool $success
 * @property string $message
 * @property Carbon|null $created_at
 */
#[Fillable(['user_id', 'account', 'ip', 'success', 'message'])]
#[Table('sys_login_logs')]
class LoginLog extends Model
{
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
            'success' => 'boolean',
            'created_at' => 'datetime',
        ];
    }
}
