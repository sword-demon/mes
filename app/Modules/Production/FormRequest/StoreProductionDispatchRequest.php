<?php

namespace App\Modules\Production\FormRequest;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductionDispatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'production_task_id' => ['required', 'integer', Rule::exists('mes_production_tasks', 'id')],
            'route_operation_id' => ['required', 'integer', Rule::exists('mes_route_operations', 'id')],
            'employee_id' => ['required', 'integer', Rule::exists('mes_employees', 'id')],
            'team_id' => ['required', 'integer', Rule::exists('mes_teams', 'id')],
            'dispatched_at' => ['nullable', 'date'],
        ];
    }
}
