<?php

namespace Database\Seeders;

use App\Modules\System\Model\Department;
use App\Modules\System\Model\Menu;
use App\Modules\System\Model\Permission;
use App\Modules\System\Model\Role;
use App\Modules\System\Model\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class SystemSeeder extends Seeder
{
    /**
     * Menu tree from PRD 8.2. `slug` is the permission namespace for the node.
     */
    private const MENUS = [
        ['slug' => 'dashboard', 'name' => '首页驾驶舱', 'path' => '/dashboard', 'icon' => 'DashboardOutlined', 'children' => [
            ['slug' => 'dashboard.overview', 'name' => '生产数据总览', 'path' => '/dashboard/overview'],
            ['slug' => 'dashboard.trend', 'name' => '生产趋势', 'path' => '/dashboard/trend'],
            ['slug' => 'dashboard.quality', 'name' => '质量统计', 'path' => '/dashboard/quality'],
            ['slug' => 'dashboard.equipment', 'name' => '设备状态', 'path' => '/dashboard/equipment'],
        ]],
        ['slug' => 'basic', 'name' => '基础数据', 'icon' => 'DatabaseOutlined', 'children' => [
            ['slug' => 'basic.factories', 'name' => '工厂管理', 'path' => '/basic-data/factories'],
            ['slug' => 'basic.workshops', 'name' => '车间管理', 'path' => '/basic-data/workshops'],
            ['slug' => 'basic.lines', 'name' => '产线管理', 'path' => '/basic-data/lines'],
            ['slug' => 'basic.teams', 'name' => '班组管理', 'path' => '/basic-data/teams'],
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
            ['slug' => 'material.inventory-alerts', 'name' => '库存预警', 'path' => '/material/inventory/alerts'],
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
        'dashboard.overview' => '驾驶舱总览',
        'dashboard.trend' => '驾驶舱生产趋势与产线对比',
        'dashboard.quality' => '驾驶舱质量统计',
        'dashboard.equipment' => '驾驶舱设备状态统计',
        'quality.standards.index' => '检验标准列表',
        'quality.standards.create' => '新增检验标准',
        'quality.standards.update' => '编辑与停用检验标准',
        'quality.defect-codes.index' => '缺陷代码列表',
        'quality.defect-codes.create' => '新增缺陷代码',
        'quality.defect-codes.update' => '编辑与停用缺陷代码',
        'quality.inspections.index' => '检验任务与记录列表',
        'quality.inspections.create' => '创建检验任务',
        'quality.inspections.record' => '录入检验结果与图片',
        'quality.defects.index' => '不良品列表',
        'quality.defects.create' => '登记不良品',
        'quality.defects.process' => '开始处理不良品',
        'quality.defects.rework' => '不良品返工',
        'quality.defects.scrap' => '不良品报废',
        'quality.defects.recheck' => '不良品复检',
        'basic.factories.index' => '工厂列表',
        'basic.factories.create' => '新增工厂',
        'basic.factories.update' => '编辑工厂',
        'basic.factories.delete' => '删除工厂',
        'basic.workshops.index' => '车间列表',
        'basic.workshops.create' => '新增车间',
        'basic.workshops.update' => '编辑车间',
        'basic.workshops.delete' => '删除车间',
        'basic.lines.index' => '产线列表',
        'basic.lines.create' => '新增产线',
        'basic.lines.update' => '编辑产线',
        'basic.lines.delete' => '删除产线',
        'basic.teams.index' => '班组列表',
        'basic.teams.create' => '新增班组',
        'basic.teams.update' => '编辑班组',
        'basic.teams.delete' => '删除班组',
        'basic.employees.index' => '员工列表',
        'basic.employees.create' => '新增员工',
        'basic.employees.update' => '编辑员工',
        'basic.employees.delete' => '删除员工',
        'basic.products.index' => '产品列表',
        'basic.products.create' => '新增产品',
        'basic.products.update' => '编辑产品',
        'basic.products.delete' => '删除产品',
        'basic.materials.index' => '物料列表',
        'basic.materials.create' => '新增物料',
        'basic.materials.update' => '编辑物料',
        'basic.boms.index' => 'BOM列表',
        'basic.boms.create' => '新增BOM',
        'basic.boms.update' => '编辑BOM',
        'basic.boms.submit' => '提交BOM审核',
        'basic.boms.audit' => '审核BOM',
        'basic.processes.index' => '工序列表',
        'basic.processes.create' => '新增工序',
        'basic.processes.update' => '编辑工序',
        'process.routes.index' => '工艺路线列表',
        'process.routes.create' => '新增工艺路线',
        'process.routes.update' => '编辑工艺路线',
        'process.routes.submit' => '提交工艺路线审核',
        'process.routes.audit' => '审核工艺路线',
        'inventory.index' => '库存与流水查询',
        'inventory.alerts.index' => '库存预警列表',
        'inventory.alerts.confirm' => '确认库存预警',
        'inventory.alerts.close' => '关闭库存预警',
        'inventory.inbound' => '库存入库',
        'inventory.outbound' => '库存出库',
        'inventory.adjust' => '库存调整',
        'equipment.ledger.index' => '设备台账列表',
        'equipment.ledger.create' => '新增设备',
        'equipment.ledger.update' => '编辑与停用设备',
        'equipment.monitor.index' => '设备实时监控',
        'equipment.history.index' => '设备历史数据查询',
        'equipment.alarms.index' => '设备告警列表',
        'equipment.alarms.confirm' => '确认设备告警',
        'equipment.alarms.process' => '处理设备告警',
        'equipment.alarms.close' => '关闭设备告警',
        'material.requisitions.index' => '领料单列表',
        'material.requisitions.create' => '创建领料单',
        'material.requisitions.issue' => '仓库发料',
        'material.returns.index' => '退料单列表',
        'material.returns.create' => '创建退料单',
        'material.returns.audit' => '审核退料单',
        'production.orders.index' => '生产订单列表',
        'production.orders.create' => '新增生产订单',
        'production.orders.update' => '编辑生产订单',
        'production.orders.submit' => '提交生产订单审核',
        'production.orders.audit' => '审核生产订单',
        'production.orders.release' => '下达生产订单',
        'production.orders.start' => '生产订单开工',
        'production.orders.pause' => '暂停生产订单',
        'production.orders.resume' => '恢复生产订单',
        'production.orders.complete' => '完成生产订单',
        'production.orders.close' => '关闭生产订单',
        'production.orders.cancel' => '取消生产订单',
        'production.tasks.index' => '生产任务列表',
        'production.tasks.create' => '拆分生产任务',
        'production.tasks.update' => '分配生产任务',
        'production.tasks.pause' => '暂停生产任务',
        'production.tasks.resume' => '恢复生产任务',
        'production.tasks.complete' => '完成生产任务',
        'production.dispatches.index' => '派工记录列表',
        'production.dispatches.create' => '创建派工单',
        'production.dispatches.mine' => '我的待办任务',
        'production.reports.index' => '报工记录列表',
        'production.reports.create' => '提交生产报工',
        'production.reports.reverse' => '冲销生产报工',
        'production.flows.index' => '工序流转列表',
        'production.flows.start' => '开始工序',
        'production.flows.complete' => '完成工序',
        'production.flows.submit' => '提交工序流转',
        'production.flows.receive' => '接收工序流转',
        'production.flows.exception' => '工序异常上报',
        'production.flows.rework' => '工序返工',
        'production.exceptions.index' => '生产异常列表',
        'production.exceptions.create' => '上报生产异常',
        'production.exceptions.process' => '处理生产异常',
        'production.exceptions.close' => '关闭生产异常',
        'production.sns.index' => 'SN列表',
        'production.sns.create' => '生成SN',
        'production.sns.bind' => '绑定SN',
        'production.sns.print' => '打印SN标签',
        'production.sns.void' => '作废SN',
        'traceability.forward.show' => '产品正向追溯',
        'traceability.backward.show' => '物料反向追溯',
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
        'system.menus.index' => '菜单列表',
        'system.menus.create' => '新增菜单',
        'system.menus.update' => '编辑菜单',
        'system.menus.delete' => '删除菜单',
        'system.departments.index' => '部门列表',
        'system.departments.create' => '新增部门',
        'system.departments.update' => '编辑部门',
        'system.departments.delete' => '删除部门',
        'system.logs.index' => '操作日志列表',
    ];

    /**
     * Departments from PRD 8.2 / spec 235: the demo factory's admin backbone.
     */
    private const DEPARTMENTS = [
        ['code' => 'root', 'name' => '演示工厂', 'children' => [
            ['code' => 'production', 'name' => '生产部', 'children' => [
                ['code' => 'workshop-a', 'name' => '一号车间'],
                ['code' => 'workshop-b', 'name' => '二号车间'],
            ]],
            ['code' => 'quality', 'name' => '质量部'],
            ['code' => 'warehouse', 'name' => '仓储部'],
            ['code' => 'equipment', 'name' => '设备部'],
        ]],
    ];

    private const DATA_PERMISSIONS = [
        'data.scope.all' => '全部数据',
        'data.scope.factory' => '本工厂数据',
        'data.scope.line' => '本产线数据',
    ];

    /**
     * Roles and the menu roots they can reach.
     *
     * `api` grants the system API permissions, `scope` the data permission.
     */
    private const ROLES = [
        'super_admin' => ['name' => '超级管理员', 'menus' => ['*'], 'api' => true, 'scope' => 'all'],
        'factory_admin' => ['name' => '工厂管理员', 'menus' => ['*'], 'api' => true, 'scope' => 'factory'],
        'production_manager' => ['name' => '生产经理', 'menus' => ['dashboard', 'basic', 'process', 'production', 'execution', 'quality', 'traceability'], 'api' => ['basic.boms.audit', 'process.routes.index', 'process.routes.audit', 'production.orders.index', 'production.orders.create', 'production.orders.update', 'production.orders.submit', 'production.orders.audit', 'production.orders.release', 'production.orders.start', 'production.orders.pause', 'production.orders.resume', 'production.orders.complete', 'production.orders.close', 'production.orders.cancel', 'production.tasks.index', 'production.tasks.create', 'production.tasks.update', 'production.tasks.pause', 'production.tasks.resume', 'production.tasks.complete', 'production.dispatches.index', 'production.dispatches.create', 'production.dispatches.mine', 'production.reports.index', 'production.reports.create', 'production.reports.reverse', 'production.flows.index', 'production.flows.start', 'production.flows.complete', 'production.flows.submit', 'production.flows.receive', 'production.flows.exception', 'production.flows.rework', 'production.exceptions.index', 'production.exceptions.create', 'production.exceptions.process', 'production.exceptions.close', 'production.sns.index', 'production.sns.create', 'production.sns.bind', 'production.sns.print', 'production.sns.void', 'quality.standards.index', 'quality.defect-codes.index', 'quality.inspections.index', 'quality.defects.index', 'traceability.forward.show', 'traceability.backward.show'], 'scope' => 'factory'],
        'process_engineer' => ['name' => '工艺工程师', 'menus' => ['dashboard', 'basic', 'process'], 'api' => ['basic.materials.create', 'basic.materials.update', 'basic.boms.create', 'basic.boms.update', 'basic.boms.submit', 'basic.processes.create', 'basic.processes.update', 'process.routes.index', 'process.routes.create', 'process.routes.update', 'process.routes.submit'], 'scope' => 'factory'],
        'workshop_director' => ['name' => '车间主任', 'menus' => ['dashboard', 'production', 'execution', 'quality'], 'api' => ['basic.teams.index', 'basic.employees.index', 'production.tasks.index', 'production.dispatches.index', 'production.dispatches.create', 'production.dispatches.mine', 'production.reports.index', 'production.reports.create', 'production.reports.reverse', 'production.flows.index', 'production.flows.start', 'production.flows.complete', 'production.flows.submit', 'production.flows.receive', 'production.flows.exception', 'production.flows.rework', 'production.exceptions.index', 'production.exceptions.create', 'production.exceptions.process', 'production.exceptions.close', 'production.sns.index', 'production.sns.create', 'production.sns.bind', 'production.sns.print', 'quality.standards.index', 'quality.defect-codes.index', 'quality.inspections.index', 'quality.defects.index'], 'scope' => 'line'],
        'production_operator' => ['name' => '生产操作员', 'menus' => ['dashboard', 'production', 'execution', 'material'], 'api' => ['material.requisitions.index', 'material.requisitions.create', 'material.returns.index', 'material.returns.create', 'production.dispatches.mine', 'production.reports.index', 'production.reports.create', 'production.reports.reverse', 'production.flows.index', 'production.flows.start', 'production.flows.complete', 'production.flows.exception', 'production.flows.rework', 'production.sns.index', 'production.sns.create', 'production.sns.bind', 'production.sns.print'], 'scope' => 'line'],
        'quality_inspector' => ['name' => '质检员', 'menus' => ['dashboard', 'quality'], 'api' => ['production.flows.index', 'quality.standards.index', 'quality.defect-codes.index', 'quality.inspections.index', 'quality.inspections.create', 'quality.inspections.record', 'quality.defects.index', 'quality.defects.create', 'quality.defects.process', 'quality.defects.rework', 'quality.defects.scrap', 'quality.defects.recheck'], 'scope' => 'line'],
        'quality_manager' => ['name' => '质检主管', 'menus' => ['dashboard', 'quality', 'traceability'], 'api' => ['quality.standards.index', 'quality.standards.create', 'quality.standards.update', 'quality.defect-codes.index', 'quality.defect-codes.create', 'quality.defect-codes.update', 'quality.inspections.index', 'quality.inspections.create', 'quality.inspections.record', 'quality.defects.index', 'quality.defects.create', 'quality.defects.process', 'quality.defects.rework', 'quality.defects.scrap', 'quality.defects.recheck', 'traceability.forward.show', 'traceability.backward.show'], 'scope' => 'factory'],
        'warehouse_manager' => ['name' => '仓库管理员', 'menus' => ['dashboard', 'material', 'basic', 'execution', 'traceability'], 'api' => ['inventory.index', 'inventory.alerts.index', 'inventory.alerts.confirm', 'inventory.alerts.close', 'inventory.inbound', 'inventory.outbound', 'inventory.adjust', 'material.requisitions.index', 'material.requisitions.issue', 'material.returns.index', 'material.returns.audit', 'production.sns.index', 'production.sns.create', 'production.sns.bind', 'production.sns.print', 'production.sns.void', 'traceability.forward.show', 'traceability.backward.show'], 'scope' => 'factory'],
        'equipment_manager' => ['name' => '设备管理员', 'menus' => ['dashboard', 'equipment'], 'api' => ['equipment.ledger.index', 'equipment.ledger.create', 'equipment.ledger.update', 'equipment.monitor.index', 'equipment.history.index', 'equipment.alarms.index', 'equipment.alarms.confirm', 'equipment.alarms.process', 'equipment.alarms.close'], 'scope' => 'factory'],
    ];

    public function run(): void
    {
        $menuPermissions = $this->seedMenus();
        $permissions = $this->seedPermissions();
        $departments = $this->seedDepartments();
        $this->seedRoles($menuPermissions, $permissions);
        $this->seedUsers($departments);
    }

    /**
     * @return array<string, int> department ids keyed by code
     */
    private function seedDepartments(): array
    {
        $ids = [];

        $walk = function (array $nodes, ?int $parentId, int $sort) use (&$walk, &$ids): void {
            foreach (array_values($nodes) as $index => $node) {
                $department = Department::query()->updateOrCreate(
                    ['code' => $node['code']],
                    ['parent_id' => $parentId, 'name' => $node['name'], 'sort' => $sort + $index],
                );

                $ids[$node['code']] = $department->id;

                if (! empty($node['children'])) {
                    $walk($node['children'], $department->id, 0);
                }
            }
        };

        $walk(self::DEPARTMENTS, null, 0);

        return $ids;
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

            $api = $definition['api'];

            if (is_array($api) && in_array('dashboard', $definition['menus'], true)) {
                foreach (array_keys(self::API_PERMISSIONS) as $apiCode) {
                    if (! str_starts_with($apiCode, 'dashboard.')) {
                        continue;
                    }

                    $codes[] = $apiCode;
                    $codes[] = "button.{$apiCode}";
                }
            }

            if (in_array('basic', $definition['menus'], true)) {
                // These roles see the basic-data menus, so they need the read
                // permissions behind them; writes are granted per role below.
                foreach (array_keys(self::API_PERMISSIONS) as $apiCode) {
                    if (str_starts_with($apiCode, 'basic.') && str_ends_with($apiCode, '.index')) {
                        $codes[] = $apiCode;
                    }
                }
            }

            if ($api === true) {
                foreach (array_keys(self::API_PERMISSIONS) as $apiCode) {
                    $codes[] = $apiCode;
                    $codes[] = "button.{$apiCode}";
                }
            } elseif (is_array($api)) {
                foreach ($api as $apiCode) {
                    $codes[] = $apiCode;
                    $codes[] = "button.{$apiCode}";
                }
            }

            $codes[] = "data.scope.{$definition['scope']}";

            $role->permissions()->sync(
                array_values(array_filter(array_map(fn (string $code) => $permissions[$code] ?? null, $codes))),
            );
        }
    }

    /**
     * @param  array<string, int>  $departments
     */
    private function seedUsers(array $departments): void
    {
        $seed = [
            ['超级管理员', 'admin@mes.test', 'super_admin', 'root', '系统管理员'],
            ['工厂管理员', 'factory@mes.test', 'factory_admin', 'root', '厂长'],
            ['生产经理', 'production@mes.test', 'production_manager', 'production', '生产经理'],
            ['工艺工程师', 'process@mes.test', 'process_engineer', 'production', '工艺工程师'],
            ['车间主任', 'workshop@mes.test', 'workshop_director', 'workshop-a', '车间主任'],
            ['质检主管', 'quality.manager@mes.test', 'quality_manager', 'quality', '质检主管'],
        ];

        $groups = [
            'operator' => [8, 'production_operator', '生产操作员', 'workshop-a', '操作工'],
            'quality' => [4, 'quality_inspector', '质检员', 'quality', '检验员'],
            'warehouse' => [2, 'warehouse_manager', '仓库管理员', 'warehouse', '仓管员'],
            'equipment' => [2, 'equipment_manager', '设备管理员', 'equipment', '设备工程师'],
        ];

        foreach ($groups as $prefix => [$count, $roleCode, $label, $department, $position]) {
            for ($i = 1; $i <= $count; $i++) {
                $seed[] = ["{$label}{$i}", "{$prefix}{$i}@mes.test", $roleCode, $department, $position];
            }
        }

        $password = $this->demoPassword();

        foreach ($seed as $index => [$name, $email, $roleCode, $departmentCode, $position]) {
            $user = User::query()->firstOrCreate(
                ['email' => $email],
                [
                    'username' => strstr($email, '@', true) ?: $email,
                    'name' => $name,
                    'phone' => '138'.str_pad((string) ($index + 1), 8, '0', STR_PAD_LEFT),
                    'department_id' => $departments[$departmentCode] ?? null,
                    'position' => $position,
                    'password' => $password,
                    'status' => true,
                ],
            );

            $user->roles()->syncWithoutDetaching([Role::query()->where('code', $roleCode)->value('id')]);
        }
    }

    /**
     * Seeding only runs for local demos, where a shared password is intentional.
     *
     * Outside local/testing the operator must supply SEED_PASSWORD; a silently
     * weak default in a shared environment is exactly the leak we must avoid.
     */
    private function demoPassword(): string
    {
        $password = config('app.seed_password');

        if (is_string($password) && $password !== '') {
            return $password;
        }

        if (app()->environment(['local', 'testing'])) {
            return 'password';
        }

        throw new RuntimeException('请通过 SEED_PASSWORD 指定演示账号密码后再执行 Seeder');
    }
}
