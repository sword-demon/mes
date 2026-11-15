<?php

namespace App\Modules\Quality\FormRequest;

use App\Modules\Quality\Model\InspectionItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class InspectionItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $item = $this->route('item');
        $presence = $item instanceof InspectionItem ? ['sometimes'] : [];

        return [
            'code' => [...$presence, 'required', 'string', 'max:64', Rule::unique('mes_inspection_items', 'code')->ignore($item instanceof InspectionItem ? $item->id : null)],
            'name' => [...$presence, 'required', 'string', 'max:128'],
            'unit' => [...$presence, 'required', 'string', 'max:16'],
            'standard_value' => [...$presence, 'required', 'numeric', 'decimal:0,3', 'between:-999999999.999,999999999.999'],
            'min_value' => [...$presence, 'required', 'numeric', 'decimal:0,3', 'between:-999999999.999,999999999.999'],
            'max_value' => [...$presence, 'required', 'numeric', 'decimal:0,3', 'between:-999999999.999,999999999.999'],
            'status' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $item = $this->route('item');
            $standard = $item instanceof InspectionItem ? $item->standard : null;
            $minimum = (float) $this->input('min_value', $standard?->min_value);
            $maximum = (float) $this->input('max_value', $standard?->max_value);
            $target = (float) $this->input('standard_value', $standard?->standard_value);

            if ($minimum > $maximum) {
                $validator->errors()->add('min_value', '最小值不能大于最大值');
                $validator->errors()->add('max_value', '最大值不能小于最小值');
            }

            if ($target < $minimum || $target > $maximum) {
                $validator->errors()->add('standard_value', '标准值必须位于最小值与最大值之间');
            }
        }];
    }
}
