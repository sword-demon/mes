<?php

namespace App\Modules\System\Resource;

use App\Modules\System\Model\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OperationLog
 */
class OperationLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user_name' => $this->user_name,
            'action' => $this->action,
            'action_name' => $this->action_name,
            'object_type' => $this->object_type,
            'object_id' => $this->object_id,
            'object_name' => $this->object_name,
            'summary' => $this->summary,
            'changes' => $this->changes,
            'ip' => $this->ip,
            'created_at' => $this->created_at,
        ];
    }
}
