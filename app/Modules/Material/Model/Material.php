<?php

namespace App\Modules\Material\Model;

use Database\Factories\MaterialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $type
 * @property string|null $specification
 * @property string $unit
 * @property float $min_stock
 * @property float $safety_stock
 * @property bool $batch_managed
 * @property bool $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'code',
    'name',
    'type',
    'specification',
    'unit',
    'min_stock',
    'safety_stock',
    'batch_managed',
    'status',
])]
#[Table('mes_materials')]
#[UseFactory(MaterialFactory::class)]
class Material extends Model
{
    /** @use HasFactory<MaterialFactory> */
    use HasFactory;

    /**
     * The types the material form offers; kept here so validation has one source.
     */
    public const array TYPES = ['原材料', '半成品', '成品', '辅助材料', '包装材料'];

    /**
     * Mirrors the column defaults so freshly built models expose them.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'min_stock' => 0,
        'safety_stock' => 0,
        'batch_managed' => false,
        'status' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_stock' => 'float',
            'safety_stock' => 'float',
            'batch_managed' => 'boolean',
            'status' => 'boolean',
        ];
    }
}
