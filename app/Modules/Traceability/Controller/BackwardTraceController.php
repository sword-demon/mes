<?php

namespace App\Modules\Traceability\Controller;

use App\Core\DataScope\DataScopeGuard;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Traceability\FormRequest\BackwardTraceRequest;
use App\Modules\Traceability\Resource\BackwardTraceResource;
use App\Modules\Traceability\Service\BackwardTraceService;
use Illuminate\Http\JsonResponse;

class BackwardTraceController extends Controller
{
    public function __construct(
        private readonly BackwardTraceService $traceService,
        private readonly DataScopeGuard $dataScope,
    ) {}

    public function show(BackwardTraceRequest $request): JsonResponse
    {
        $batchNo = (string) $request->validated('batch_no');

        return ApiResponse::success(
            BackwardTraceResource::make($this->traceService->trace($batchNo, $this->dataScope->scope())),
        );
    }
}
