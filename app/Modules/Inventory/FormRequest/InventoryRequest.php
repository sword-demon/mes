<?php

namespace App\Modules\Inventory\FormRequest;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class InventoryRequest extends FormRequest
{
    final public function authorize(): bool
    {
        return true;
    }

    /**
     * The stock position every movement is addressed to.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function positionRules(): array
    {
        return [
            'material_id' => ['required', 'integer', Rule::exists('mes_materials', 'id')],
            'warehouse_id' => ['required', 'integer', Rule::exists('mes_warehouses', 'id')],
            'location_id' => ['required', 'integer', Rule::exists('mes_storage_locations', 'id')],
            'batch_no' => ['nullable', 'string', 'max:80'],
            'reference_no' => ['nullable', 'string', 'max:80'],
            'remark' => ['nullable', 'string', 'max:255'],
        ];
    }
}
