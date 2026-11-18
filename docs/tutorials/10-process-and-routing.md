# 10 工序与工艺路线

**目标**：配置工序和工艺路线，审核后供生产订单锁定，路线顺序成为后续工序流转的依据。

## 1. 三个模型

```text
Process             工序主数据
ProcessRoute        工艺路线（属于产品）
RouteOperation      路线中的一道工序（顺序 + 标准工时 + 参数）
```

`Process` 字段：

```text
code / name / type / standard_hours
requires_inspection / description / status
```

`requires_inspection` 决定后续工序完成时是否必须经过检验。

## 2. 工艺路线状态

与 BOM 一样，路线有：

```php
DRAFT / PENDING / APPROVED
```

只有草稿可编辑：

```php
$route->ensureEditable();
```

提交审核前必须：

- 路线本身启用；
- 产品启用；
- 路线上至少有一道工序；
- 没有停用工序。

## 3. 可选路线

```php
public function scopeSelectable(Builder $query): void
{
    $query->where('status', true)
        ->whereHas('product', fn (Builder $inner) => $inner->where('status', true))
        ->whereDoesntHave('operations.process', fn (Builder $inner) => $inner->where('status', false));
}

public function scopeUsable(Builder $query): void
{
    $query->where('audit_status', self::APPROVED)->selectable();
}
```

`usable` 比 `selectable` 多一个“已审核”条件，生产订单只接受 `usable` 路线。

## 4. 路线工序

`RouteOperationController` 按路线维护工序：

```text
GET    /api/v1/process/routes/{route}/operations
POST   /api/v1/process/routes/{route}/operations
PUT    /api/v1/process/routes/{route}/operations/{operation}
DELETE /api/v1/process/routes/{route}/operations/{operation}
```

每道路线工序包含：

```text
process_id
sequence
standard_hours
parameters
```

路线工序的顺序在后续工序流转中会被用来判断“上道工序是否已流转”。

## 5. Seeder

`ProcessSeeder` 生成：

- 8 道工序：原材料准备、SMT 贴片、回流焊、AOI 检测、组装、功能测试、成品检验、包装入库；
- 3 条路线：
  - P001 V1.0 已审核；
  - P001 V2.0 待审核；
  - P002 V1.0 已审核。

其中 AOI 检测、功能测试、成品检验标记为需检验，用于工序流转的检验卡口。

## 6. 前端

`frontend/web/src/pages/process/routes/index.tsx` 提供：

- 路线列表和审核状态筛选；
- 新增 / 编辑路线；
- 配置路线工序；
- 提交审核 / 审核通过。

路线编辑页通常分两层：路线头信息和路线工序抽屉。

## 7. 测试与验收

```bash
php artisan test --compact tests/Feature/Api/ProcessTest.php
php artisan test --compact tests/Feature/Api/ProcessRouteTest.php
php artisan test --compact tests/Feature/ProcessSeederTest.php
```

验收：

- 工序编码唯一；
- 路线编码唯一，产品版本/路线版本可被订单引用；
- 没有工序的路线不能提交审核；
- 已审核路线不能改内容，只能停用；
- 停用产品/停用工序不会出现在可用路线里；
- 生产订单只能选择 `usable` 路线（#11）。

## 练习

给 P002 新建一条路线，添加“原材料准备 → 组装 → 包装入库”三道工序，提交审核并审核通过。然后：

1. 尝试修改已审核路线；
2. 停用其中一道工序；
3. 查询 `usable=1`，观察路线是否消失。
