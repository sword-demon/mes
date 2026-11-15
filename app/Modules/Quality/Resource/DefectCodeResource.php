<?php

namespace App\Modules\Quality\Resource;

use App\Modules\Quality\Model\DefectCode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DefectCode */
class DefectCodeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'code' => $this->code, 'name' => $this->name, 'category' => $this->category, 'status' => $this->status];
    }
}
