# 30 物料反向追溯

**目标**：输入物料批次号，反查所有受影响的生产订单、生产批次和产品 SN。

## 1. 接口

```text
GET /api/v1/traceability/material/{batchNo}
```

实际参数以 `BackwardTraceRequest` 为准。权限：

```text
traceability.backward.show
```

## 2. 从库存出库流水出发

`BackwardTraceService::trace()`：

1. 找到批次对应的 `outbound` 库存流水；
2. 用流水的 `reference_no` 找领料单；
3. 用领料单找生产订单；
4. 找订单下的生产批次和 SN；
5. 按订单聚合实际发料物料；
6. 返回生产批次 → SN 的层级结果。

每一层都按调用者的数据范围过滤：库存流水看仓库所属工厂，领料单与订单看产线，
生产批次与 SN 只保留范围内订单的数据。

关键点：

```text
物料批次 → 出库流水 → 领料单 → 生产订单 → 生产批次 → 产品 SN
```

追溯关系来自真实业务流水，不手工维护。

## 3. 空结果

如果批次没有可见的出库流水，或流水关联的订单没有带 SN 的生产批次，
返回 404，而不是带总数的空结构：

```json
{
  "code": 404,
  "message": "物料批次不存在"
}
```

## 4. 前端

`frontend/web/src/pages/traceability/backward/index.tsx`：

- 输入批次号；
- 展示受影响订单/批次/SN 数量；
- 展开生产批次查看 SN；
- 有正向权限时，可以从 SN 跳到正向追溯。

## 5. 测试

```bash
php artisan test --compact tests/Feature/TraceabilitySeederTest.php
```

验收：

- 批次能反查到受影响订单和 SN；
- 无关联时返回 404；
- 物料用量来自出库流水；
- 生产批次与 SN 层级正确；
- 未登录/无权限返回 401/403。

## 练习

从 `InventorySeeder` 找一个真实批次号查询，记录受影响订单数；再用其中一个 SN 走正向追溯，验证两边订单号一致。
