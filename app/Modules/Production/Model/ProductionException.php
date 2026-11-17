<?php

namespace App\Modules\Production\Model;

use App\Core\Models\Concerns\HasStatusLabels;
use App\Modules\System\Model\User;
use Database\Factories\ProductionExceptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $type
 * @property string $status
 * @property int|null $production_order_id
 * @property int|null $process_record_id
 * @property string $description
 * @property int|null $reported_by_id
 * @property int|null $handled_by_id
 * @property string|null $handling_result
 * @property Carbon $reported_at
 * @property Carbon|null $handled_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'code',
    'type',
    'status',
    'production_order_id',
    'process_record_id',
    'description',
    'reported_by_id',
    'handled_by_id',
    'handling_result',
    'reported_at',
    'handled_at',
    'closed_at',
])]
#[Table('mes_production_exceptions')]
#[UseFactory(ProductionExceptionFactory::class)]
class ProductionException extends Model
{
    /** @use HasFactory<ProductionExceptionFactory> */
    use HasFactory, HasStatusLabels;

    public const string TYPE_EQUIPMENT = 'equipment';

    public const string TYPE_MATERIAL = 'material';

    public const string TYPE_PROCESS = 'process';

    public const string TYPE_QUALITY = 'quality';

    public const string TYPE_PERSONNEL = 'personnel';

    public const array TYPES = [
        self::TYPE_EQUIPMENT,
        self::TYPE_MATERIAL,
        self::TYPE_PROCESS,
        self::TYPE_QUALITY,
        self::TYPE_PERSONNEL,
    ];

    /** @var array<string, string> */
    public const array TYPE_LABELS = [
        self::TYPE_EQUIPMENT => '设备异常',
        self::TYPE_MATERIAL => '物料异常',
        self::TYPE_PROCESS => '工艺异常',
        self::TYPE_QUALITY => '质量异常',
        self::TYPE_PERSONNEL => '人员异常',
    ];

    public const string REPORTED = 'reported';

    public const string PROCESSING = 'processing';

    public const string CLOSED = 'closed';

    public const array STATUSES = [
        self::REPORTED,
        self::PROCESSING,
        self::CLOSED,
    ];

    /** @var array<string, string> */
    public const array STATUS_LABELS = [
        self::REPORTED => '已上报',
        self::PROCESSING => '处理中',
        self::CLOSED => '已关闭',
    ];

    public const array RELATIONS = [
        'productionOrder.product',
        'processRecord.routeOperation.process',
        'reporter',
        'handler',
        'images',
    ];

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    /**
     * @return BelongsTo<ProductionOrder, $this>
     */
    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    /**
     * @return BelongsTo<ProcessRecord, $this>
     */
    public function processRecord(): BelongsTo
    {
        return $this->belongsTo(ProcessRecord::class, 'process_record_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by_id');
    }

    /**
     * @return HasMany<ProductionExceptionImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductionExceptionImage::class, 'production_exception_id')->orderBy('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'production_order_id' => 'integer',
            'process_record_id' => 'integer',
            'reported_by_id' => 'integer',
            'handled_by_id' => 'integer',
            'reported_at' => 'datetime',
            'handled_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }
}
