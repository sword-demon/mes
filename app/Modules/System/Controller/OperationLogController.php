<?php

namespace App\Modules\System\Controller;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\System\Model\OperationLog;
use App\Modules\System\Resource\OperationLogResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class OperationLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'user_id' => ['nullable', 'integer'],
            'action' => ['nullable', 'string', 'max:64'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        $query = OperationLog::query()
            ->when($filters['user_id'] ?? null, fn (Builder $query, int|string $userId) => $query->where('user_id', $userId))
            ->when($filters['action'] ?? null, fn (Builder $query, string $action) => $query->where('action', $action))
            ->when(
                $filters['start_date'] ?? null,
                fn (Builder $query, string $date) => $query->where('created_at', '>=', $this->businessDayStart($date)),
            )
            ->when(
                $filters['end_date'] ?? null,
                fn (Builder $query, string $date) => $query->where('created_at', '<', $this->businessDayStart($date)->addDay()),
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
        ]);
    }

    private function businessDayStart(string $date): Carbon
    {
        return Carbon::parse($date, (string) config('app.business_timezone', 'Asia/Shanghai'))
            ->startOfDay()
            ->utc();
    }
}
