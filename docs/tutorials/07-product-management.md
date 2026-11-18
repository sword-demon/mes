# 07 产品与产品版本

**目标**：维护产品主数据与版本，让 BOM 和生产订单可以引用具体版本。

## 数据模型

```text
Product 1 ── * ProductVersion
```

产品字段包括编码、名称、型号、规格、分类、单位和状态。分类固定为：

```php
public const array CATEGORIES = ['成品', '半成品', '部件'];
```

产品编码全局唯一；版本在同一个产品内维护，版本记录通过 `product_id` 关联产品。

## 1. 产品和版本接口

```text
GET    /api/v1/basic-data/products
POST   /api/v1/basic-data/products
PUT    /api/v1/basic-data/products/{product}
DELETE /api/v1/basic-data/products/{product}

GET    /api/v1/basic-data/products/{product}/versions
POST   /api/v1/basic-data/products/{product}/versions
PUT    /api/v1/basic-data/products/{product}/versions/{version}
```

产品列表支持：

```text
category
status
keyword
per_page
```

## 2. 实现方式

`ProductController` 继续复用 `EntityService`：

```php
return ApiResponse::paginated(
    $this->entities->paginate(
        Product::class,
        [],
        $request->only(['category', 'status', 'keyword', 'per_page']),
        filterColumns: ['category'],
    ),
    ProductResource::class,
);
```

产品删除有版本时会被拒绝：

```php
$this->entities->delete($product, [
    'versions' => '产品下还有版本，只能停用',
]);
```

版本由 `ProductVersionController` 维护，创建版本时以路由中的产品为准。

## 3. Seeder

`ProductSeeder` 预置三种产品：

| 编码 | 名称 | 分类 | 状态 |
| --- | --- | --- | --- |
| P001 | 智能电表 | 成品 | 启用 |
| P002 | 电表外壳 | 部件 | 启用 |
| P003 | 采样电路板 | 半成品 | 停用 |

P001 有两个版本 V1.0、V2.0，同时生成 BOM。P003 用于验证停用产品不会进入可用下拉。

Seeder 还会创建对应的 BOM 与 BOM 子项，因此执行顺序上必须先运行 `MaterialSeeder`。

## 4. 前端

`frontend/web/src/pages/basic-data/products/index.tsx` 提供：

- 产品列表、分类筛选、关键字搜索；
- 新增 / 编辑产品；
- 版本查看与维护；
- 停用产品。

产品编码重复时，后端返回 422 和明确消息，前端直接展示错误。

## 5. 测试

```bash
php artisan test --compact tests/Feature/Api/ProductTest.php
php artisan test --compact tests/Feature/ProductSeederTest.php
```

重点验证：

- 产品编码唯一；
- 分类只接受 `成品/半成品/部件`；
- 有版本的产品不能物理删除；
- 同一产品可以有多个版本；
- 停用产品不会出现在可用下拉。

## 练习

创建产品 `P004`，添加两个版本，然后：

1. 用重复编码创建产品；
2. 给停用产品新增版本；
3. 删除有版本的产品；
4. 查询 `status=1` 的产品，确认停用产品不出现。

## 向下衔接

产品的版本会被 BOM（#09）和工艺路线（#10）引用；生产订单创建时会锁定具体版本。
