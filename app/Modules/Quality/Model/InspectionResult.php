<?php

namespace App\Modules\Quality\Model;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $inspection_task_id
 * @property int $inspection_item_id
 * @property string $measured_value
 * @property string $standard_value
 * @property string $min_value
 * @property string $max_value
 * @property bool $passed
 */
#[Fillable([
    'inspection_item_id',
    'measured_value',
    'standard_value',
    'min_value',
    'max_value',
    'passed',
])]
#[Table('mes_inspection_results')]
class InspectionResult extends Model
{
    /** @return BelongsTo<InspectionTask, $this> */
    public function inspectionTask(): BelongsTo
    {
        return $this->belongsTo(InspectionTask::class);
    }

    /** @return BelongsTo<InspectionItem, $this> */
    public function inspectionItem(): BelongsTo
    {
        return $this->belongsTo(InspectionItem::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'measured_value' => 'decimal:3',
            'standard_value' => 'decimal:3',
            'min_value' => 'decimal:3',
            'max_value' => 'decimal:3',
            'passed' => 'boolean',
        ];
    }
}
