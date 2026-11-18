# 28 生产异常闭环

**目标**：上报设备、物料、工艺、质量、人员异常，支持图片，并从上报流转到处理、关闭。

## 1. 异常类型

```text
equipment  设备
material   物料
process    工艺
quality    质量
personnel  人员
```

状态：

```text
reported → processing → closed
```

## 2. 上报

```text
POST /api/v1/production/exceptions
```

请求可以关联：

- 生产订单；
- 工序执行记录；
- 异常类型；
- 描述；
- 图片。

校验：

- 工序记录属于指定订单；
- 至少能推导出订单或工序；
- 图片保存失败时回滚数据库并删除已保存文件。

异常编码：

```text
EXC-{ULID}
```

## 3. 处理与关闭

```text
POST /exceptions/{exception}/process
POST /exceptions/{exception}/close
```

处理必须填写处理结果；关闭要求已经处理且处理结果非空。

所有状态变化在事务中锁定异常记录，防止重复处理。

## 4. 列表和选项

```text
GET /production/exceptions
GET /production/exceptions/options
```

支持类型、状态、订单、时间筛选；选项接口可按订单/工序记录补全表单下拉。

## 5. 前端

`frontend/web/src/pages/execution/exceptions/index.tsx` 提供上报表单、图片上传、处理、关闭和列表筛选。

## 6. 测试

```bash
php artisan test --compact tests/Feature/Api/ProductionExceptionTest.php
```

验收：

- 工序记录不属于订单时拒绝；
- 上报后状态 `reported`；
- 处理必须填处理结果；
- 未处理不能关闭；
- 图片失败不留下半条异常；
- 列表筛选正确；
- 未登录/无权限返回 401/403。

## 练习

上报一条“设备异常”，关联一个工序记录并上传图片，然后：

1. 直接关闭；
2. 填写处理结果；
3. 再关闭；
4. 重复处理已关闭异常。
