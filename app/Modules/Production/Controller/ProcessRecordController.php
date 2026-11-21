<?php

namespace App\Modules\Production\Controller;

use App\Core\DataScope\DataScopeGuard;
use App\Core\Services\EntityService;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Production\FormRequest\ReasonProcessRecordRequest;
use App\Modules\Production\FormRequest\StartProcessRecordRequest;
use App\Modules\Production\Model\ProcessRecord;
use App\Modules\Production\Resource\ProcessRecordResource;
use App\Modules\Production\Service\ProcessRecordService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProcessRecordController extends Controller
{
    private const array RELATIONS = [
        'productionTask.productionOrder',
        'routeOperation.process',
        'employee',
        'team',
        'equipment',
    ];

    public function __construct(
        private readonly ProcessRecordService $records,
        private readonly EntityService $entities,
        private readonly DataScopeGuard $dataScope,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $orderId = $request->query('production_order_id');
        $status = $request->query('status');
        $sn = $request->query('sn');

        $query = ProcessRecord::query()
            ->with(self::RELATIONS)
            ->inDataScope($this->dataScope->scope())
            ->when(
                is_string($orderId) && $orderId !== '',
                fn (Builder $query) => $query->whereHas(
                    'productionTask',
                    fn (Builder $task) => $task->where('production_order_id', $orderId),
                ),
            )
            ->when(
                is_string($sn) && $sn !== '',
                fn (Builder $query) => $query->where('sn', $sn),
            )
            ->when(
                is_string($status) && $status !== '',
                fn (Builder $query) => $query->where('status', $status),
            );

        return ApiResponse::paginated(
            $this->entities->paginateQuery(
                $query,
                array_filter(
                    $request->only(['production_task_id', 'per_page']),
                    static fn (mixed $value): bool => is_scalar($value),
                ),
                filterColumns: ['production_task_id'],
            ),
            ProcessRecordResource::class,
        );
    }

    public function start(StartProcessRecordRequest $request, ProcessRecord $record): JsonResponse
    {
        return $this->respond($this->records->start($record, $request->validated()));
    }

    public function complete(ProcessRecord $record): JsonResponse
    {
        return $this->respond($this->records->complete($record));
    }

    public function submit(ProcessRecord $record): JsonResponse
    {
        return $this->respond($this->records->submit($record));
    }

    public function receive(ProcessRecord $record): JsonResponse
    {
        return $this->respond($this->records->receive($record));
    }

    public function exception(ReasonProcessRecordRequest $request, ProcessRecord $record): JsonResponse
    {
        return $this->respond($this->records->exception(
            $record,
            (string) $request->validated('reason'),
        ));
    }

    public function rework(ReasonProcessRecordRequest $request, ProcessRecord $record): JsonResponse
    {
        return $this->respond($this->records->rework(
            $record,
            (string) $request->validated('reason'),
        ));
    }

    private function respond(ProcessRecord $record): JsonResponse
    {
        return ApiResponse::success(
            ProcessRecordResource::make($record->load(self::RELATIONS)),
        );
    }
}
