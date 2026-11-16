<?php

namespace App\Modules\System\Service;

use App\Modules\System\Model\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\TransientToken;

class AccessTokenService
{
    public function __construct(private readonly LoginLogService $loginLogs) {}

    /**
     * @return array{user: User, token: string}
     *
     * @throws ValidationException
     */
    public function issue(string $email, string $password, string $deviceName = 'api'): array
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->loginLogs->record($email, null, false, '账号不存在');

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        if (! $user->status) {
            $this->loginLogs->record($email, $user, false, '账号已停用');

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        if (! Hash::check($password, $user->password)) {
            $this->loginLogs->record($email, $user, false, '密码错误');

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $token = $user->createToken($deviceName)->plainTextToken;
        $this->loginLogs->record($email, $user, true, '登录成功');

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    public function revoke(PersonalAccessToken|TransientToken|null $token): void
    {
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }
    }
}
