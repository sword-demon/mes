<?php

namespace App\Modules\System\Service;

use App\Modules\System\Model\LoginLog;
use App\Modules\System\Model\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LoginLogService
{
    public function __construct(private readonly Request $request) {}

    public function record(
        string $account,
        ?User $user,
        bool $success,
        string $message,
        ?string $ip = null,
    ): LoginLog {
        return LoginLog::query()->create([
            'user_id' => $user?->id,
            'account' => Str::substr($account, 0, 255),
            'ip' => $ip ?? (string) $this->request->ip(),
            'success' => $success,
            'message' => $message,
        ]);
    }
}
