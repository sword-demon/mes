# 16 SN 条码管理

**目标**：为产品生成全局唯一 SN，绑定订单、批次、设备和人员，并支持打印、作废和工序追溯。

## 1. SN 格式

格式：

```text
MES + YYYYMMDD + 6 位日序号
```

例如：

```text
MES20261118000001
```

生成逻辑在 `ProductSnService`：

```php
$prefix = 'MES'.$day->format('Ymd');
$latest = ProductSn::query()->where('sn', 'like', $prefix.'%')->orderByDesc('sn')->value('sn');
$sequence = $latest === null ? 1 : ((int) substr((string) $latest, strlen($prefix))) + 1;
```

并发场景下可能算出相同序号，项目通过数据库唯一索引 + 最多 3 次重试解决。

## 2. 生成批次和 SN

接口：

```text
POST /api/v1/production/sns
```

服务会：

- 校验订单状态（已下达/生产中/暂停）；
- 校验累计 SN 不超过订单计划数量；
- 创建 `ProductionBatch`；
- 批量创建 SN；
- 返回批次和 SN 列表。

批次编码：

```text
PO-DEMO-001-L01
```

## 3. 绑定、打印、作废

```text
PUT  /production/sns/{productSn}/bind
GET  /production/sns/{productSn}/label
POST /production/sns/{productSn}/print
POST /production/sns/{productSn}/void
```

绑定至少选择物料批次、设备、人员之一；已绑定相同内容不能重复绑定，物料批次只能绑定一次。

打印分两步：

- `label` 读取标签，不改变状态；
- `print` 记录 `printed_at`。

作废需要填写原因，状态变为 `voided`；后续工序流转会拒绝作废 SN。

## 4. SN 与工序

`ProductSn` 关联：

```text
productionOrder
productionBatch
employee
equipment
processRecords
```

工序记录绑定 SN 后，正向追溯可以按 SN 找到每道工序、人员、设备和检验记录。

## 5. 测试

```bash
php artisan test --compact tests/Feature/Api/ProductSnTest.php
php artisan test --compact tests/Feature/ProductionSeederTest.php
```

重点：

- SN 格式和日序号；
- 重复 SN 被唯一索引拒绝并重试；
- 订单计划数量限制；
- 重复绑定拒绝；
- 作废后不能继续流转；
- 一个批次能查到多个 SN；
- Seeder 至少生成 50 个 SN。

## 练习

对一张已下达订单生成 3 个 SN，然后：

1. 给一个 SN 绑定物料批次；
2. 重复绑定相同批次；
3. 打印标签；
4. 作废一个 SN；
5. 在工序流转页尝试使用作废 SN。
