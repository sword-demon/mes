<?php

namespace App\Modules\Quality\Model;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $inspection_task_id
 * @property string $path
 * @property string|null $original_name
 */
#[Fillable(['path', 'original_name'])]
#[Table('mes_inspection_images')]
class InspectionImage extends Model
{
    /** @return BelongsTo<InspectionTask, $this> */
    public function inspectionTask(): BelongsTo
    {
        return $this->belongsTo(InspectionTask::class);
    }
}
