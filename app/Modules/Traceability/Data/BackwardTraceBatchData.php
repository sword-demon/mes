<?php

namespace App\Modules\Traceability\Data;

use App\Modules\Production\Model\ProductionBatch;
use App\Modules\Production\Model\ProductionOrder;
use App\Modules\Production\Model\ProductSn;
use Illuminate\Support\Collection;

final readonly class BackwardTraceBatchData
{
    /**
     * @param  Collection<int, BackwardTraceMaterialUsage>  $materials
     * @param  Collection<int, ProductSn>  $productSns
     */
    public function __construct(
        public ProductionBatch $batch,
        public ProductionOrder $order,
        public Collection $materials,
        public Collection $productSns,
    ) {}
}
