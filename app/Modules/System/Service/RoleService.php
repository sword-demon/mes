<?php

namespace App\Modules\System\Service;

use App\Core\Exceptions\ApiException;
use App\Modules\System\Model\Permission;
use App\Modules\System\Model\Role;
use App\Modules\System\Model\User;
use App\Modules\System\ValueObject\OperationLogEntry;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class RoleService
{
    public function __construct(
        private readonly AccessControlService $access,
        private readonly OperationLogService $operationLogs,
    ) {}

    /**
     * A role may not hand out a permission its author does not hold.
     *
     * @param  array<int, int>  $permissionIds
     *
     * @throws ApiException
     */
    public function ensureCanGrantPermissions(User $actor, array $permissionIds): void
    {
        if ($permissionIds === [] || $this->access->isSuperAdmin($actor)) {
            return;
        }

        $owned = Permission::query()
            ->whereIn('code', $this->access->permissionCodes($actor))
            ->pluck('id')
            ->all();

        if (array_diff($permissionIds, $owned) !== []) {
            throw new ApiException('不能授予自己没有的权限', 403, status: 403);
        }
    }

    /**
     * @return LengthAwarePaginator<int, Role>
     */
    public function paginate(): LengthAwarePaginator
    {
        return Role::query()
            ->with('permissions')
            ->withCount('users')
            ->orderBy('id')
            ->paginate(50);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, int>  $permissionIds
     */
    public function create(array $data, array $permissionIds): Role
    {
        $this->ensureReservedCodeIsNotUsed($data['code'] ?? null);

        return DB::transaction(function () use ($data, $permissionIds): Role {
            $role = Role::query()->create($data);
            $role->permissions()->sync($permissionIds);

            $this->operationLogs->record(new OperationLogEntry(
                action: 'system.role.created',
                actionName: '新增角色',
                objectType: '角色',
                objectId: $role->id,
                objectName: $role->name,
            ));

            return $role->load('permissions');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, int>|null  $permissionIds
     */
    public function update(Role $role, array $data, ?array $permissionIds): Role
    {
        if ($role->isSuperAdmin()) {
            if (array_key_exists('status', $data) && $data['status'] === false) {
                throw new ApiException('超级管理员角色不允许停用', 403, status: 403);
            }

            if (array_key_exists('code', $data) && $data['code'] !== Role::SUPER_ADMIN) {
                throw new ApiException('超级管理员角色编码不允许修改', 403, status: 403);
            }
        } elseif (($data['code'] ?? null) === Role::SUPER_ADMIN) {
            throw new ApiException('超级管理员角色编码为系统保留，不能使用', 403, status: 403);
        }

        return DB::transaction(function () use ($role, $data, $permissionIds): Role {
            $role->loadMissing('permissions');
            $before = $this->snapshot($role);
            $role->update($data);

            if ($permissionIds !== null) {
                $role->permissions()->sync($permissionIds);
            }

            $role->load('permissions');

            $this->operationLogs->record(new OperationLogEntry(
                action: 'system.role.updated',
                actionName: '编辑角色',
                objectType: '角色',
                objectId: $role->id,
                objectName: $role->name,
                before: $before,
                after: $this->snapshot($role),
            ));

            return $role;
        });
    }

    public function delete(Role $role): void
    {
        if ($role->isSuperAdmin()) {
            throw new ApiException('超级管理员角色不允许删除', 403, status: 403);
        }

        if ($role->users()->exists()) {
            throw new ApiException('角色已分配给用户，不能删除', 403, status: 403);
        }

        DB::transaction(function () use ($role): void {
            $role->delete();

            $this->operationLogs->record(new OperationLogEntry(
                action: 'system.role.deleted',
                actionName: '删除角色',
                objectType: '角色',
                objectId: $role->id,
                objectName: $role->name,
            ));
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Role $role): array
    {
        return [
            '名称' => $role->name,
            '编码' => $role->code,
            '状态' => $role->status ? '启用' : '停用',
            '权限' => $role->permissions->pluck('code')->sort()->values()->all(),
        ];
    }

    /**
     * `super_admin` bypasses every check, so only the seeder may create it.
     *
     * @throws ApiException
     */
    private function ensureReservedCodeIsNotUsed(?string $code): void
    {
        if ($code === Role::SUPER_ADMIN) {
            throw new ApiException('超级管理员角色编码为系统保留，不能使用', 403, status: 403);
        }
    }
}
