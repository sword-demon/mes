<?php

namespace App\Modules\Organization\FormRequest;

use App\Modules\Organization\Model\Workshop;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkshopRequest extends FormRequest
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
        /** @var Workshop $workshop */
        $workshop = $this->route('workshop');

        return [
            'factory_id' => ['sometimes', 'required', 'integer', Rule::exists('mes_factories', 'id')->where('status', true)],
            'code' => ['sometimes', 'required', 'string', 'max:64', Rule::unique('mes_workshops', 'code')->ignore($workshop->id)],
            'name' => ['sometimes', 'required', 'string', 'max:128'],
            'manager' => ['nullable', 'string', 'max:64'],
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
            'factory_id.exists' => '所属工厂不存在或已停用',
        ];
    }
}
