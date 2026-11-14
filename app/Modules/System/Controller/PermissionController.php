<?php

namespace App\Modules\System\Controller;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\System\Model\Permission;
use App\Modules\System\Resource\PermissionResource;
use Illuminate\Http\JsonResponse;

class PermissionController extends Controller
{
    public function index(): JsonResponse
    {
        $permissions = Permission::query()
            ->orderBy('type')
            ->orderBy('code')
            ->get();

        return ApiResponse::success(PermissionResource::collection($permissions)->resolve());
    }
}
