<?php

namespace App\Modules\System\Service;

use App\Core\Exceptions\ApiException;
use App\Modules\System\Model\Role;
use Illuminate\Pagination\LengthAwarePaginator;

class RoleService
{
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
        if ($role->code === Role::SUPER_ADMIN) {
            if (array_key_exists('status', $data) && $data['status'] === false) {
                throw new ApiException('超级管理员角色不允许停用', 403, status: 403);
            }

            if (array_key_exists('code', $data) && $data['code'] !== Role::SUPER_ADMIN) {
                throw new ApiException('超级管理员角色编码不允许修改', 403, status: 403);
            }
        }

        $role->update($data);

        if ($permissionIds !== null) {
            $role->permissions()->sync($permissionIds);
        }

        return $role->load('permissions');
    }

    public function delete(Role $role): void
    {
        if ($role->code === Role::SUPER_ADMIN) {
            throw new ApiException('超级管理员角色不允许删除', 403, status: 403);
        }

        if ($role->users()->exists()) {
            throw new ApiException('角色已分配给用户，不能删除', 403, status: 403);
        }

        $role->delete();
    }
}
