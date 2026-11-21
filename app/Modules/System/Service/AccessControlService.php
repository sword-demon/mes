<?php

namespace App\Modules\System\Service;

use App\Core\DataScope\DataScope;
use App\Core\Exceptions\ApiException;
use App\Modules\System\Model\Menu;
use App\Modules\System\Model\Permission;
use App\Modules\System\Model\Role;
use App\Modules\System\Model\User;
use Illuminate\Support\Collection;

class AccessControlService
{
    /**
     * The widest data scope the user holds; `null` means unrestricted.
     */
    public const SCOPE_ALL = 'all';

    /**
     * Permission codes granted by the user's enabled roles.
     *
     * @return array<int, string>
     */
    public function permissionCodes(User $user): array
    {
        $roles = $user->activeRoles()->get();

        if ($roles->contains('code', Role::SUPER_ADMIN)) {
            return Permission::query()->orderBy('code')->pluck('code')->all();
        }

        if ($roles->isEmpty()) {
            return [];
        }

        return Permission::query()
            ->whereHas('roles', fn ($query) => $query->whereKey($roles->modelKeys())->where('status', true))
            ->orderBy('code')
            ->pluck('code')
            ->all();
    }

    public function allows(User $user, string $permissionCode): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return in_array($permissionCode, $this->permissionCodes($user), true);
    }

    public function isSuperAdmin(User $user): bool
    {
        if ($user->relationLoaded('activeRoles')) {
            return $user->activeRoles->contains('code', Role::SUPER_ADMIN);
        }

        return $user->activeRoles()->where('code', Role::SUPER_ADMIN)->exists();
    }

    /**
     * Permission codes from the roles already loaded on the model.
     *
     * Callers resolving many users can eager load `activeRoles.permissions`
     * and pass the result to `dataScope()` to avoid a query per user.
     *
     * @return array<int, string>
     */
    public function grantedCodes(User $user): array
    {
        return $user->activeRoles
            ->flatMap(fn (Role $role) => $role->permissions)
            ->pluck('code')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Resolve the user's data scope from the `data.scope.*` grants.
     *
     * `all` wins over a narrower scope; no grant means no data at all, so a
     * missing permission fails closed instead of silently showing everything.
     * This is the display value; use `resolveDataScope()` when the scope must
     * also carry the factory and production line identifiers.
     *
     * @param  array<int, string>|null  $granted  reuse a permission list already loaded for the same user
     */
    public function dataScope(User $user, ?array $granted = null): string
    {
        if ($this->isSuperAdmin($user)) {
            return self::SCOPE_ALL;
        }

        return $this->grantedLevel($granted ?? $this->permissionCodes($user));
    }

    /**
     * Resolve the enforceable data scope, including the factory and the
     * production lines the user is responsible for.
     *
     * A `factory` or `line` grant without the matching assignment resolves to
     * `none` so a half-configured account never sees the whole plant.
     *
     * @param  array<int, string>|null  $granted  reuse a permission list already loaded for the same user
     */
    public function resolveDataScope(User $user, ?array $granted = null): DataScope
    {
        if ($this->isSuperAdmin($user)) {
            return DataScope::all();
        }

        $level = $this->grantedLevel($granted ?? $this->permissionCodes($user));

        if ($level === DataScope::ALL) {
            return DataScope::all();
        }

        if ($level === DataScope::FACTORY) {
            return $user->factory_id === null
                ? DataScope::none()
                : new DataScope(DataScope::FACTORY, (int) $user->factory_id);
        }

        if ($level !== DataScope::LINE) {
            return DataScope::none();
        }

        $productionLineIds = $user->relationLoaded('productionLines')
            ? $user->productionLines->pluck('id')->sort()->values()->map(fn (mixed $id): int => (int) $id)->all()
            : $user->productionLines()
                ->orderBy('mes_production_lines.id')
                ->pluck('mes_production_lines.id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();

        if ($user->factory_id === null || $productionLineIds === []) {
            return DataScope::none();
        }

        return new DataScope(DataScope::LINE, (int) $user->factory_id, $productionLineIds);
    }

    /**
     * The widest `data.scope.*` grant, independent of whether the user has
     * been assigned a factory or production line yet.
     *
     * @param  array<int, string>  $granted
     */
    private function grantedLevel(array $granted): string
    {
        foreach (['data.scope.all', 'data.scope.factory', 'data.scope.line'] as $code) {
            if (in_array($code, $granted, true)) {
                return substr($code, strlen('data.scope.'));
            }
        }

        return DataScope::NONE;
    }

    /**
     * Only a super admin may act on a super admin account.
     *
     * @throws ApiException
     */
    public function ensureCanManageUser(User $actor, User $target): void
    {
        if ($this->isSuperAdmin($target) && ! $this->isSuperAdmin($actor)) {
            throw new ApiException('只有超级管理员可以操作超级管理员账号', 403, status: 403);
        }
    }

    /**
     * Granting roles requires the assign-roles permission, and only a super
     * admin may hand out the super admin role.
     *
     * @param  array<int, int>  $roleIds
     *
     * @throws ApiException
     */
    public function ensureCanGrantRoles(User $actor, array $roleIds): void
    {
        if ($roleIds === []) {
            return;
        }

        // The create payload carries `role_ids`, so the create permission alone
        // must not be enough to hand out roles.
        if (! $this->allows($actor, 'system.users.assign-roles')) {
            throw new ApiException('没有分配角色的权限', 403, status: 403);
        }

        if ($this->isSuperAdmin($actor)) {
            return;
        }

        $grantsSuperAdmin = Role::query()
            ->whereKey($roleIds)
            ->where('code', Role::SUPER_ADMIN)
            ->exists();

        if ($grantsSuperAdmin) {
            throw new ApiException('只有超级管理员可以授予超级管理员角色', 403, status: 403);
        }
    }

    /**
     * Menus visible to the user, nested by parent and sorted for display.
     *
     * @param  array<int, string>|null  $granted  reuse a permission list already loaded for the same user
     * @return array<int, array<string, mixed>>
     */
    public function menuTree(User $user, ?array $granted = null): array
    {
        $granted ??= $this->permissionCodes($user);
        $permissions = Permission::query()->pluck('code', 'id');

        $menus = Menu::query()->orderBy('sort')->orderBy('id')->get();

        if ($this->isSuperAdmin($user)) {
            return Menu::toTree($menus);
        }

        $visible = $menus->filter(fn (Menu $menu) => $this->isReachable($menu, $menus, $permissions, $granted));

        return Menu::toTree($visible);
    }

    /**
     * A menu shows when its permission is granted, or when a granted menu sits beneath it.
     *
     * A menu with no permission (or one whose permission row is gone) is hidden: an
     * unresolvable grant must fail closed, not leak the entry to everyone.
     *
     * @param  Collection<int, Menu>  $menus
     * @param  Collection<int, string>  $permissions
     * @param  array<int, string>  $granted
     */
    private function isReachable(Menu $menu, Collection $menus, Collection $permissions, array $granted): bool
    {
        $code = $menu->permission_id === null ? null : $permissions[$menu->permission_id] ?? null;

        if ($code !== null && in_array($code, $granted, true)) {
            return true;
        }

        return $menus
            ->where('parent_id', $menu->id)
            ->contains(fn (Menu $child) => $this->isReachable($child, $menus, $permissions, $granted));
    }
}
