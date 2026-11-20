<?php

namespace App\Modules\System\Service;

use App\Core\Exceptions\ApiException;
use App\Modules\System\Model\User;
use App\Modules\System\ValueObject\OperationLogEntry;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class UserService
{
    public function __construct(
        private readonly AccessControlService $access,
        private readonly OperationLogService $operationLogs,
    ) {}

    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(?string $keyword = null, ?bool $status = null): LengthAwarePaginator
    {
        return User::query()
            ->with(['roles', 'department', 'employee', 'organizationFactory', 'productionLines'])
            ->when($keyword, function ($query, string $keyword): void {
                $query->where(fn ($inner) => $inner
                    ->where('name', 'like', "%{$keyword}%")
                    ->orWhere('email', 'like', "%{$keyword}%")
                    ->orWhere('phone', 'like', "%{$keyword}%"));
            })
            ->when($status !== null, function ($query) use ($status): void {
                $query->where('status', $status);
            })
            ->orderBy('id')
            ->paginate(15);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, int>  $roleIds
     */
    public function create(User $actor, array $data, array $roleIds): User
    {
        $this->access->ensureCanGrantRoles($actor, $roleIds);
        $productionLineIds = $this->pullProductionLineIds($data) ?? [];

        return DB::transaction(function () use ($data, $roleIds, $productionLineIds): User {
            $user = User::query()->create($data);
            $user->roles()->sync($roleIds);
            $user->productionLines()->sync($productionLineIds);

            $this->operationLogs->record(new OperationLogEntry(
                action: 'system.user.created',
                actionName: '新增用户',
                objectType: '用户',
                objectId: $user->id,
                objectName: $user->name,
            ));

            return $user->load(['roles', 'organizationFactory', 'productionLines']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, User $user, array $data): User
    {
        $this->access->ensureCanManageUser($actor, $user);

        if (array_key_exists('status', $data) && $data['status'] === false && $user->is($actor)) {
            throw new ApiException('不能停用当前登录账号', 403, status: 403);
        }

        $productionLineIds = $this->pullProductionLineIds($data);

        return DB::transaction(function () use ($user, $data, $productionLineIds): User {
            $user->loadMissing(['roles', 'organizationFactory', 'productionLines']);
            $before = $this->snapshot($user);
            $user->update($data);

            if ($productionLineIds !== null) {
                $user->productionLines()->sync($productionLineIds);
            }

            $user->load(['roles', 'organizationFactory', 'productionLines']);

            $this->operationLogs->record(new OperationLogEntry(
                action: 'system.user.updated',
                actionName: '编辑用户',
                objectType: '用户',
                objectId: $user->id,
                objectName: $user->name,
                before: $before,
                after: $this->snapshot($user),
            ));

            return $user;
        });
    }

    public function resetPassword(User $actor, User $user, string $password): void
    {
        $this->access->ensureCanManageUser($actor, $user);

        DB::transaction(function () use ($user, $password): void {
            $user->update(['password' => $password]);
            $user->tokens()->delete();

            $this->operationLogs->record(new OperationLogEntry(
                action: 'system.user.password-reset',
                actionName: '重置密码',
                objectType: '用户',
                objectId: $user->id,
                objectName: $user->name,
            ));
        });
    }

    /**
     * @param  array<int, int>  $roleIds
     */
    public function assignRoles(User $actor, User $user, array $roleIds): User
    {
        $this->access->ensureCanManageUser($actor, $user);
        $this->access->ensureCanGrantRoles($actor, $roleIds);

        return DB::transaction(function () use ($user, $roleIds): User {
            $user->load('roles');
            $before = $this->snapshot($user);
            $user->roles()->sync($roleIds);
            $user->load('roles');

            $this->operationLogs->record(new OperationLogEntry(
                action: 'system.user.roles-assigned',
                actionName: '分配角色',
                objectType: '用户',
                objectId: $user->id,
                objectName: $user->name,
                before: $before,
                after: $this->snapshot($user),
            ));

            return $user;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(User $user): array
    {
        return [
            '姓名' => $user->name,
            '邮箱' => $user->email,
            '手机号' => $user->phone,
            '状态' => $user->status ? '启用' : '停用',
            '岗位' => $user->position,
            '工厂' => $user->organizationFactory?->name,
            '负责产线' => $user->productionLines->pluck('name')->sort()->values()->all(),
            '角色' => $user->roles->pluck('name')->sort()->values()->all(),
        ];
    }

    /**
     * Remove `production_line_ids` from the payload and return it.
     *
     * `null` means the request never mentioned production lines, so an update
     * must leave the current assignment untouched; an empty array clears it.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, int>|null
     */
    private function pullProductionLineIds(array &$data): ?array
    {
        if (! array_key_exists('production_line_ids', $data)) {
            return null;
        }

        $ids = array_map('intval', (array) ($data['production_line_ids'] ?? []));
        unset($data['production_line_ids']);

        return array_values(array_unique($ids));
    }
}
