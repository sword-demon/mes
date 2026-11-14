<?php

namespace App\Modules\System\FormRequest;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:64'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('sys_users', 'email')],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => ['required', 'string', Password::default()],
            'status' => ['sometimes', 'boolean'],
            'role_ids' => ['array'],
            'role_ids.*' => ['integer', Rule::exists('sys_roles', 'id')],
        ];
    }
}
