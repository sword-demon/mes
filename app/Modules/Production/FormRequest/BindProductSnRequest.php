<?php

namespace App\Modules\Production\FormRequest;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BindProductSnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'material_batch_no' => ['nullable', 'string', 'max:64'],
            'equipment_id' => ['nullable', 'integer', Rule::exists('mes_equipments', 'id')],
            'employee_id' => ['nullable', 'integer', Rule::exists('mes_employees', 'id')],
        ];
    }
}
