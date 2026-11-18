# 08 物料管理

**目标**：维护物料档案，为 BOM、库存、领料和追溯提供统一的物料主数据。

## 物料字段

模型在 `app/Modules/Material/Model/Material.php`，核心字段：

```text
code / name / type / specification / unit
min_stock / safety_stock / batch_managed / status
```

物料类型固定为五种：

```php
public const array TYPES = ['原材料', '半成品', '成品', '辅助材料', '包装材料'];
```

其中：

- `min_stock`：低于该值触发库存预警；
- `safety_stock`：安全库存参考值；
- `batch_managed`：是否必须按批次管理，影响库存和追溯。

## 1. 接口

```text
GET  /api/v1/basic-data/materials
POST /api/v1/basic-data/materials
PUT  /api/v1/basic-data/materials/{material}
```

物料没有删除接口，停用通过 `status=false` 完成，避免历史 BOM、库存和追溯记录失去引用。

列表支持 `type`、`status`、`keyword`、`per_page`。

## 2. 实现

`MaterialController` 使用 `EntityService`：

```php
$this->entities->paginate(
    Material::class,
    [],
    $request->only(['type', 'status', 'keyword', 'per_page']),
    filterColumns: ['type'],
);
```

创建和编辑由 `StoreMaterialRequest` / `UpdateMaterialRequest` 校验，编码唯一。

## 3. Seeder

`MaterialSeeder` 生成 10 种物料，覆盖全部五种类型。包装材料 M009、M010 不启用批次管理，其余物料默认启用，用于演示库存批次和追溯差异。

Seeder 最后会调用 `InventorySeeder`，因为库存行必须依附于物料、仓库和库位。

## 4. 前端页面

`frontend/web/src/pages/basic-data/materials/index.tsx` 提供物料列表、类型筛选、编辑和停用。

物料页面的字段较多，建议前端表单分成三组理解：

1. 基本信息：编码、名称、规格、单位；
2. 类型与批次：类型、`batch_managed`；
3. 库存阈值：最低库存、安全库存。

## 5. 测试

```bash
php artisan test --compact tests/Feature/Api/MaterialTest.php
php artisan test --compact tests/Feature/MaterialSeederTest.php
```

重点：

- 编码唯一；
- 类型只能是五种之一；
- 停用物料不出现在启用下拉；
- 批次管理标记可以维护；
- Seeder 重复执行不重复插入。

## 练习

创建一种“辅助材料”物料，设置最低库存 10、安全库存 30，开启批次管理。然后：

1. 用重复编码创建；
2. 改成非法类型；
3. 停用后查询启用物料；
4. 观察它是否还能出现在 BOM 物料下拉中。

## 向下衔接

物料会被 BOM 子项、库存流水、领料单、退料单和物料追溯引用。物料编码一旦被业务单据使用，应优先停用而不是删除。
