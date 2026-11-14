<?php

namespace App\Modules\System\FormRequest;

use App\Modules\System\Model\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
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
        /** @var Department $department */
        $department = $this->route('department');

        return [
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('sys_departments', 'id'),
                Rule::notIn([$department->id]),
            ],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:64',
                Rule::unique('sys_departments', 'code')->ignore($department->id),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:64'],
            'sort' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
