<?php

namespace App\Modules\Quality\Model;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $inspection_item_id
 * @property string $standard_value
 * @property string $min_value
 * @property string $max_value
 */
#[Fillable(['inspection_item_id', 'standard_value', 'min_value', 'max_value'])]
#[Table('mes_inspection_standards')]
class InspectionStandard extends Model
{
    /** @return BelongsTo<InspectionItem, $this> */
    public function inspectionItem(): BelongsTo
    {
        return $this->belongsTo(InspectionItem::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['standard_value' => 'decimal:3', 'min_value' => 'decimal:3', 'max_value' => 'decimal:3'];
    }
}
