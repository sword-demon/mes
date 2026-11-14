<?php

namespace App\Modules\System\FormRequest;

use App\Modules\System\Model\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
        /** @var User $user */
        $user = $this->route('user');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:64'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('sys_users', 'email')->ignore($user->id),
            ],
            'phone' => ['nullable', 'string', 'max:32'],
            'status' => ['sometimes', 'boolean'],
        ];
    }
}
