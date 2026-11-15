<?php

namespace App\Modules\Quality\FormRequest;

use Illuminate\Foundation\Http\FormRequest;

class ScrapDefectiveProductRequest extends FormRequest
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
            'quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:999999999.999'],
        ];
    }
}
