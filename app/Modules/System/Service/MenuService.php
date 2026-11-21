<?php

namespace App\Modules\System\Service;

use App\Core\Exceptions\ApiException;
use App\Modules\System\Model\Menu;
use App\Modules\System\Model\Permission;
use App\Modules\System\ValueObject\OperationLogEntry;
use Illuminate\Support\Facades\DB;

class MenuService
{
    public function __construct(private readonly OperationLogService $operationLogs) {}

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
        return DB::transaction(function () use ($data): Menu {
            $menu = Menu::query()->create($data);

            $this->operationLogs->record(new OperationLogEntry(
                action: 'system.menu.create',
                actionName: '新增菜单',
                objectType: '菜单',
                objectId: $menu->id,
                objectName: $menu->name,
            ));

            return $menu;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Menu $menu, array $data): Menu
    {
        return DB::transaction(function () use ($menu, $data): Menu {
            $before = $this->snapshot($menu);
            $menu->update($data);

            $this->operationLogs->record(new OperationLogEntry(
                action: 'system.menu.update',
                actionName: '编辑菜单',
                objectType: '菜单',
                objectId: $menu->id,
                objectName: $menu->name,
                before: $before,
                after: $this->snapshot($menu),
            ));

            return $menu;
        });
    }

    public function delete(Menu $menu): void
    {
        if ($menu->children()->exists()) {
            throw new ApiException('存在子菜单，不能删除', 403, status: 403);
        }

        DB::transaction(function () use ($menu): void {
            $name = $menu->name;
            $menu->delete();

            $this->operationLogs->record(new OperationLogEntry(
                action: 'system.menu.delete',
                actionName: '删除菜单',
                objectType: '菜单',
                objectId: $menu->id,
                objectName: $name,
            ));
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Menu $menu): array
    {
        return [
            '名称' => $menu->name,
            '上级菜单' => $menu->parent_id === null
                ? null
                : Menu::query()->whereKey($menu->parent_id)->value('name'),
            '权限' => $menu->permission_id === null
                ? null
                : Permission::query()->whereKey($menu->permission_id)->value('code'),
            '路径' => $menu->path,
            '图标' => $menu->icon,
            '排序' => $menu->sort,
        ];
    }
}
