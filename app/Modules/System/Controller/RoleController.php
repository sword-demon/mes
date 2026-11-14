<?php

namespace App\Modules\System\Controller;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\System\FormRequest\StoreRoleRequest;
use App\Modules\System\FormRequest\UpdateRoleRequest;
use App\Modules\System\Model\Role;
use App\Modules\System\Resource\RoleResource;
use App\Modules\System\Service\RoleService;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    public function __construct(private readonly RoleService $roles) {}

    public function index(): JsonResponse
    {
        return ApiResponse::paginated($this->roles->paginate(), RoleResource::class);
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $permissionIds = $validated['permission_ids'] ?? [];
        unset($validated['permission_ids']);

        $this->roles->ensureCanGrantPermissions($request->user(), $permissionIds);

        return ApiResponse::success(RoleResource::make($this->roles->create($validated, $permissionIds)));
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $validated = $request->validated();
        $permissionIds = array_key_exists('permission_ids', $validated) ? $validated['permission_ids'] : null;
        unset($validated['permission_ids']);

        $this->roles->ensureCanGrantPermissions($request->user(), $permissionIds ?? []);

        return ApiResponse::success(RoleResource::make($this->roles->update($role, $validated, $permissionIds)));
    }

    public function destroy(Role $role): JsonResponse
    {
        $this->roles->delete($role);

        return ApiResponse::success();
    }
}
