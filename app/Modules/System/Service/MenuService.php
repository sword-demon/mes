<?php

namespace App\Modules\System\Service;

use App\Core\Exceptions\ApiException;
use App\Modules\System\Model\Menu;

class MenuService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function tree(): array
    {
        return Menu::toTree(Menu::query()->orderBy('sort')->orderBy('id')->get());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Menu
    {
        return Menu::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Menu $menu, array $data): Menu
    {
        $menu->update($data);

        return $menu;
    }

    public function delete(Menu $menu): void
    {
        if ($menu->children()->exists()) {
            throw new ApiException('存在子菜单，不能删除', 403, status: 403);
        }

        $menu->delete();
    }
}
