<?php

namespace App\Modules\Traceability\Data;

use App\Modules\Production\Model\ProcessRecord;
use App\Modules\Production\Model\ProductSn;
use App\Modules\Quality\Model\DefectiveProduct;
use App\Modules\Quality\Model\InspectionTask;
use Illuminate\Database\Eloquent\Collection;

final readonly class ForwardTraceData
{
    /**
     * @param  Collection<int, ProcessRecord>  $processRecords
     * @param  Collection<int, InspectionTask>  $inspections
     * @param  Collection<int, InspectionTask>  $orderInspections
     * @param  Collection<int, DefectiveProduct>  $defects
     * @param  array<int, TraceMaterialUsage>  $materials
     */
    public function __construct(
        public ProductSn $sn,
        public Collection $processRecords,
        public Collection $inspections,
        public Collection $orderInspections,
        public Collection $defects,
        public array $materials,
    ) {}
}
