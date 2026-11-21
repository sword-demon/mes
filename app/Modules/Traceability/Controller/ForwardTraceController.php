<?php

namespace App\Modules\Traceability\Controller;

use App\Core\DataScope\DataScopeGuard;
use App\Core\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Production\Model\ProductSn;
use App\Modules\Traceability\Resource\ForwardTraceResource;
use App\Modules\Traceability\Service\ForwardTraceService;
use Illuminate\Http\JsonResponse;

class ForwardTraceController extends Controller
{
    public function __construct(
        private readonly ForwardTraceService $traceService,
        private readonly DataScopeGuard $dataScope,
    ) {}

    public function show(ProductSn $productSn): JsonResponse
    {
        // Route binding keys by `sn`; scope-filter the lookup the SN detail
        // endpoint uses, so an out-of-scope unit reads as missing (see 07).
        $sn = ProductSn::query()
            ->inDataScope($this->dataScope->scope())
            ->where('sn', $productSn->sn)
            ->first();

        if ($sn === null) {
            throw new ApiException('SN 不存在', 404, status: 404);
        }

        return ApiResponse::success(
            ForwardTraceResource::make($this->traceService->trace($sn)),
        );
    }
}
