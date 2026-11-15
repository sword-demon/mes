<?php

namespace App\Modules\Quality\Model;

use Database\Factories\InspectionItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $unit
 * @property bool $status
 * @property-read InspectionStandard|null $standard
 */
#[Fillable(['code', 'name', 'unit', 'status'])]
#[Table('mes_inspection_items')]
#[UseFactory(InspectionItemFactory::class)]
class InspectionItem extends Model
{
    /** @use HasFactory<InspectionItemFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = ['status' => true];

    /** @return HasOne<InspectionStandard, $this> */
    public function standard(): HasOne
    {
        return $this->hasOne(InspectionStandard::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => 'boolean'];
    }
}
