<?php

namespace App\Modules\Quality\FormRequest;

use Illuminate\Foundation\Http\FormRequest;

class ReworkDefectiveProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
