<?php

namespace App\Modules\System\Controller;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\System\FormRequest\LoginRequest;
use App\Modules\System\Resource\UserResource;
use App\Modules\System\Service\AccessTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccessTokenController extends Controller
{
    public function __construct(private readonly AccessTokenService $tokens) {}

    /**
     * Issue a bearer token for the given credentials.
     */
    public function store(LoginRequest $request): JsonResponse
    {
        $issued = $this->tokens->issue(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->string('device_name', 'api')->toString(),
        );

        return ApiResponse::success([
            'token' => $issued['token'],
            'user' => UserResource::make($issued['user']),
        ]);
    }

    /**
     * Revoke the token used for the current request.
     */
    public function destroy(Request $request): JsonResponse
    {
        $this->tokens->revoke($request->user()->currentAccessToken());

        return ApiResponse::success();
    }
}
