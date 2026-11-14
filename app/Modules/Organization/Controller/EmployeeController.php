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
            ),
            EmployeeResource::class,
        );
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        return ApiResponse::success(EmployeeResource::make(
            Employee::query()->create($request->validated())->load(['department', 'team']),
        ));
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): JsonResponse
    {
        return ApiResponse::success(EmployeeResource::make(
            tap($employee)->update($request->validated())->load(['department', 'team']),
        ));
    }
}
