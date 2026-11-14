<?php

namespace App\Modules\Bom\FormRequest;

use App\Modules\Bom\Model\Bom;
use App\Modules\Product\Model\ProductVersion;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class BomRequest extends FormRequest
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
    protected function bomRules(?Bom $bom = null): array
    {
        $presence = $bom === null ? [] : ['sometimes'];
        $code = Rule::unique('mes_boms', 'code');

        if ($bom !== null) {
            $code->ignore($bom->id);
        }

        $rules = [
            'code' => [...$presence, 'required', 'string', 'max:64', $code],
            'version' => [...$presence, 'required', 'string', 'max:32', $this->versionAvailable($bom)],
            'remark' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'boolean'],
        ];

        if ($bom === null) {
            // A BOM is anchored to its product version for life.
            $rules['product_version_id'] = ['required', 'integer', Rule::exists('mes_product_versions', 'id')];
        }

        return $rules;
    }

    /**
     * A BOM version is unique across every product version of one product.
     */
    private function versionAvailable(?Bom $bom): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($bom): void {
            $productVersionId = $bom->product_version_id ?? $this->input('product_version_id');
            $productId = ProductVersion::query()->whereKey($productVersionId)->value('product_id');

            if ($productId === null) {
                return;
            }

            $taken = Bom::query()
                ->where('version', (string) $value)
                ->whereHas('productVersion', fn ($query) => $query->where('product_id', $productId))
                ->when($bom !== null, fn ($query) => $query->whereKeyNot($bom->id))
                ->exists();

            if ($taken) {
                $fail('版本号已存在');
            }
        };
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
