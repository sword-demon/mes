<?php

namespace App\Modules\System\Controller;

use App\Core\Audit\AuditContext;
use App\Core\Services\BusinessDay;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\System\Model\OperationLog;
use App\Modules\System\Resource\OperationLogResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OperationLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'user_id' => ['nullable', 'integer'],
            'action' => ['nullable', 'string', 'max:64'],
            'object_type' => ['nullable', 'string', 'max:32'],
            'source' => ['nullable', 'string', Rule::in(AuditContext::sources())],
            'trace_id' => ['nullable', 'string', 'max:64'],
            'keyword' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        $query = OperationLog::query()
            ->when($filters['user_id'] ?? null, fn (Builder $query, int|string $userId) => $query->where('user_id', $userId))
            ->when($filters['action'] ?? null, fn (Builder $query, string $action) => $query->where('action', $action))
            ->when($filters['object_type'] ?? null, fn (Builder $query, string $type) => $query->where('object_type', $type))
            ->when($filters['source'] ?? null, fn (Builder $query, string $source) => $query->where('source', $source))
            ->when($filters['trace_id'] ?? null, fn (Builder $query, string $traceId) => $query->where('trace_id', $traceId))
            ->when($filters['keyword'] ?? null, function (Builder $query, string $keyword): void {
                $query->where(fn (Builder $inner) => $inner
                    ->where('object_name', 'like', "%{$keyword}%")
                    ->orWhere('summary', 'like', "%{$keyword}%"));
            })
            ->when(
                $filters['start_date'] ?? null,
                fn (Builder $query, string $date) => $query->where('created_at', '>=', BusinessDay::startUtc($date)),
            )
            ->when(
                $filters['end_date'] ?? null,
                fn (Builder $query, string $date) => $query->where('created_at', '<', BusinessDay::endUtc($date)),
            )
            ->orderByDesc('id');

        return ApiResponse::paginated($query->paginate(15), OperationLogResource::class);
    }

    public function options(): JsonResponse
    {
        $users = OperationLog::query()
            ->whereNotNull('user_id')
            ->select('user_id', 'user_name')
            ->distinct()
            ->orderBy('user_name')
            ->get()
            ->map(fn (OperationLog $log): array => [
                'id' => $log->user_id,
                'name' => $log->user_name,
            ]);

        $actions = OperationLog::query()
            ->select('action', 'action_name')
            ->distinct()
            ->orderBy('action_name')
            ->get()
            ->map(fn (OperationLog $log): array => [
                'action' => $log->action,
                'action_name' => $log->action_name,
            ]);

        return ApiResponse::success([
            'users' => $users,
            'actions' => $actions,
            'sources' => AuditContext::sources(),
        ]);
    }
}
