<?php

namespace App\Modules\Equipment\Controller;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Equipment\Model\Equipment;
use App\Modules\Equipment\Model\EquipmentAlarm;
use App\Modules\Equipment\Resource\EquipmentAlarmResource;
use App\Modules\Equipment\Resource\EquipmentSummaryResource;
use App\Modules\Equipment\Service\EquipmentAlarmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class EquipmentAlarmController extends Controller
{
    public function __construct(
        private readonly EquipmentAlarmService $alarms,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'equipment_id' => ['sometimes', 'integer', 'min:1', 'exists:mes_equipments,id'],
            'level' => ['sometimes', Rule::in(EquipmentAlarm::LEVELS)],
            'status' => ['sometimes', Rule::in(EquipmentAlarm::STATUSES)],
            'start_date' => ['sometimes', 'date_format:Y-m-d'],
            'end_date' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = EquipmentAlarm::query()
            ->with(EquipmentAlarm::RELATIONS)
            ->when(
                $data['equipment_id'] ?? null,
                fn ($query, int $equipmentId) => $query->where('equipment_id', $equipmentId),
            )
            ->when(
                $data['level'] ?? null,
                fn ($query, string $level) => $query->where('level', $level),
            )
            ->when(
                $data['status'] ?? null,
                fn ($query, string $status) => $query->where('status', $status),
            )
            ->when(
                $data['start_date'] ?? null,
                fn ($query, string $date) => $query->where('triggered_at', '>=', $this->businessDayStart($date)),
            )
            ->when(
                $data['end_date'] ?? null,
                fn ($query, string $date) => $query->where('triggered_at', '<', $this->businessDayStart($date)->addDay()),
            )
            ->orderByDesc('triggered_at')
            ->orderByDesc('id');

        return ApiResponse::paginated(
            $query->paginate(max(1, min((int) ($data['per_page'] ?? 15), 100))),
            EquipmentAlarmResource::class,
        );
    }

    public function options(): JsonResponse
    {
        // shortcut: one page covers a demo factory's devices; add paging when it grows.
        $equipments = Equipment::query()
            ->orderBy('id')
            ->limit(200)
            ->get(['id', 'code', 'name', 'status']);

        return ApiResponse::success([
            'equipments' => EquipmentSummaryResource::collection($equipments)->resolve(),
        ]);
    }

    public function confirm(Request $request, EquipmentAlarm $alarm): JsonResponse
    {
        return ApiResponse::success(EquipmentAlarmResource::make(
            $this->alarms->confirm($alarm, $request->user()),
        ));
    }

    public function process(Request $request, EquipmentAlarm $alarm): JsonResponse
    {
        $data = $request->validate([
            'handling_note' => ['required', 'string', 'max:1000'],
        ], [
            'handling_note.required' => '请填写处理说明',
        ]);

        return ApiResponse::success(EquipmentAlarmResource::make(
            $this->alarms->process($alarm, $request->user(), $data['handling_note']),
        ));
    }

    public function close(Request $request, EquipmentAlarm $alarm): JsonResponse
    {
        $data = $request->validate([
            'handling_note' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        return ApiResponse::success(EquipmentAlarmResource::make(
            $this->alarms->close($alarm, $request->user(), $data['handling_note'] ?? null),
        ));
    }

    private function businessDayStart(string $date): Carbon
    {
        return Carbon::parse($date, (string) config('app.business_timezone', 'Asia/Shanghai'))
            ->startOfDay()
            ->utc();
    }
}
