<?php

namespace App\Modules\Quality\FormRequest;

use Illuminate\Foundation\Http\FormRequest;

class RecheckDefectiveProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'passed' => ['required', 'boolean'],
            'remark' => ['nullable', 'string', 'max:255'],
        ];
    }
}
