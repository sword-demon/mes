# 09 BOM 版本与审核锁定

**目标**：维护产品 BOM 和子项，通过审核状态阻止生产订单使用未审核 BOM，并在订单创建时锁定版本。

## 1. BOM 状态

`app/Modules/Bom/Model/Bom.php` 定义三种状态：

```php
DRAFT    = draft
PENDING  = pending
APPROVED = approved
```

流转：

```text
草稿 → 待审核 → 已审核
```

只有 `draft` 可以编辑；提交后内容锁定，但仍允许修改 `status`（停用）。

```php
public const array FIELDS_ALLOWED_WHEN_LOCKED = ['status'];
```

## 2. 数据模型

```text
Bom 1 ── * BomItem
Bom.product_version_id → ProductVersion
Bom.product_id         → Product（由版本自动同步）
```

`Bom::booted()` 在创建时根据 `product_version_id` 写入 `product_id`，避免冗余字段被绕过：

```php
static::creating(function (Bom $bom): void {
    $bom->product_id = ProductVersion::query()
        ->whereKey($bom->product_version_id)
        ->firstOrFail()
        ->product_id;
});
```

## 3. 可用 BOM

生产订单只能使用“已审核、启用、版本和产品也启用”的 BOM：

```php
public function scopeUsable(Builder $query): void
{
    $query->where('audit_status', self::APPROVED)
        ->where('status', true)
        ->whereHas('productVersion', fn (Builder $inner) => $inner
            ->where('status', true)
            ->whereHas('product', fn (Builder $product) => $product->where('status', true)));
}
```

订单创建时会再次调用这个范围，避免下拉选项和真实校验漂移。

## 4. 审核流程

`BomController` 提供：

```text
POST /api/v1/basic-data/boms/{bom}/submit
POST /api/v1/basic-data/boms/{bom}/approve
```

提交审核前：

- 产品版本和产品必须启用；
- BOM 必须至少有一个子项；
- 当前状态必须是 `draft`。

审核通过前：

- 产品版本和产品必须启用；
- 当前状态必须是 `pending`；
- 写入 `audited_at`。

## 5. BOM 子项

`BomItem` 字段：

```text
material_id / quantity / loss_rate
```

子项由 `BomItemController` 维护，要求：

- BOM 仍可编辑；
- 物料存在；
- 同一 BOM 不重复添加同一物料；
- 用量必须大于 0；
- 损耗率不能为负。

## 6. Seeder

`ProductSeeder` 生成三个产品的 BOM：

- P001：已审核 V1.0 BOM、草稿 V2.0 BOM；
- P002：已审核 BOM；
- P003：停用产品下的草稿 BOM。

这样可同时验证“可用 BOM 下拉”和“未审核 BOM 被拒绝”。

## 7. 测试与验收

```bash
php artisan test --compact tests/Feature/Api/BomTest.php
php artisan test --compact tests/Feature/ProductSeederTest.php
```

验收：

- 草稿 BOM 可以编辑子项；
- 提交后不能改内容，只能停用；
- 没有子项不能提交；
- 未审核 BOM 不出现在 `usable=1` 列表；
- 订单创建时不能引用未审核 BOM（订单侧见 #11）。

## 练习

为 P001 新建一个 BOM，添加两个物料子项，完成：

1. 提交审核；
2. 审核通过；
3. 尝试继续修改 BOM 名称；
4. 停用 BOM；
5. 查询 `usable=1`，确认停用 BOM 消失。
