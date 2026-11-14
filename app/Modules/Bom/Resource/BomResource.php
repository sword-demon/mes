<?php

namespace App\Modules\Bom\Resource;

use App\Modules\Bom\Model\Bom;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Bom
 */
class BomResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'product_version_id' => $this->product_version_id,
            'version' => $this->version,
            'audit_status' => $this->audit_status,
            'audited_at' => $this->audited_at,
            'remark' => $this->remark,
            'status' => $this->status,
            'product_version' => $this->whenLoaded('productVersion', fn () => [
                'id' => $this->productVersion->id,
                'version' => $this->productVersion->version,
                'product' => [
                    'id' => $this->productVersion->product->id,
                    'code' => $this->productVersion->product->code,
                    'name' => $this->productVersion->product->name,
                ],
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
