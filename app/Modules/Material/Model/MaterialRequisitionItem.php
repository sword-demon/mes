<?php

namespace App\Modules\Material\Model;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $requisition_id
 * @property int $material_id
 * @property float $requested_quantity
 * @property float $issued_quantity
 * @property bool $over_requirement
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['requisition_id', 'material_id', 'requested_quantity', 'issued_quantity', 'over_requirement'])]
#[Table('mes_material_requisition_items')]
class MaterialRequisitionItem extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'issued_quantity' => 0,
        'over_requirement' => false,
    ];

    /**
     * @return BelongsTo<MaterialRequisition, $this>
     */
    public function requisition(): BelongsTo
    {
        return $this->belongsTo(MaterialRequisition::class, 'requisition_id');
    }

    /**
     * @return BelongsTo<Material, $this>
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requested_quantity' => 'decimal:3',
            'issued_quantity' => 'decimal:3',
            'over_requirement' => 'boolean',
        ];
    }
}
