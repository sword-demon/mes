<?php

namespace App\Modules\Production\Model;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $production_exception_id
 * @property string $path
 * @property string|null $original_name
 */
#[Fillable(['path', 'original_name'])]
#[Table('mes_production_exception_images')]
class ProductionExceptionImage extends Model
{
    /** @return BelongsTo<ProductionException, $this> */
    public function productionException(): BelongsTo
    {
        return $this->belongsTo(ProductionException::class, 'production_exception_id');
    }
}
