<?php

namespace App\Modules\Quality\Model;

use App\Modules\System\Model\User;
use Database\Factories\ReworkOrderFactory;
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
 * @property int $defective_product_id
 * @property string $reason
 * @property int $created_by_id
 * @property string|null $recheck_result
 * @property string|null $recheck_remark
 * @property int|null $rechecked_by_id
 * @property Carbon|null $rechecked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'code',
    'defective_product_id',
    'reason',
    'created_by_id',
    'recheck_result',
    'recheck_remark',
    'rechecked_by_id',
    'rechecked_at',
])]
#[Table('mes_rework_orders')]
#[UseFactory(ReworkOrderFactory::class)]
class ReworkOrder extends Model
{
    /** @use HasFactory<ReworkOrderFactory> */
    use HasFactory;

    public const string PASSED = 'passed';

    public const string FAILED = 'failed';

    /** @return BelongsTo<DefectiveProduct, $this> */
    public function defectiveProduct(): BelongsTo
    {
        return $this->belongsTo(DefectiveProduct::class, 'defective_product_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recheckedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rechecked_by_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['rechecked_at' => 'datetime'];
    }
}
