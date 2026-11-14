<?php

namespace App\Modules\System\FormRequest;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMenuRequest extends FormRequest
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
            'parent_id' => ['nullable', 'integer', Rule::exists('sys_menus', 'id')],
            'permission_id' => ['nullable', 'integer', Rule::exists('sys_permissions', 'id')],
            'name' => ['required', 'string', 'max:64'],
            'path' => ['nullable', 'string', 'max:128'],
            'icon' => ['nullable', 'string', 'max:64'],
            'sort' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
