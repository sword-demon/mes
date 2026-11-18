# 11 生产订单状态机

**目标**：掌握生产订单从草稿到关闭的合法状态流转，以及订单创建时对 BOM、工艺路线和产线的校验与版本锁定。

## 1. 状态表

`ProductionOrder` 定义九个状态：

```text
draft       草稿
pending     待审核
approved    已审核
released    已下达
in_progress 生产中
paused      已暂停
completed   已完成
closed      已关闭
cancelled   已取消
```

合法转移：

```text
草稿 → 待审核 / 已取消
待审核 → 已审核 / 已取消
已审核 → 已下达 / 已取消
已下达 → 生产中 / 已取消
生产中 → 已暂停 / 已完成 / 已取消
已暂停 → 生产中 / 已取消
已完成 → 已关闭
已关闭、已取消 → 终止
```

## 2. 创建订单时校验什么

`ProductionOrderService::create()` 在同一个事务里：

```php
$product = $this->activeProduct((int) $data['product_id']);
$bom = $this->usableBom((int) $data['bom_id'], $product);
$route = $this->usableRoute((int) $data['route_id'], $product);
$this->ensureActiveLine((int) $data['production_line_id']);

return ProductionOrder::query()->create([
    ...$data,
    'product_version_id' => $bom->product_version_id,
    'bom_version' => $bom->version,
    'route_version' => $route->version,
]);
```

校验规则：

- 产品存在且启用；
- BOM 属于该产品、已审核、启用、产品版本启用；
- 工艺路线属于该产品、已审核、启用、没有停用工序；
- 产线存在且启用；
- 把 BOM 版本和路线版本写成订单快照。

快照的意义：BOM 后来改成 V2.0，也不会影响已经创建的订单。

## 3. 只有草稿能编辑

```php
public function ensureEditable(): void
{
    if ($this->status === self::DRAFT) {
        return;
    }

    throw new ApiException('生产订单已提交，不可修改', 403, status: 403);
}
```

更新时还会重新锁定行，防止并发提交后仍修改订单：

```php
$order = ProductionOrder::query()
    ->lockForUpdate()
    ->whereKey($order->getKey())
    ->firstOrFail();
$order->ensureEditable();
```

## 4. 状态流转

所有动作最终调用：

```php
$locked->transitionTo($target);
```

`transitionTo()` 只允许在 `TRANSITIONS` 表中出现的目标状态，非法转移返回 422。各动作是独立接口：

```text
POST /orders/{order}/submit
POST /orders/{order}/approve
POST /orders/{order}/release
POST /orders/{order}/start
POST /orders/{order}/pause
POST /orders/{order}/resume
POST /orders/{order}/complete
POST /orders/{order}/close
POST /orders/{order}/cancel
```

流转成功后写操作日志，记录订单号、原状态、新状态。

## 5. 前端

`frontend/web/src/pages/production/orders/index.tsx` 提供：

- 状态、产品、产线、日期、订单号筛选；
- 创建和编辑订单；
- 按当前状态显示不同操作按钮；
- 提交审核、审核、下达、开工、暂停、恢复、完成、关闭、取消。

前端按钮只做体验，后端状态机才是唯一规则来源。

## 6. 测试

```bash
php artisan test --compact tests/Feature/Api/ProductionOrderTest.php
```

重点：

- 未审核 BOM / 路线不能建单；
- 停用产品、BOM、路线、产线不能建单；
- 非法状态转移返回 422；
- 已提交订单不能编辑；
- 订单写入 BOM/路线版本快照；
- 取消和关闭后的订单不能被继续流转。

## 练习

创建一张订单并依次执行：

```text
提交 → 审核 → 下达 → 开工 → 暂停 → 恢复 → 完成 → 关闭
```

然后尝试：

1. 跳过审核直接下达；
2. 已完成订单再次开工；
3. 修改已提交订单的计划数量；
4. 观察每一步的错误消息。
