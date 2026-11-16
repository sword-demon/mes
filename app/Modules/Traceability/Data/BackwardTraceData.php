<?php

namespace App\Modules\Traceability\Data;

use App\Modules\Material\Model\Material;
use Illuminate\Support\Collection;

final readonly class BackwardTraceData
{
    /**
     * @param  array<int, Material>  $materials
     * @param  Collection<int, BackwardTraceBatchData>  $rows
     */
    public function __construct(
        public string $batchNo,
        public array $materials,
        public Collection $rows,
    ) {}
}
