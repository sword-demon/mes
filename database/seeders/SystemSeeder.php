<?php

namespace Database\Seeders;

use App\Modules\System\Model\Menu;
use App\Modules\System\Model\Permission;
use App\Modules\System\Model\Role;
use App\Modules\System\Model\User;
use Illuminate\Database\Seeder;

class SystemSeeder extends Seeder
{
    /**
     * Menu tree from PRD 8.2. `slug` is the permission namespace for the node.
     */
    private const MENUS = [
        ['slug' => 'dashboard', 'name' => '首页驾驶舱', 'path' => '/dashboard', 'icon' => 'DashboardOutlined'],
        ['slug' => 'basic', 'name' => '基础数据', 'icon' => 'DatabaseOutlined', 'children' => [
            ['slug' => 'basic.factories', 'name' => '工厂管理', 'path' => '/basic-data/factories'],
            ['slug' => 'basic.workshops', 'name' => '车间管理', 'path' => '/basic-data/workshops'],
            ['slug' => 'basic.lines', 'name' => '产线管理', 'path' => '/basic-data/lines'],
            ['slug' => 'basic.employees', 'name' => '人员管理', 'path' => '/basic-data/employees'],
            ['slug' => 'basic.products', 'name' => '产品管理', 'path' => '/basic-data/products'],
            ['slug' => 'basic.materials', 'name' => '物料管理', 'path' => '/basic-data/materials'],
            ['slug' => 'basic.boms', 'name' => 'BOM管理', 'path' => '/basic-data/boms'],
            ['slug' => 'basic.processes', 'name' => '工序管理', 'path' => '/basic-data/processes'],
        ]],
        ['slug' => 'process', 'name' => '工艺管理', 'icon' => 'PartitionOutlined', 'children' => [
            ['slug' => 'process.routes', 'name' => '工艺路线', 'path' => '/process/routes'],
        ]],
        ['slug' => 'production', 'name' => '生产管理', 'icon' => 'BuildOutlined', 'children' => [
            ['slug' => 'production.orders', 'name' => '生产订单', 'path' => '/production/orders'],
            ['slug' => 'production.tasks', 'name' => '生产任务', 'path' => '/production/tasks'],
            ['slug' => 'production.dispatch', 'name' => '生产派工', 'path' => '/production/dispatch'],
            ['slug' => 'production.reports', 'name' => '生产报工', 'path' => '/production/reports'],
        ]],
        ['slug' => 'execution', 'name' => '生产执行', 'icon' => 'PlayCircleOutlined', 'children' => [
            ['slug' => 'execution.flows', 'name' => '工序流转', 'path' => '/execution/flows'],
            ['slug' => 'execution.sn', 'name' => 'SN管理', 'path' => '/execution/sn'],
            ['slug' => 'execution.exceptions', 'name' => '生产异常', 'path' => '/execution/exceptions'],
        ]],
        ['slug' => 'quality', 'name' => '质量管理', 'icon' => 'SafetyCertificateOutlined', 'children' => [
            ['slug' => 'quality.standards', 'name' => '检验标准', 'path' => '/quality/standards'],
            ['slug' => 'quality.tasks', 'name' => '检验任务', 'path' => '/quality/tasks'],
            ['slug' => 'quality.records', 'name' => '检验记录', 'path' => '/quality/records'],
            ['slug' => 'quality.defects', 'name' => '不良品管理', 'path' => '/quality/defects'],
        ]],
        ['slug' => 'material', 'name' => '物料管理', 'icon' => 'InboxOutlined', 'children' => [
            ['slug' => 'material.inventory', 'name' => '库存查询', 'path' => '/material/inventory'],
            ['slug' => 'material.inbound', 'name' => '入库管理', 'path' => '/material/inbound'],
            ['slug' => 'material.outbound', 'name' => '出库管理', 'path' => '/material/outbound'],
            ['slug' => 'material.requisitions', 'name' => '领料管理', 'path' => '/material/requisitions'],
            ['slug' => 'material.returns', 'name' => '退料管理', 'path' => '/material/returns'],
        ]],
        ['slug' => 'equipment', 'name' => '设备管理', 'icon' => 'DesktopOutlined', 'children' => [
            ['slug' => 'equipment.ledger', 'name' => '设备台账', 'path' => '/equipment/ledger'],
            ['slug' => 'equipment.monitor', 'name' => '设备监控', 'path' => '/equipment/monitor'],
            ['slug' => 'equipment.alarms', 'name' => '设备告警', 'path' => '/equipment/alarms'],
            ['slug' => 'equipment.history', 'name' => '历史数据', 'path' => '/equipment/history'],
        ]],
        ['slug' => 'traceability', 'name' => '追溯管理', 'icon' => 'ApartmentOutlined', 'children' => [
            ['slug' => 'traceability.forward', 'name' => '产品追溯', 'path' => '/traceability/forward'],
            ['slug' => 'traceability.backward', 'name' => '物料追溯', 'path' => '/traceability/backward'],
        ]],
        ['slug' => 'system', 'name' => '系统管理', 'icon' => 'SettingOutlined', 'children' => [
            ['slug' => 'system.users', 'name' => '用户管理', 'path' => '/system/users'],
            ['slug' => 'system.roles', 'name' => '角色管理', 'path' => '/system/roles'],
            ['slug' => 'system.menus', 'name' => '菜单管理', 'path' => '/system/menus'],
            ['slug' => 'system.departments', 'name' => '部门管理', 'path' => '/system/departments'],
            ['slug' => 'system.logs', 'name' => '操作日志', 'path' => '/system/logs'],
        ]],
    ];

