<?php

namespace App\Modules\Material\Model;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $material_return_id
 * @property int $requisition_item_id
 * @property float $quantity
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['material_return_id', 'requisition_item_id', 'quantity'])]
#[Table('mes_material_return_items')]
class MaterialReturnItem extends Model
{
    /**
     * @return BelongsTo<MaterialReturn, $this>
     */
    public function materialReturn(): BelongsTo
    {
        return $this->belongsTo(MaterialReturn::class, 'material_return_id');
    }

    /**
     * @return BelongsTo<MaterialRequisitionItem, $this>
     */
    public function requisitionItem(): BelongsTo
    {
        return $this->belongsTo(MaterialRequisitionItem::class, 'requisition_item_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
        ];
    }
}
