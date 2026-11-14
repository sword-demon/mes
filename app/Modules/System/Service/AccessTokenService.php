<?php

namespace App\Modules\System\Service;

use App\Modules\System\Model\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\TransientToken;

class AccessTokenService
{
    /**
     * @return array{user: User, token: string}
     *
     * @throws ValidationException
     */
    public function issue(string $email, string $password, string $deviceName = 'api'): array
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        return [
            'user' => $user,
            'token' => $user->createToken($deviceName)->plainTextToken,
        ];
    }

    public function revoke(PersonalAccessToken|TransientToken|null $token): void
    {
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }
    }
}
