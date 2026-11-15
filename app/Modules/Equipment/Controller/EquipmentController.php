<?php

namespace App\Modules\Equipment\Controller;

use App\Core\Services\EntityService;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Equipment\FormRequest\StoreEquipmentRequest;
use App\Modules\Equipment\FormRequest\UpdateEquipmentRequest;
use App\Modules\Equipment\Model\Equipment;
use App\Modules\Equipment\Resource\EquipmentResource;
use App\Modules\Equipment\Service\EquipmentService;
use App\Modules\Organization\Model\Employee as OrganizationEmployee;
use App\Modules\Organization\Model\ProductionLine;
use App\Modules\Organization\Model\Workshop;
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
