<?php

namespace App\Modules\Production\FormRequest;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartProcessRecordRequest extends FormRequest
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
            'employee_id' => ['nullable', 'integer', Rule::exists('mes_employees', 'id')],
            'equipment_id' => ['nullable', 'integer', Rule::exists('mes_equipments', 'id')],
            'sn' => ['nullable', 'string', 'max:64'],
        ];
    }
}
