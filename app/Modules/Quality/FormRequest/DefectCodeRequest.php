<?php

namespace App\Modules\Quality\FormRequest;

use App\Modules\Quality\Model\DefectCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DefectCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $defect = $this->route('defect');
        $presence = $defect instanceof DefectCode ? ['sometimes'] : [];

        return [
            'code' => [...$presence, 'required', 'string', 'max:64', Rule::unique('mes_defect_codes', 'code')->ignore($defect instanceof DefectCode ? $defect->id : null)],
            'name' => [...$presence, 'required', 'string', 'max:128'],
            'category' => [...$presence, 'required', 'string', 'max:64'],
            'status' => ['sometimes', 'boolean'],
        ];
    }
}
