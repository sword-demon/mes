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

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function today(): JsonResponse
    {
        return ApiResponse::success($this->dashboard->today());
    }

    public function productionTrend(): JsonResponse
    {
        return ApiResponse::success($this->dashboard->productionTrend());
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

    public function latestOrders(): JsonResponse
    {
        return ApiResponse::success([
            'items' => ProductionOrderResource::collection(
                $this->dashboard->latestOrders(),
            )->resolve(),
        ]);
    }

    public function latestReports(): JsonResponse
    {
        return ApiResponse::success([
            'items' => ProductionReportResource::collection(
                $this->dashboard->latestReports(),
            )->resolve(),
        ]);
    }

    public function latestExceptions(): JsonResponse
    {
        return ApiResponse::success([
            'items' => ProductionExceptionResource::collection(
                $this->dashboard->latestExceptions(),
            )->resolve(),
        ]);
    }

    public function latestAlarms(): JsonResponse
    {
        return ApiResponse::success([
            'items' => EquipmentAlarmResource::collection(
                $this->dashboard->latestAlarms(),
            )->resolve(),
        ]);
    }
}
