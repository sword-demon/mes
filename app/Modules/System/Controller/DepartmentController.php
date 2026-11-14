<?php

namespace App\Modules\System\Controller;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\System\FormRequest\StoreDepartmentRequest;
use App\Modules\System\FormRequest\UpdateDepartmentRequest;
use App\Modules\System\Model\Department;
use App\Modules\System\Resource\DepartmentResource;
use App\Modules\System\Service\DepartmentService;
use Illuminate\Http\JsonResponse;

class DepartmentController extends Controller
{
    public function __construct(private readonly DepartmentService $departments) {}

    public function index(): JsonResponse
    {
        return ApiResponse::success($this->departments->tree());
    }

    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        return ApiResponse::success(DepartmentResource::make(
            $this->departments->create($request->validated()),
        ));
    }

    public function update(UpdateDepartmentRequest $request, Department $department): JsonResponse
    {
        return ApiResponse::success(DepartmentResource::make(
            $this->departments->update($department, $request->validated()),
        ));
    }

    public function destroy(Department $department): JsonResponse
    {
        $this->departments->delete($department);

        return ApiResponse::success();
    }
}
