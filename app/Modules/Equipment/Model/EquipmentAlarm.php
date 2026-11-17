<?php

namespace App\Modules\Equipment\Model;

use App\Core\Models\Concerns\HasStatusLabels;
use App\Modules\System\Model\User;
use Database\Factories\EquipmentAlarmFactory;
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
 * @property int $equipment_id
 * @property int|null $equipment_data_id
 * @property string|null $metric
 * @property string|null $alarm_code
 * @property string|null $message
 * @property string $level
 * @property string $status
 * @property float|null $threshold
 * @property float|null $measured_value
 * @property int|null $confirmed_by_id
 * @property Carbon|null $confirmed_at
 * @property int|null $handled_by_id
 * @property Carbon|null $handled_at
 * @property string|null $handling_note
 * @property Carbon $triggered_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'code',
    'equipment_id',
    'equipment_data_id',
    'metric',
    'alarm_code',
    'message',
    'level',
    'status',
    'threshold',
    'measured_value',
    'confirmed_by_id',
    'confirmed_at',
    'handled_by_id',
    'handled_at',
    'handling_note',
    'triggered_at',
    'closed_at',
])]
#[Table('mes_equipment_alarms', dateFormat: 'Y-m-d H:i:s.u')]
#[UseFactory(EquipmentAlarmFactory::class)]
class EquipmentAlarm extends Model
{
    /** @use HasFactory<EquipmentAlarmFactory> */
    use HasFactory, HasStatusLabels;

    public const string PENDING = 'pending';

    public const string CONFIRMED = 'confirmed';

    public const string PROCESSING = 'processing';

    public const string CLOSED = 'closed';

    public const array STATUSES = [
        self::PENDING,
        self::CONFIRMED,
        self::PROCESSING,
        self::CLOSED,
    ];

    public const array OPEN_STATUSES = [
        self::PENDING,
        self::CONFIRMED,
        self::PROCESSING,
    ];

    /** @var array<string, string> */
    public const array STATUS_LABELS = [
        self::PENDING => '待确认',
        self::CONFIRMED => '已确认',
        self::PROCESSING => '处理中',
        self::CLOSED => '已关闭',
    ];

    public const string LEVEL_INFO = 'info';

    public const string LEVEL_WARNING = 'warning';

    public const string LEVEL_CRITICAL = 'critical';

    public const array LEVELS = [
        self::LEVEL_INFO,
        self::LEVEL_WARNING,
        self::LEVEL_CRITICAL,
    ];

    /** @var array<string, int> */
    public const array LEVEL_RANKS = [
        self::LEVEL_INFO => 1,
        self::LEVEL_WARNING => 2,
        self::LEVEL_CRITICAL => 3,
    ];

    /** @var array<string, string> */
    public const array LEVEL_LABELS = [
        self::LEVEL_INFO => '提示',
        self::LEVEL_WARNING => '警告',
        self::LEVEL_CRITICAL => '严重',
    ];

    public const array METRICS = ['temperature', 'speed', 'pressure'];

    public const string ALARM_DEVICE_FAULT = 'device_fault';

    /** @var array<string, string> */
    public const array METRIC_ALARM_CODES = [
        'temperature' => 'over_temperature',
        'speed' => 'over_speed',
        'pressure' => 'over_pressure',
    ];

    public const array RELATIONS = ['equipment', 'confirmedBy', 'handledBy'];

    /** @var array<string, string> */
    public const array METRIC_LABELS = [
        'temperature' => '温度',
        'speed' => '转速',
        'pressure' => '压力',
    ];

    /** @var array<string, string> */
    public const array METRIC_UNITS = [
        'temperature' => '℃',
        'speed' => 'rpm',
        'pressure' => 'MPa',
    ];

    /**
     * 与模拟器告警口径一致：实测值严格大于阈值才触发对应等级。
     *
     * @var array<string, array<string, float>>
     */
    public const array THRESHOLDS = [
        'temperature' => [
            self::LEVEL_INFO => 60.0,
            self::LEVEL_WARNING => 64.0,
            self::LEVEL_CRITICAL => 68.0,
        ],
        'speed' => [
            self::LEVEL_INFO => 1200.0,
            self::LEVEL_WARNING => 1350.0,
            self::LEVEL_CRITICAL => 1450.0,
        ],
        'pressure' => [
            self::LEVEL_INFO => 0.70,
            self::LEVEL_WARNING => 0.80,
            self::LEVEL_CRITICAL => 0.85,
        ],
    ];

    public function levelLabel(): string
    {
        return self::LEVEL_LABELS[$this->level] ?? $this->level;
    }

    public function metricLabel(): string
    {
        return $this->metric === null
            ? '设备'
            : (self::METRIC_LABELS[$this->metric] ?? $this->metric);
    }

    public function metricUnit(): string
    {
        return $this->metric === null ? '' : (self::METRIC_UNITS[$this->metric] ?? '');
    }

    public static function alarmCodeForMetric(string $metric): string
    {
        return self::METRIC_ALARM_CODES[$metric] ?? $metric;
    }

    public static function metricForAlarmCode(string $alarmCode): ?string
    {
        $metric = array_search($alarmCode, self::METRIC_ALARM_CODES, true);

        return is_string($metric) ? $metric : null;
    }

    public static function messageForMetric(string $metric): string
    {
        return (self::METRIC_LABELS[$metric] ?? $metric).'超限';
    }

    /**
     * @return array{level: string, threshold: float}|null
     */
    public static function levelFor(string $metric, float $value): ?array
    {
        $matched = null;

        foreach (self::THRESHOLDS[$metric] ?? [] as $level => $threshold) {
            if ($value > $threshold) {
                $matched = ['level' => $level, 'threshold' => $threshold];
            }
        }

        return $matched;
    }

    /**
     * @return BelongsTo<Equipment, $this>
     */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class, 'equipment_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'equipment_id' => 'integer',
            'equipment_data_id' => 'integer',
            'threshold' => 'float',
            'measured_value' => 'float',
            'confirmed_by_id' => 'integer',
            'confirmed_at' => 'datetime',
            'handled_by_id' => 'integer',
            'handled_at' => 'datetime',
            'triggered_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }
}
