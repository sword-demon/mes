<?php

namespace App\Modules\Organization\Model;

use Database\Factories\ProductionLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $workshop_id
 * @property string $code
 * @property string $name
 * @property int $capacity
 * @property string|null $manager
 * @property bool $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['workshop_id', 'code', 'name', 'capacity', 'manager', 'status'])]
#[Table('mes_production_lines')]
#[UseFactory(ProductionLineFactory::class)]
class ProductionLine extends Model
{
    /** @use HasFactory<ProductionLineFactory> */
    use HasFactory;

    /**
     * Mirrors the column default so freshly built models expose it.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => true,
    ];

    /**
     * @return BelongsTo<Workshop, $this>
     */
    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class, 'workshop_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'status' => 'boolean',
        ];
    }
}
