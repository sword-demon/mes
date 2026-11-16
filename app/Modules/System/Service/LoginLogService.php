<?php

namespace App\Modules\System\Service;

use App\Modules\System\Model\LoginLog;
use App\Modules\System\Model\User;

class LoginLogService
{
    public function record(
        string $account,
        ?User $user,
        bool $success,
        string $message,
        ?string $ip = null,
    ): LoginLog {
        return LoginLog::query()->create([
            'user_id' => $user?->id,
            'account' => $account,
            'ip' => $ip ?? (string) request()->ip(),
            'success' => $success,
            'message' => $message,
        ]);
    }
}
