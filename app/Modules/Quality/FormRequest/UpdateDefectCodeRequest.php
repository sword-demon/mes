<?php

namespace App\Modules\Quality\FormRequest;

use App\Modules\Quality\Model\DefectCode;

class UpdateDefectCodeRequest extends DefectCodeRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $defect = $this->route('defect');

        return $this->defectRules($defect instanceof DefectCode ? $defect : null);
    }
}
