<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\Request;

class MeController extends Controller
{
    /**
     * Return the authenticated user.
     */
    public function show(Request $request): UserResource
    {
        return UserResource::make($request->user());
    }
}
