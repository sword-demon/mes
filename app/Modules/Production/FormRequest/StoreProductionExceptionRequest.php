<?php

namespace App\Modules\Production\FormRequest;

use App\Modules\Production\Model\ProductionException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductionExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(ProductionException::TYPES)],
            'production_order_id' => ['nullable', 'integer', 'required_without:process_record_id', Rule::exists('mes_production_orders', 'id')],
            'process_record_id' => ['nullable', 'integer', 'required_without:production_order_id', Rule::exists('mes_process_records', 'id')],
            'description' => ['required', 'string', 'max:2000'],
            'images' => ['nullable', 'array', 'max:5'],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'type.required' => '请选择异常类型',
            'type.in' => '异常类型不合法',
            'production_order_id.required_without' => '请关联生产订单或工序执行记录',
            'process_record_id.required_without' => '请关联生产订单或工序执行记录',
            'description.required' => '请填写异常描述',
            'images.max' => '最多上传 5 张图片',
            'images.*.image' => '只能上传图片',
            'images.*.mimes' => '图片格式仅支持 jpg/jpeg/png/webp',
            'images.*.max' => '单张图片不能超过 5MB',
        ];
    }
}
