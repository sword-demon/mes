# 06 基础数据：工厂、车间、产线、班组与人员

**目标**：维护一套可复用的工厂组织数据，并让停用、父级过滤和删除保护在 API 层生效。

## 数据关系

```text
Factory 1 ── * Workshop 1 ── * ProductionLine
                         └── * Team 1 ── * Employee
```

对应模型：

```text
app/Modules/Organization/Model/
  Factory.php
  Workshop.php
  ProductionLine.php
  Team.php
  Employee.php
```

## 1. 接口与权限

每组资源都有独立的权限码：

```text
GET    /api/v1/basic-data/factories
POST   /api/v1/basic-data/factories
PUT    /api/v1/basic-data/factories/{factory}
DELETE /api/v1/basic-data/factories/{factory}
```

车间、产线、班组、员工的路径同理，权限码分别是：

```text
basic.workshops.*
basic.lines.*
basic.teams.*
basic.employees.*
```

## 2. 编码唯一和父级校验

例如 `StoreWorkshopRequest`：

```php
'factory_id' => ['required', 'integer', Rule::exists('mes_factories', 'id')->where('status', true)],
'code' => ['required', 'string', 'max:64', Rule::unique('mes_workshops', 'code')],
'name' => ['required', 'string', 'max:128'],
```

这表示：

- 车间必须挂在一个启用的工厂下；
- 工厂 ID 不存在或已停用时返回 422；
- 编码重复时返回“编码已存在”。

产线必须属于启用车间，班组必须属于启用车间，员工关联的班组必须启用。

## 3. `EntityService` 统一列表行为

基础数据大多没有单独的 Service，而是复用 `app/Core/Services/EntityService.php`：

- 统一分页，`per_page` 上限 100；
- `status=1` 时过滤停用记录；
- 启用列表会隐藏“父级已停用”的子记录；
- 没有父级的员工仍然可以被下拉框选中；
- `keyword` 支持按配置的列模糊搜索。

例如产线列表：

```php
$this->entities->paginate(
    ProductionLine::class,
    ['workshop'],
    $request->only(['workshop_id', 'status', 'keyword', 'per_page']),
    enabledRelations: ['workshop', 'workshop.parentFactory'],
    filterColumns: ['workshop_id'],
);
```

## 4. 删除保护

`EntityService::delete()` 接收“关系 → 错误消息”映射：

```php
$this->entities->delete($factory, [
    'workshops' => '工厂下还有车间，只能停用',
]);
```

工厂、车间、班组会检查下级引用；有引用时返回 403，提示只能停用。

当前代码中产线和员工控制器仍留有 `shortcut` 注释：业务表尚未完全补引用校验。后续接入生产任务、报工等引用后，应补上“被引用时只能停用”的检查。

## 5. Seeder

`OrganizationSeeder` 的演示数据：

- 1 个工厂：FAC01；
- 2 个车间：WS01、WS02；
- 3 条产线：PL01、PL02、PL03；
- 3 个班组；
- 10 名员工；
- 把演示用户和员工档案关联起来。

所有节点用 `updateOrCreate`，重复执行不会重复插入。

## 6. 前端页面

`frontend/web/src/pages/basic-data/` 下对应：

```text
factories/
workshops/
production-lines/
teams/
employees/
```

页面使用统一的列表 + 弹窗表单模式。停用通过编辑 `status` 完成，不下拉删除有引用的数据。

## 7. 测试与验收

```bash
php artisan test --compact tests/Feature/Api/OrganizationTest.php
php artisan test --compact tests/Feature/OrganizationSeederTest.php
```

验收重点：

- 编码重复返回明确错误；
- 停用父级后，新下拉列表不出现其子记录；
- 员工可没有班组；
- 工厂/车间/班组有下级时不能删除；
- Seeder 连续执行两次数量不增长。

## 练习

新增一个车间，再在它下面新增一条产线，尝试：

1. 用重复编码创建；
2. 挂到停用工厂下；
3. 停用车间后查询启用产线；
4. 删除仍有产线的车间。

观察每一步的 HTTP 状态和 `message`。
