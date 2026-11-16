<?php

namespace App\Modules\System\Service;

use App\Core\Exceptions\ApiException;
use App\Modules\System\Model\User;
use Illuminate\Pagination\LengthAwarePaginator;

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
            ->with(['roles', 'department', 'employee'])
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

        $user = User::query()->create($data);
        $user->roles()->sync($roleIds);

        $this->operationLogs->record(
            action: 'system.user.created',
            actionName: '新增用户',
            objectType: '用户',
            objectId: $user->id,
            objectName: $user->name,
        );

        return $user->load('roles');
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

        $user->loadMissing('roles');
        $before = $this->snapshot($user);
        $user->update($data);
        $user->load('roles');

        $this->operationLogs->record(
            action: 'system.user.updated',
            actionName: '编辑用户',
            objectType: '用户',
            objectId: $user->id,
            objectName: $user->name,
            before: $before,
            after: $this->snapshot($user),
        );

        return $user;
    }

    public function resetPassword(User $actor, User $user, string $password): void
    {
        $this->access->ensureCanManageUser($actor, $user);

        $user->update(['password' => $password]);
        $user->tokens()->delete();

        $this->operationLogs->record(
            action: 'system.user.password-reset',
            actionName: '重置密码',
            objectType: '用户',
            objectId: $user->id,
            objectName: $user->name,
        );
    }

    /**
     * @param  array<int, int>  $roleIds
     */
    public function assignRoles(User $actor, User $user, array $roleIds): User
    {
        $this->access->ensureCanManageUser($actor, $user);
        $this->access->ensureCanGrantRoles($actor, $roleIds);

        $user->load('roles');
        $before = $this->snapshot($user);
        $user->roles()->sync($roleIds);
        $user->load('roles');

        $this->operationLogs->record(
            action: 'system.user.roles-assigned',
            actionName: '分配角色',
            objectType: '用户',
            objectId: $user->id,
            objectName: $user->name,
            before: $before,
            after: $this->snapshot($user),
        );

        return $user;
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
            '角色' => $user->roles->pluck('name')->sort()->values()->all(),
        ];
    }
}
