<?php

namespace App\Modules\Bom\FormRequest;

use App\Modules\Bom\Model\Bom;
use App\Modules\Bom\Model\BomItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class BomItemRequest extends FormRequest
{
    final public function authorize(): bool
    {
        return true;
    }

    /**
     * A material may appear once per BOM; the quantity must stay positive.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function itemRules(Bom $bom, ?BomItem $item = null): array
    {
        $presence = $item === null ? [] : ['sometimes'];
        $material = Rule::unique('mes_bom_items', 'material_id')->where('bom_id', $bom->id);

        if ($item !== null) {
            $material->ignore($item->id);
        }

        return [
            'material_id' => [...$presence, 'required', 'integer', Rule::exists('mes_materials', 'id'), $material],
            'quantity' => [...$presence, 'required', 'numeric', 'gt:0', 'max:999999999.999', 'decimal:0,3'],
            'loss_rate' => ['sometimes', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'material_id.unique' => '物料已在 BOM 中',
        ];
    }
}
