<?php

namespace App\Modules\Process\Controller;

use App\Core\Services\EntityService;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Process\FormRequest\StoreProcessRequest;
use App\Modules\Process\FormRequest\UpdateProcessRequest;
use App\Modules\Process\Model\Process;
use App\Modules\Process\Resource\ProcessResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProcessController extends Controller
{
    public function __construct(private readonly EntityService $entities) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::paginated(
            $this->entities->paginate(
                Process::class,
                [],
                $request->only(['type', 'status', 'keyword', 'per_page']),
                filterColumns: ['type'],
            ),
            ProcessResource::class,
        );
    }

    public function store(StoreProcessRequest $request): JsonResponse
    {
        return ApiResponse::success(ProcessResource::make(
            $this->entities->create(Process::class, $request->validated()),
        ));
    }

    public function update(UpdateProcessRequest $request, Process $process): JsonResponse
    {
        return ApiResponse::success(ProcessResource::make(
            $this->entities->update($process, $request->validated()),
        ));
    }
}
