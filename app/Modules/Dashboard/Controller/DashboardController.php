<?php

namespace App\Modules\Dashboard\Controller;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Dashboard\Service\DashboardService;
use App\Modules\Equipment\Resource\EquipmentAlarmResource;
use App\Modules\Production\Resource\ProductionExceptionResource;
use App\Modules\Production\Resource\ProductionOrderResource;
use App\Modules\Production\Resource\ProductionReportResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function today(): JsonResponse
    {
        return ApiResponse::success($this->dashboard->today());
    }

    public function productionTrend(Request $request): JsonResponse
    {
        $data = $request->validate([
            'days' => ['sometimes', 'integer', 'min:1', 'max:31'],
        ]);

        return ApiResponse::success(
            $this->dashboard->productionTrend((int) ($data['days'] ?? 7)),
        );
    }

    public function productionLines(): JsonResponse
    {
        return ApiResponse::success($this->dashboard->productionLines());
    }

    public function quality(): JsonResponse
    {
        return ApiResponse::success($this->dashboard->quality());
    }

    public function equipmentStatus(): JsonResponse
    {
        return ApiResponse::success($this->dashboard->equipmentStatus());
    }

    public function latestOrders(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'items' => ProductionOrderResource::collection(
                $this->dashboard->latestOrders($this->latestLimit($request)),
            )->resolve(),
        ]);
    }

    public function latestReports(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'items' => ProductionReportResource::collection(
                $this->dashboard->latestReports($this->latestLimit($request)),
            )->resolve(),
        ]);
    }

    public function latestExceptions(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'items' => ProductionExceptionResource::collection(
                $this->dashboard->latestExceptions($this->latestLimit($request)),
            )->resolve(),
        ]);
    }

    public function latestAlarms(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'items' => EquipmentAlarmResource::collection(
                $this->dashboard->latestAlarms($this->latestLimit($request)),
            )->resolve(),
        ]);
    }

    private function latestLimit(Request $request): int
    {
        $data = $request->validate([
            'limit' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ]);

        return (int) ($data['limit'] ?? 5);
    }
}
