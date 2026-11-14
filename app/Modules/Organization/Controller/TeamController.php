<?php

namespace App\Modules\Organization\Controller;

use App\Core\Services\EntityService;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Organization\FormRequest\StoreTeamRequest;
use App\Modules\Organization\FormRequest\UpdateTeamRequest;
use App\Modules\Organization\Model\Team;
use App\Modules\Organization\Resource\TeamResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function __construct(private readonly EntityService $entities) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::paginated(
            $this->entities->paginate(
                Team::class,
                ['workshop'],
                $request->only(['workshop_id', 'status', 'keyword', 'per_page']),
                enabledRelations: ['workshop', 'workshop.parentFactory'],
                filterColumns: ['workshop_id'],
            ),
            TeamResource::class,
        );
    }

    public function store(StoreTeamRequest $request): JsonResponse
    {
        return ApiResponse::success(TeamResource::make(
            $this->entities->create(Team::class, $request->validated())->load('workshop'),
        ));
    }

    public function update(UpdateTeamRequest $request, Team $team): JsonResponse
    {
        return ApiResponse::success(TeamResource::make(
            $this->entities->update($team, $request->validated())->load('workshop'),
        ));
    }

    public function destroy(Team $team): JsonResponse
    {
        $this->entities->delete($team, [
            'employees' => '班组下还有员工，只能停用',
        ]);

        return ApiResponse::success();
    }
}
