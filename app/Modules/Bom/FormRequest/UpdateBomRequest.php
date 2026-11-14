<?php

namespace App\Modules\Bom\FormRequest;

use App\Modules\Bom\Model\Bom;

class UpdateBomRequest extends BomRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Bom $bom */
        $bom = $this->route('bom');

        return $this->bomRules($bom);
    }
}
