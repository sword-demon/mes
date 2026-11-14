<?php

namespace App\Modules\System\Service;

use App\Modules\System\Model\Menu;
use App\Modules\System\Model\Permission;
use App\Modules\System\Model\Role;
use App\Modules\System\Model\User;
use Illuminate\Support\Collection;

class AccessControlService
{
    /**
     * Permission codes granted by the user's enabled roles.
     *
     * @return array<int, string>
     */
    public function permissionCodes(User $user): array
    {
        $roles = $user->roles()->where('status', true)->get();

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
        return in_array($permissionCode, $this->permissionCodes($user), true);
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

        $visible = $menus->filter(fn (Menu $menu) => $this->isReachable($menu, $menus, $permissions, $granted));

        return $this->nest($visible, null);
    }

    /**
     * A menu shows when it is granted, or when a granted menu sits beneath it.
     *
     * @param  Collection<int, Menu>  $menus
     * @param  Collection<int, string>  $permissions
     * @param  array<int, string>  $granted
     */
    private function isReachable(Menu $menu, Collection $menus, Collection $permissions, array $granted): bool
    {
        $code = $menu->permission_id === null ? null : $permissions[$menu->permission_id] ?? null;

        if ($code === null || in_array($code, $granted, true)) {
            return true;
        }

        return $menus
            ->where('parent_id', $menu->id)
            ->contains(fn (Menu $child) => $this->isReachable($child, $menus, $permissions, $granted));
    }

    /**
     * @param  Collection<int, Menu>  $menus
     * @return array<int, array<string, mixed>>
     */
    private function nest(Collection $menus, ?int $parentId): array
    {
        return $menus
            ->where('parent_id', $parentId)
            ->map(fn (Menu $menu) => [
                'id' => $menu->id,
                'name' => $menu->name,
                'path' => $menu->path,
                'icon' => $menu->icon,
                'children' => $this->nest($menus, $menu->id),
            ])
            ->values()
            ->all();
    }
}
