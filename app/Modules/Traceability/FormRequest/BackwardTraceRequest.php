<?php

namespace App\Modules\Traceability\FormRequest;

use Illuminate\Foundation\Http\FormRequest;

class BackwardTraceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'batch_no' => ['required', 'string', 'max:80'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $batch = $this->route('batch');

        $this->merge(['batch_no' => is_string($batch) ? trim($batch) : '']);
    }
}
