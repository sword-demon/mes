<?php

namespace App\Modules\Quality\FormRequest;

use App\Modules\Quality\Model\DefectCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class DefectCodeRequest extends FormRequest
{
    final public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    protected function defectRules(?DefectCode $defect = null): array
    {
        $presence = $defect === null ? [] : ['sometimes'];
        $code = Rule::unique('mes_defect_codes', 'code');

        if ($defect !== null) {
            $code->ignore($defect->id);
        }

        return [
            'code' => [...$presence, 'required', 'string', 'max:64', $code],
            'name' => [...$presence, 'required', 'string', 'max:128'],
            'category' => [...$presence, 'required', 'string', 'max:64'],
            'status' => ['sometimes', 'boolean'],
        ];
    }
}
