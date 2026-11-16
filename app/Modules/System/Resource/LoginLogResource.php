<?php

namespace App\Modules\System\Resource;

use App\Modules\System\Model\LoginLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LoginLog
 */
class LoginLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'account' => $this->account,
            'ip' => $this->ip,
            'success' => $this->success,
            'message' => $this->message,
            'created_at' => $this->created_at,
        ];
    }
}
