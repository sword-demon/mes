<?php

namespace App\Modules\Organization\FormRequest;

use App\Modules\Organization\Model\Factory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFactoryRequest extends FormRequest
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
        /** @var Factory $factory */
        $factory = $this->route('factory');

        return [
            'code' => ['sometimes', 'required', 'string', 'max:64', Rule::unique('mes_factories', 'code')->ignore($factory->id)],
            'name' => ['sometimes', 'required', 'string', 'max:128'],
            'address' => ['nullable', 'string', 'max:255'],
            'contact' => ['nullable', 'string', 'max:64'],
            'phone' => ['nullable', 'string', 'max:32'],
            'status' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['code.unique' => '编码已存在'];
    }
}
