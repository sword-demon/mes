<?php

namespace App\Modules\System\Service;

use App\Core\Exceptions\ApiException;
use App\Modules\System\Model\Permission;
use App\Modules\System\Model\Role;
use App\Modules\System\Model\User;
use Illuminate\Pagination\LengthAwarePaginator;

class RoleService
{
    public function __construct(private readonly AccessControlService $access) {}

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

        $role = Role::query()->create($data);
        $role->permissions()->sync($permissionIds);

        return $role->load('permissions');
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

        $role->update($data);

        if ($permissionIds !== null) {
            $role->permissions()->sync($permissionIds);
        }

        return $role->load('permissions');
    }

    public function delete(Role $role): void
    {
        if ($role->isSuperAdmin()) {
            throw new ApiException('超级管理员角色不允许删除', 403, status: 403);
        }

        if ($role->users()->exists()) {
            throw new ApiException('角色已分配给用户，不能删除', 403, status: 403);
        }

        $role->delete();
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
