<?php

namespace App\Modules\Quality\FormRequest;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordInspectionTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $decimal = ['numeric', 'decimal:0,3', 'between:-999999999.999,999999999.999'];

        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.inspection_item_id' => ['required', 'integer', 'distinct', Rule::exists('mes_inspection_items', 'id')],
            'items.*.measured_value' => ['required', ...$decimal],
            'remark' => ['nullable', 'string', 'max:255'],
        ];
    }
}
