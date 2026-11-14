<?php

namespace App\Modules\Material\FormRequest;

use App\Modules\Material\Model\Material;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class MaterialRequest extends FormRequest
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
    protected function materialRules(?Material $material = null): array
    {
        $presence = $material === null ? [] : ['sometimes'];
        $code = Rule::unique('mes_materials', 'code');

        if ($material !== null) {
            $code->ignore($material->id);
        }

        return [
            'code' => [...$presence, 'required', 'string', 'max:64', $code],
            'name' => [...$presence, 'required', 'string', 'max:128'],
            'type' => [...$presence, 'required', 'string', Rule::in(Material::TYPES)],
            'specification' => ['nullable', 'string', 'max:255'],
            'unit' => [...$presence, 'required', 'string', 'max:16'],
            'min_stock' => ['sometimes', 'numeric', 'min:0', 'max:999999999.999', 'decimal:0,3'],
            'safety_stock' => ['sometimes', 'numeric', 'min:0', 'max:999999999.999', 'decimal:0,3'],
            'batch_managed' => ['sometimes', 'boolean'],
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
        ];
    }
}
