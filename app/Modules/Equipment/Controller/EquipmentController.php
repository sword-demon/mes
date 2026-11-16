<?php

namespace App\Modules\Equipment\Controller;

use App\Core\Exceptions\ApiException;
use App\Core\Services\EntityService;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Equipment\FormRequest\StoreEquipmentRequest;
use App\Modules\Equipment\FormRequest\UpdateEquipmentRequest;
use App\Modules\Equipment\Model\Equipment;
use App\Modules\Equipment\Model\EquipmentData;
use App\Modules\Equipment\Resource\EquipmentResource;
use App\Modules\Equipment\Service\EquipmentService;
use App\Modules\Organization\Model\Employee as OrganizationEmployee;
use App\Modules\Organization\Model\ProductionLine;
use App\Modules\Organization\Model\Workshop;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EquipmentController extends Controller
{
    private const array RELATIONS = [
        'workshop',
        'productionLine',
        'owner',
    ];

    public function __construct(
        private readonly EquipmentService $equipments,
        private readonly EntityService $entities,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Equipment::query()->with(self::RELATIONS);

        return ApiResponse::paginated(
            $this->entities->paginateQuery(
                $query,
                array_filter(
                    $request->only(['workshop_id', 'production_line_id', 'type', 'status', 'keyword', 'per_page']),
                    static fn (mixed $value): bool => is_scalar($value),
                ),
                ['code', 'name', 'model'],
                filterColumns: ['workshop_id', 'production_line_id', 'type', 'status'],
            ),
            EquipmentResource::class,
        );
    }

    public function options(): JsonResponse
    {
        // shortcut: one page covers a demo factory's master data; add paging when it grows.
        $workshops = Workshop::query()
            ->where('status', true)
            ->orderBy('id')
            ->limit(200)
            ->get(['id', 'code', 'name'])
            ->map(fn (Workshop $workshop): array => $workshop->only(['id', 'code', 'name']));

        $lines = ProductionLine::query()
            ->where('status', true)
            ->orderBy('id')
            ->limit(200)
            ->get(['id', 'workshop_id', 'code', 'name'])
            ->map(fn (ProductionLine $line): array => $line->only(['id', 'workshop_id', 'code', 'name']));

        $employees = OrganizationEmployee::query()
            ->where('status', true)
            ->orderBy('id')
            ->limit(200)
            ->get(['id', 'employee_no', 'name'])
            ->map(fn (OrganizationEmployee $employee): array => $employee->only(['id', 'employee_no', 'name']));

        $equipments = Equipment::query()
            ->with(self::RELATIONS)
            ->available()
            ->orderBy('id')
            ->limit(200)
            ->get();

        return ApiResponse::success([
            'workshops' => $workshops,
            'production_lines' => $lines,
            'employees' => $employees,
            'equipments' => EquipmentResource::collection($equipments)->resolve(),
        ]);
    }

    public function monitor(): JsonResponse
    {
        $statusRows = Equipment::query()
            ->where('enabled', true)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $statusCounts = array_fill_keys(Equipment::STATUSES, 0);

        foreach ($statusRows as $status => $count) {
            if (array_key_exists($status, $statusCounts)) {
                $statusCounts[$status] = (int) $count;
            }
        }

        // shortcut: one page covers a demo factory's fleet; add paging when it grows.
        $equipments = Equipment::query()
            ->where('enabled', true)
            ->with([...self::RELATIONS, 'latestData'])
            ->orderBy('id')
            ->limit(200)
            ->get();

        return ApiResponse::success([
            'items' => EquipmentResource::collection($equipments)->resolve(),
            'status_counts' => $statusCounts,
        ]);
    }

    public function history(Request $request, Equipment $equipment): JsonResponse
    {
        $data = $request->validate([
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
        ], [
            'start_at.required' => '请选择开始时间',
            'end_at.required' => '请选择结束时间',
            'end_at.after' => '结束时间必须晚于开始时间',
        ]);
        $start = CarbonImmutable::parse($data['start_at'])->utc();
        $end = CarbonImmutable::parse($data['end_at'])->utc();

        if ((int) $start->diffInSeconds($end) > 86400) {
            throw new ApiException('查询时间区间不能超过 24 小时', 422);
        }

        // shortcut: raw samples are capped at 24h; bucket them once longer ranges are needed.
        $samples = EquipmentData::query()
            ->where('equipment_id', $equipment->id)
            ->where('message_type', 'data')
            ->whereBetween('recorded_at', [
                $start->format('Y-m-d H:i:s.u'),
                $end->format('Y-m-d H:i:s.u'),
            ])
            ->orderBy('recorded_at')
            ->get(['id', 'recorded_at', 'status', 'temperature', 'speed', 'pressure', 'output', 'runtime_seconds']);

        return ApiResponse::success([
            'equipment' => [
                'id' => $equipment->id,
                'code' => $equipment->code,
                'name' => $equipment->name,
                'status' => $equipment->status,
                'status_label' => $equipment->statusLabel(),
            ],
            'start_at' => $start->toISOString(),
            'end_at' => $end->toISOString(),
            'items' => $samples->map(fn (EquipmentData $sample): array => [
                'id' => $sample->id,
                'recorded_at' => $sample->recorded_at->toISOString(),
                'status' => $sample->status,
                'temperature' => $sample->temperature,
                'speed' => $sample->speed,
                'pressure' => $sample->pressure,
                'output' => $sample->output,
                'runtime_seconds' => $sample->runtime_seconds,
            ])->values(),
        ]);
    }

    public function store(StoreEquipmentRequest $request): JsonResponse
    {
        return ApiResponse::success(EquipmentResource::make(
            $this->equipments->create($request->validated())->load(self::RELATIONS),
        ));
    }

    public function update(UpdateEquipmentRequest $request, Equipment $equipment): JsonResponse
    {
        return ApiResponse::success(EquipmentResource::make(
            $this->equipments->update($equipment, $request->validated())->load(self::RELATIONS),
        ));
    }
}
