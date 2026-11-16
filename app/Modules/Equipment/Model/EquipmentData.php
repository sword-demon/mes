<?php

namespace App\Modules\Equipment\Model;

use Database\Factories\EquipmentDataFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $equipment_id
 * @property string $message_type
 * @property Carbon $recorded_at
 * @property string $status
 * @property float $temperature
 * @property int $speed
 * @property float $pressure
 * @property int $output
 * @property int|null $runtime_seconds
 * @property array{code: string, message: string, level: string}|null $alarm
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['equipment_id', 'message_type', 'recorded_at', 'status', 'temperature', 'speed', 'pressure', 'output', 'runtime_seconds', 'alarm'])]
#[Table('mes_equipment_data', dateFormat: 'Y-m-d H:i:s.u')]
#[UseFactory(EquipmentDataFactory::class)]
class EquipmentData extends Model
{
    /** @use HasFactory<EquipmentDataFactory> */
    use HasFactory;

    public const string TYPE_DATA = 'data';

    public const string TYPE_STATUS = 'status';

    public const string TYPE_ALARM = 'alarm';

    public const array MESSAGE_TYPES = [self::TYPE_STATUS, self::TYPE_DATA, self::TYPE_ALARM];

    public const int MAX_HISTORY_DAYS = 31;

    public const int MAX_HISTORY_SECONDS = self::MAX_HISTORY_DAYS * 86400;

    public const int HISTORY_POINT_LIMIT = 500;

    /**
     * @return BelongsTo<Equipment, $this>
     */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class, 'equipment_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'equipment_id' => 'integer',
            'recorded_at' => 'datetime',
            'temperature' => 'float',
            'speed' => 'integer',
            'pressure' => 'float',
            'output' => 'integer',
            'runtime_seconds' => 'integer',
            'alarm' => 'array',
        ];
    }
}
