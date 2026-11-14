<?php

namespace App\Modules\System\Controller;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\System\FormRequest\StoreMenuRequest;
use App\Modules\System\FormRequest\UpdateMenuRequest;
use App\Modules\System\Model\Menu;
use App\Modules\System\Resource\MenuResource;
use App\Modules\System\Service\MenuService;
use Illuminate\Http\JsonResponse;

class MenuController extends Controller
{
    public function __construct(private readonly MenuService $menus) {}

    public function index(): JsonResponse
    {
        return ApiResponse::success($this->menus->tree());
    }

    public function store(StoreMenuRequest $request): JsonResponse
    {
        return ApiResponse::success(MenuResource::make($this->menus->create($request->validated())));
    }

    public function update(UpdateMenuRequest $request, Menu $menu): JsonResponse
    {
        return ApiResponse::success(MenuResource::make($this->menus->update($menu, $request->validated())));
    }

    public function destroy(Menu $menu): JsonResponse
    {
        $this->menus->delete($menu);

        return ApiResponse::success();
    }
}