    /**
     * API permissions keyed by permission code.
     */
    private const API_PERMISSIONS = [
        'system.users.index' => '用户列表',
        'system.users.create' => '新增用户',
        'system.users.update' => '编辑用户',
        'system.users.reset-password' => '重置密码',
        'system.users.assign-roles' => '分配角色',
        'system.roles.index' => '角色列表',
        'system.roles.create' => '新增角色',
        'system.roles.update' => '编辑角色',
        'system.roles.delete' => '删除角色',
        'system.permissions.index' => '权限列表',
    ];

    private const DATA_PERMISSIONS = [
        'data.scope.all' => '全部数据',
        'data.scope.factory' => '本工厂数据',
        'data.scope.line' => '本产线数据',
    ];

    /**
     * Roles and the menu roots they can reach. `api` grants the system API permissions.
     */
    private const ROLES = [
        'super_admin' => ['name' => '超级管理员', 'menus' => ['*'], 'api' => true],
        'factory_admin' => ['name' => '工厂管理员', 'menus' => ['*'], 'api' => true],
        'production_manager' => ['name' => '生产经理', 'menus' => ['dashboard', 'basic', 'process', 'production', 'execution', 'quality', 'traceability']],
        'workshop_director' => ['name' => '车间主任', 'menus' => ['dashboard', 'production', 'execution', 'quality']],
        'production_operator' => ['name' => '生产操作员', 'menus' => ['dashboard', 'production', 'execution']],
        'quality_inspector' => ['name' => '质检员', 'menus' => ['dashboard', 'quality', 'traceability']],
        'warehouse_manager' => ['name' => '仓库管理员', 'menus' => ['dashboard', 'material', 'basic', 'traceability']],
        'equipment_manager' => ['name' => '设备管理员', 'menus' => ['dashboard', 'equipment']],
    ];

    public function run(): void
    {
        $menuPermissions = $this->seedMenus();
        $permissions = $this->seedPermissions();
        $this->seedRoles($menuPermissions, $permissions);
        $this->seedUsers();
    }

