<?php

namespace App\Modules\Material\FormRequest;

use App\Modules\Material\Model\Material;

class UpdateMaterialRequest extends MaterialRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Material $material */
        $material = $this->route('material');

        return $this->materialRules($material);
    }
}
