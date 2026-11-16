<?php

namespace App\Modules\Equipment\FormRequest;

use App\Modules\Equipment\Model\Equipment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class EquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function attributeRules(?Equipment $ignore = null): array
    {
        return [
            'code' => ['required', 'string', 'max:64', Rule::unique('mes_equipments', 'code')->ignore($ignore)],
            'name' => ['required', 'string', 'max:128'],
            'type' => ['required', Rule::in(Equipment::TYPES)],
            'workshop_id' => ['required', 'integer', Rule::exists('mes_workshops', 'id')->where('status', true)],
            'production_line_id' => ['required', 'integer', Rule::exists('mes_production_lines', 'id')->where('status', true)],
            'model' => ['required', 'string', 'max:64'],
            'owner_id' => ['required', 'integer', Rule::exists('mes_employees', 'id')->where('status', true)],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function attributeMessages(): array
    {
        return [
            'code.unique' => '设备编码已存在',
            'workshop_id.exists' => '所属车间不存在或已停用',
            'production_line_id.exists' => '所属产线不存在或已停用',
            'owner_id.exists' => '设备负责人不存在或已停用',
            'type.in' => '设备类型不正确',
            'status.in' => '设备状态不正确',
        ];
    }
}
