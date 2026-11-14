<?php

namespace App\Modules\Bom\FormRequest;

use App\Modules\Bom\Model\Bom;
use App\Modules\Bom\Model\BomItem;

class UpdateBomItemRequest extends BomItemRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Bom $bom */
        $bom = $this->route('bom');
        /** @var BomItem $item */
        $item = $this->route('item');

        return $this->itemRules($bom, $item);
    }
}
