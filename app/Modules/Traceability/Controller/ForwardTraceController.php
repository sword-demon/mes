<?php

namespace App\Modules\Traceability\Controller;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Production\Model\ProductSn;
use App\Modules\Traceability\Service\ForwardTraceService;
use Illuminate\Http\JsonResponse;

class ForwardTraceController extends Controller
{
    public function __construct(private readonly ForwardTraceService $traces) {}

    public function show(ProductSn $productSn): JsonResponse
    {
        return ApiResponse::success($this->traces->trace($productSn));
    }
}
