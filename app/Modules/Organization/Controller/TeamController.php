<?php

namespace App\Modules\Organization\Controller;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Organization\FormRequest\StoreTeamRequest;
use App\Modules\Organization\FormRequest\UpdateTeamRequest;
use App\Modules\Organization\Model\Team;
use App\Modules\Organization\Resource\TeamResource;
use App\Modules\Organization\Service\OrganizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function __construct(private readonly OrganizationService $organization) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::paginated(
            $this->organization->paginate(
                Team::class,
                ['workshop'],
                $request->only(['workshop_id', 'status', 'keyword', 'per_page']),
                ['code', 'name'],
                ['workshop', 'workshop.parentFactory'],
            ),
            TeamResource::class,
        );
    }

    public function store(StoreTeamRequest $request): JsonResponse
    {
        return ApiResponse::success(TeamResource::make(
            Team::query()->create($request->validated())->load('workshop'),
        ));
    }

    public function update(UpdateTeamRequest $request, Team $team): JsonResponse
    {
        return ApiResponse::success(TeamResource::make(
            tap($team)->update($request->validated())->load('workshop'),
        ));
    }

    public function destroy(Team $team): JsonResponse
    {
        $this->organization->delete($team, [
            'employees' => '班组下还有员工，只能停用',
        ]);

        return ApiResponse::success();
    }
}
