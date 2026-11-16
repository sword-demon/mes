<?php

namespace App\Modules\Equipment\Model;

use App\Modules\Organization\Model\Employee;
use App\Modules\Organization\Model\ProductionLine;
use App\Modules\Organization\Model\Workshop;
use Database\Factories\EquipmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $type
 * @property int $workshop_id
 * @property int $production_line_id
 * @property string $model
 * @property string $status
 * @property bool $enabled
 * @property int|null $owner_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['code', 'name', 'type', 'workshop_id', 'production_line_id', 'model', 'status', 'enabled', 'owner_id'])]
#[Table('mes_equipments')]
#[UseFactory(EquipmentFactory::class)]
class Equipment extends Model
{
    /** @use HasFactory<EquipmentFactory> */
    use HasFactory;

    public const string RUNNING = 'running';

    public const string IDLE = 'idle';

    public const string FAULT = 'fault';

    public const string REPAIR = 'repair';

    public const string OFFLINE = 'offline';

    public const array STATUSES = [
        self::RUNNING,
        self::IDLE,
        self::FAULT,
        self::REPAIR,
        self::OFFLINE,
    ];

    public const array STATUS_LABELS = [
        self::RUNNING => '运行',
        self::IDLE => '待机',
        self::FAULT => '故障',
        self::REPAIR => '维修',
        self::OFFLINE => '离线',
    ];

    public const array TYPES = [
        'SMT 贴片机',
        '回流焊设备',
        'CNC 加工中心',
        '自动组装设备',
        '功能测试设备',
    ];

    /**
     * Mirrors the column default so freshly built models expose it.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::IDLE,
        'enabled' => true,
    ];

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /**
     * @param  Builder<Equipment>  $query
     * @return Builder<Equipment>
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->enabled()->whereIn('status', [self::RUNNING, self::IDLE]);
    }

    /**
     * @param  Builder<Equipment>  $query
     * @return Builder<Equipment>
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    /**
     * @return BelongsTo<Workshop, $this>
     */
    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class, 'workshop_id');
    }

    /**
     * @return BelongsTo<ProductionLine, $this>
     */
    public function productionLine(): BelongsTo
    {
        return $this->belongsTo(ProductionLine::class, 'production_line_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'owner_id');
    }

    /**
     * @return HasOne<EquipmentData, $this>
     */
    public function latestData(): HasOne
    {
        return $this->hasOne(EquipmentData::class)
            ->ofMany(
                ['recorded_at' => 'max'],
                fn (Builder $query) => $query->where('message_type', EquipmentData::TYPE_DATA),
            );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'owner_id' => 'integer',
        ];
    }
}
