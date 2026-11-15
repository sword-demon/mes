<?php

namespace App\Modules\Equipment\FormRequest;

use App\Modules\Equipment\Model\Equipment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEquipmentRequest extends FormRequest
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
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:64',
                Rule::unique('mes_equipments', 'code')->ignore($this->route('equipment')),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:128'],
            'type' => ['sometimes', 'required', Rule::in(Equipment::TYPES)],
            'workshop_id' => ['sometimes', 'required', 'integer', Rule::exists('mes_workshops', 'id')->where('status', true)],
            'production_line_id' => ['sometimes', 'required', 'integer', Rule::exists('mes_production_lines', 'id')->where('status', true)],
            'model' => ['sometimes', 'required', 'string', 'max:64'],
            'status' => ['sometimes', 'required', Rule::in(Equipment::STATUSES)],
            'owner_id' => ['sometimes', 'required', 'integer', Rule::exists('mes_employees', 'id')->where('status', true)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
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
