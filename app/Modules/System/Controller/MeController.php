<?php

namespace App\Modules\System\Controller;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\System\Resource\UserResource;
use App\Modules\System\Service\AccessControlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __construct(private readonly AccessControlService $access) {}

    /**
     * Return the authenticated user with roles, permissions and menus.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load(['roles' => fn ($query) => $query->where('status', true)]);
        $permissions = $this->access->permissionCodes($user);

        return ApiResponse::success([
            ...UserResource::make($user)->resolve(),
            'permissions' => $permissions,
            'data_scope' => $this->access->dataScope($user),
            'menus' => $this->access->menuTree($user, $permissions),
        ]);
    }
}
