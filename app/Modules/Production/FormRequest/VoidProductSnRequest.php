<?php

namespace App\Modules\Production\FormRequest;

use Illuminate\Foundation\Http\FormRequest;

class VoidProductSnRequest extends FormRequest
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
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => '作废 SN 必须填写原因',
        ];
    }
}
