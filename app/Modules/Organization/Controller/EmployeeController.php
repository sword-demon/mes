<?php

namespace App\Modules\Organization\Controller;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Organization\FormRequest\StoreEmployeeRequest;
use App\Modules\Organization\FormRequest\UpdateEmployeeRequest;
use App\Modules\Organization\Model\Employee;
use App\Modules\Organization\Resource\EmployeeResource;
use App\Modules\Organization\Service\OrganizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function __construct(private readonly OrganizationService $organization) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::paginated(
            $this->organization->paginate(
                Employee::class,
                ['department', 'team'],
                $request->only(['department_id', 'team_id', 'status', 'keyword', 'per_page']),
                ['employee_no', 'name'],
                enabledRelations: ['team'],
                filterColumns: ['department_id', 'team_id'],
            ),
            EmployeeResource::class,
        );
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        return ApiResponse::success(EmployeeResource::make(
            $this->organization->create(Employee::class, $request->validated())->load(['department', 'team']),
        ));
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): JsonResponse
    {
        return ApiResponse::success(EmployeeResource::make(
            $this->organization->update($employee, $request->validated())->load(['department', 'team']),
        ));
    }

    public function destroy(Employee $employee): JsonResponse
    {
        // shortcut: 业务表尚未引用员工，接入派工后需补引用校验
        $this->organization->delete($employee, []);

        return ApiResponse::success();
    }
}
