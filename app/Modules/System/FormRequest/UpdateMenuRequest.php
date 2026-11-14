<?php

namespace App\Modules\System\FormRequest;

use App\Modules\System\Model\Menu;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMenuRequest extends FormRequest
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
        /** @var Menu $menu */
        $menu = $this->route('menu');

        return [
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('sys_menus', 'id'),
                Rule::notIn([$menu->id]),
            ],
            'permission_id' => ['nullable', 'integer', Rule::exists('sys_permissions', 'id')],
            'name' => ['sometimes', 'required', 'string', 'max:64'],
            'path' => ['nullable', 'string', 'max:128'],
            'icon' => ['nullable', 'string', 'max:64'],
            'sort' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
