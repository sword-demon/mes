<?php

namespace App\Modules\Process\FormRequest;

use App\Modules\Process\Model\Process;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class ProcessRequest extends FormRequest
{
    final public function authorize(): bool
    {
        return true;
    }

    /**
     * Store requires every field; update only validates what the request sends.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function processRules(?Process $process = null): array
    {
        $presence = $process === null ? [] : ['sometimes'];
        $code = Rule::unique('mes_processes', 'code');

        if ($process !== null) {
            $code->ignore($process->id);
        }

        return [
            'code' => [...$presence, 'required', 'string', 'max:64', $code],
            'name' => [...$presence, 'required', 'string', 'max:64'],
            'type' => [...$presence, 'required', 'string', Rule::in(Process::TYPES)],
            'standard_hours' => ['sometimes', 'numeric', 'min:0', 'max:9999.99', 'decimal:0,2'],
            'requires_inspection' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.unique' => '编码已存在',
            'type.in' => '工序类型不合法',
        ];
    }
}
