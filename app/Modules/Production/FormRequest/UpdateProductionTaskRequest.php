<?php

namespace App\Modules\Production\FormRequest;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductionTaskRequest extends FormRequest
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
            'production_line_id' => ['required', 'integer', Rule::exists('mes_production_lines', 'id')],
            'team_id' => ['required', 'integer', Rule::exists('mes_teams', 'id')],
            'owner_id' => ['required', 'integer', Rule::exists('mes_employees', 'id')],
        ];
    }
}
