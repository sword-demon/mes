<?php

namespace App\Modules\Traceability\Data;

use App\Modules\Material\Model\Material;

final readonly class BackwardTraceMaterialUsage
{
    public function __construct(
        public Material $material,
        public string $issuedQuantity,
    ) {}
}
