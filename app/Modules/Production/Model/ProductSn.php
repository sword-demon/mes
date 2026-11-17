<?php

namespace App\Modules\Production\Model;

use App\Core\Models\Concerns\HasStatusLabels;
use App\Modules\Equipment\Model\Equipment;
use App\Modules\Organization\Model\Employee;
use Database\Factories\ProductSnFactory;
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
 * @property string $sn
 * @property int $production_order_id
 * @property int $production_batch_id
 * @property string|null $material_batch_no
 * @property int|null $equipment_id
 * @property int|null $employee_id
 * @property string $status
 * @property Carbon|null $printed_at
 * @property Carbon|null $voided_at
 * @property string|null $void_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'sn',
    'production_order_id',
    'production_batch_id',
    'material_batch_no',
    'equipment_id',
    'employee_id',
    'status',
    'printed_at',
    'voided_at',
    'void_reason',
])]
#[Table('mes_product_sns')]
#[UseFactory(ProductSnFactory::class)]
class ProductSn extends Model
{
    /** @use HasFactory<ProductSnFactory> */
    use HasFactory, HasStatusLabels;

    public const string ACTIVE = 'active';

    public const string VOIDED = 'voided';

    public const array STATUS_LABELS = [
        self::ACTIVE => '在用',
        self::VOIDED => '已作废',
    ];

    /**
     * Mirrors the column default so freshly built models expose it.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::ACTIVE,
    ];

    /**
     * Serial numbers are addressed by their printed code in the URL.
     */
    public function getRouteKeyName(): string
    {
        return 'sn';
    }

    public function isVoided(): bool
    {
        return $this->status === self::VOIDED;
    }

    /**
     * @return BelongsTo<ProductionOrder, $this>
     */
    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    /**
     * @return BelongsTo<ProductionBatch, $this>
     */
    public function productionBatch(): BelongsTo
    {
        return $this->belongsTo(ProductionBatch::class, 'production_batch_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * @return BelongsTo<Equipment, $this>
     */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class, 'equipment_id');
    }

    /**
     * Executions link to the SN by its printed code, not by a foreign key.
     *
     * shortcut: one row per task+operation, so a multi-unit task shares this
     * status; upgrade when per-unit routing is required.
     *
     * @return HasMany<ProcessRecord, $this>
     */
    public function processRecords(): HasMany
    {
        return $this->hasMany(ProcessRecord::class, 'sn', 'sn')->orderBy('sequence');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'production_order_id' => 'integer',
            'production_batch_id' => 'integer',
            'equipment_id' => 'integer',
            'employee_id' => 'integer',
            'printed_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }
}
