<?php

namespace App\Core\DataScope;

use App\Core\Exceptions\ApiException;
use App\Modules\System\Service\AccessControlService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Rejects writes that target data outside the current user's scope.
 *
 * Called from services, the single entry point every caller (HTTP, queue,
 * Artisan) goes through, so the check cannot be bypassed at the controller.
 * Processes without a user context resolve to `all` and are never restricted.
 *
 * Scoping is opt-in per model: line-scoped models implement `HasDataScope`,
 * factory-scoped ones (inventory) implement `HasFactoryScope`. A model with
 * neither is passed through, because basic data like products is shared
 * across factories by design.
 */
class DataScopeGuard
{
    private ?DataScope $scope = null;

    private ?int $scopeUserId = null;

    /**
     * @var array<int, int>|null
     */
    private ?array $scopedLineIds = null;

    public function __construct(
        private readonly AccessControlService $access,
        private readonly Request $request,
    ) {}

    /**
     * Cached per user: the framework can reuse a resolved controller across
     * requests, so the cache must key on who is calling instead of letting
     * the first resolution win.
     */
    public function scope(): DataScope
    {
        $user = $this->request->user();

        if ($this->scope === null || $this->scopeUserId !== $user?->id) {
            $this->scopeUserId = $user?->id;
            $this->scope = $user === null
                ? DataScope::all()
                : $this->access->resolveDataScope($user);
            $this->scopedLineIds = null;
        }

        return $this->scope;
    }

    /**
     * @throws ApiException when the row is outside the scope
     */
    public function ensure(Model $model): void
    {
        $scope = $this->scope();

        if ($scope->isAll()) {
            return;
        }

        if ($model instanceof HasDataScope) {
            if ($this->isOutsideScope($model->dataScopeProductionLineId())) {
                throw new ApiException('该数据不在您的数据范围内', 403, status: 403);
            }

            return;
        }

        if ($model instanceof HasFactoryScope) {
            if (! DataScopeQuery::factoryInScope($model->dataScopeFactoryId(), $scope)) {
                throw new ApiException('该数据不在您的数据范围内', 403, status: 403);
            }

            return;
        }

        // Fail closed: a model reaching a scoped service without declaring how
        // it resolves to a line or factory must not be silently allowed, or a
        // forgotten interface quietly opens the write to every factory.
        Log::error('数据范围守卫遇到未声明归属的模型', ['model' => $model::class]);

        throw new ApiException('该数据不在您的数据范围内', 403, status: 403);
    }

    /**
     * @throws ApiException when the production line is outside the scope
     */
    public function ensureLine(?int $productionLineId): void
    {
        if ($this->isOutsideScope($productionLineId)) {
            throw new ApiException('生产产线不在您的数据范围内', 403, status: 403);
        }
    }

    private function isOutsideScope(?int $productionLineId): bool
    {
        $scope = $this->scope();

        if ($scope->isAll()) {
            return false;
        }

        $this->scopedLineIds ??= DataScopeQuery::scopedProductionLineIds($scope);

        return $productionLineId === null
            || ! in_array($productionLineId, $this->scopedLineIds, true);
    }
}