    /**
     * @return array<string, array<int, string>> permission codes grouped by root menu slug
     */
    private function seedMenus(): array
    {
        $codes = [];

        $walk = function (array $nodes, ?int $parentId, string $rootSlug) use (&$walk, &$codes): void {
            foreach (array_values($nodes) as $sort => $node) {
                $permission = Permission::query()->updateOrCreate(
                    ['code' => "menu.{$node['slug']}"],
                    ['name' => $node['name'], 'type' => 'menu'],
                );

                $menu = Menu::query()->updateOrCreate(
                    ['parent_id' => $parentId, 'name' => $node['name']],
                    [
                        'permission_id' => $permission->id,
                        'path' => $node['path'] ?? null,
                        'icon' => $node['icon'] ?? null,
                        'sort' => $sort,
                    ],
                );

                $codes[$rootSlug][] = $permission->code;

                if (isset($node['path'])) {
                    Permission::query()->updateOrCreate(
                        ['code' => "page.{$node['slug']}"],
                        ['name' => "{$node['name']}页面", 'type' => 'page'],
                    );
                    $codes[$rootSlug][] = "page.{$node['slug']}";
                }

                if (! empty($node['children'])) {
                    $walk($node['children'], $menu->id, $rootSlug);
                }
            }
        };

        foreach (self::MENUS as $root) {
            $walk([$root], null, $root['slug']);
        }

        return $codes;
    }

    /**
     * @return array<string, int>
     */
    private function seedPermissions(): array
    {
        foreach (self::API_PERMISSIONS as $code => $name) {
            Permission::query()->updateOrCreate(['code' => $code], ['name' => $name, 'type' => 'api']);
            Permission::query()->updateOrCreate(['code' => "button.{$code}"], ['name' => $name, 'type' => 'button']);
        }

        foreach (self::DATA_PERMISSIONS as $code => $name) {
            Permission::query()->updateOrCreate(['code' => $code], ['name' => $name, 'type' => 'data']);
        }

        $ids = [];

        foreach (Permission::query()->get(['id', 'code']) as $permission) {
            $ids[$permission->code] = $permission->id;
        }

        return $ids;
    }

    /**
     * @param  array<string, array<int, string>>  $menuPermissions
     * @param  array<string, int>  $permissions
     */
    private function seedRoles(array $menuPermissions, array $permissions): void
    {
        foreach (self::ROLES as $code => $definition) {
            $role = Role::query()->updateOrCreate(
                ['code' => $code],
                ['name' => $definition['name'], 'status' => true],
            );

            $codes = $definition['menus'] === ['*']
                ? array_merge(...array_values($menuPermissions))
                : array_merge(...array_map(fn (string $root) => $menuPermissions[$root] ?? [], $definition['menus']));

            if ($definition['api'] ?? false) {
                foreach (array_keys(self::API_PERMISSIONS) as $apiCode) {
                    $codes[] = $apiCode;
                    $codes[] = "button.{$apiCode}";
                }
            }

            $role->permissions()->sync(
                array_values(array_filter(array_map(fn (string $code) => $permissions[$code] ?? null, $codes))),
            );
        }
    }

    private function seedUsers(): void
    {
        $seed = [
            ['超级管理员', 'admin@mes.test', 'super_admin'],
            ['工厂管理员', 'factory@mes.test', 'factory_admin'],
            ['生产经理', 'production@mes.test', 'production_manager'],
            ['车间主任', 'workshop@mes.test', 'workshop_director'],
        ];

        $groups = [
            'operator' => [8, 'production_operator', '生产操作员'],
            'quality' => [4, 'quality_inspector', '质检员'],
            'warehouse' => [2, 'warehouse_manager', '仓库管理员'],
            'equipment' => [2, 'equipment_manager', '设备管理员'],
        ];

        foreach ($groups as $prefix => [$count, $roleCode, $label]) {
            for ($i = 1; $i <= $count; $i++) {
                $seed[] = ["{$label}{$i}", "{$prefix}{$i}@mes.test", $roleCode];
            }
        }

        foreach ($seed as $index => [$name, $email, $roleCode]) {
            $user = User::query()->firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'phone' => '138'.str_pad((string) ($index + 1), 8, '0', STR_PAD_LEFT),
                    'password' => 'password',
                    'status' => true,
                ],
            );

            $user->roles()->syncWithoutDetaching([Role::query()->where('code', $roleCode)->value('id')]);
        }
    }
}
