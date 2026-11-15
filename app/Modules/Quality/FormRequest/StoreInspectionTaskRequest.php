<?php

namespace App\Modules\Quality\FormRequest;

use App\Modules\Quality\Model\InspectionTask;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInspectionTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'production_order_id' => ['required', 'integer', Rule::exists('mes_production_orders', 'id')],
            'inspection_type' => ['required', Rule::in([InspectionTask::IPQC, InspectionTask::OQC])],
            'process_record_id' => ['nullable', 'integer', Rule::exists('mes_process_records', 'id')],
            'route_operation_id' => ['nullable', 'integer', Rule::exists('mes_route_operations', 'id'), 'prohibited_if:inspection_type,ipqc'],
            'remark' => ['nullable', 'string', 'max:255'],
        ];
    }
}
