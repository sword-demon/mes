<?php

namespace App\Modules\System\Controller;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\System\FormRequest\AssignUserRolesRequest;
use App\Modules\System\FormRequest\ResetUserPasswordRequest;
use App\Modules\System\FormRequest\StoreUserRequest;
use App\Modules\System\FormRequest\UpdateUserRequest;
use App\Modules\System\Model\User;
use App\Modules\System\Resource\UserResource;
use App\Modules\System\Service\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::paginated(
            $this->users->paginate(
                $request->string('keyword')->toString() ?: null,
                $request->has('status') ? $request->boolean('status') : null,
            ),
            UserResource::class,
        );
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $roleIds = $validated['role_ids'] ?? [];
        unset($validated['role_ids']);

        return ApiResponse::success(UserResource::make(
            $this->users->create($request->user(), $validated, $roleIds),
        ));
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        return ApiResponse::success(UserResource::make(
            $this->users->update($request->user(), $user, $request->validated()),
        ));
    }

    public function resetPassword(ResetUserPasswordRequest $request, User $user): JsonResponse
    {
        $this->users->resetPassword($request->user(), $user, $request->string('password')->toString());

        return ApiResponse::success();
    }

    public function assignRoles(AssignUserRolesRequest $request, User $user): JsonResponse
    {
        return ApiResponse::success(UserResource::make(
            $this->users->assignRoles($request->user(), $user, $request->array('role_ids')),
        ));
    }
}
