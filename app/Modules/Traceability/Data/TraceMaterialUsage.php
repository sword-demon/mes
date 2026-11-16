<?php

namespace App\Modules\Traceability\Data;

use App\Modules\Material\Model\Material;

final readonly class TraceMaterialUsage
{
    public function __construct(
        public Material $material,
        public float $quantity,
        public float $lossRate,
        public string $issuedQuantity,
    ) {}
}
