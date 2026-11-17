<?php

namespace App\Modules\System\Controller;

use App\Core\Services\BusinessDay;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\System\Model\LoginLog;
use App\Modules\System\Resource\LoginLogResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoginLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'account' => ['nullable', 'string', 'max:255'],
            'success' => ['nullable', 'boolean'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        $success = array_key_exists('success', $filters) && $filters['success'] !== null
            ? $request->boolean('success')
            : null;

        $query = LoginLog::query()
            ->when(
                $filters['account'] ?? null,
                fn (Builder $query, string $account) => $query->where('account', 'like', "%{$account}%"),
            )
            ->when($success !== null, fn (Builder $query) => $query->where('success', $success))
            ->when(
                $filters['start_date'] ?? null,
                fn (Builder $query, string $date) => $query->where('created_at', '>=', BusinessDay::startUtc($date)),
            )
            ->when(
                $filters['end_date'] ?? null,
                fn (Builder $query, string $date) => $query->where('created_at', '<', BusinessDay::endUtc($date)),
            )
            ->orderByDesc('id');

        return ApiResponse::paginated($query->paginate(15), LoginLogResource::class);
    }
}
