<?php

namespace App\Modules\Bom\Model;

use App\Modules\Material\Model\Material;
use Database\Factories\BomItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $bom_id
 * @property int $material_id
 * @property float $quantity
 * @property float $loss_rate
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['bom_id', 'material_id', 'quantity', 'loss_rate'])]
#[Table('mes_bom_items')]
#[UseFactory(BomItemFactory::class)]
class BomItem extends Model
{
    /** @use HasFactory<BomItemFactory> */
    use HasFactory;

    /**
     * Mirrors the column default so freshly built models expose it.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'loss_rate' => 0,
    ];

    /**
     * @return BelongsTo<Bom, $this>
     */
    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class, 'bom_id');
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
            'quantity' => 'float',
            'loss_rate' => 'float',
        ];
    }
}
