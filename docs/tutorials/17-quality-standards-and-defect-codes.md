# 17 检验项目、标准与缺陷代码

**目标**：维护检验项目、上下限标准和缺陷代码库，为检验任务自动判定提供依据。

## 1. 数据模型

```text
InspectionItem 1 ── 1 InspectionStandard
DefectCode（独立主数据）
```

检验项目：

```text
code / name / unit / status
```

标准：

```text
standard_value / min_value / max_value
```

DefectCode：

```text
code / name / category / status
```

## 2. 核心规则

- 检验项目编码唯一；
- 每个启用项目必须有标准；
- `min_value ≤ standard_value ≤ max_value`；
- 停用项目不能创建新的检验任务；
- 缺陷代码带分类（外观、电气、尺寸等）。

`InspectionItemService::save()` 在保存项目和标准时保持区间约束，避免只改一半导致范围反转。

## 3. 接口

```text
GET  /api/v1/quality/inspection-items
POST /api/v1/quality/inspection-items
PUT  /api/v1/quality/inspection-items/{item}

GET  /api/v1/quality/defect-codes
POST /api/v1/quality/defect-codes
PUT  /api/v1/quality/defect-codes/{defect}
```

对应权限：

```text
quality.standards.index / create / update
quality.defect-codes.index / create / update
```

## 4. Seeder

`QualitySeeder` 生成：

| 编码 | 名称 | 标准 | 范围 |
| --- | --- | ---: | --- |
| VOLTAGE | 工作电压 | 5 V | 4.8–5.2 |
| TEMPERATURE | 工作温度 | 25 ℃ | 0–60 |
| LENGTH | 产品长度 | 100 mm | 99–101 |

缺陷代码：D001 表面划伤、D002 电压异常、D003 长度超差。

## 5. 前端

`frontend/web/src/pages/quality/standards/index.tsx` 使用标签页：

- 检验标准：项目 + 上下限；
- 缺陷代码：分类维护。

## 6. 测试

```bash
php artisan test --compact tests/Feature/QualityStandardServiceTest.php
php artisan test --compact tests/Feature/QualitySeederTest.php
```

验收：

- 项目编码唯一；
- 标准区间不能反转；
- 停用项目不能再创建任务；
- 缺陷代码带分类；
- 权限不足返回 403。

## 练习

新增“外观检查”项目，标准值 1、最小值 1、最大值 1。尝试把最大值改成 0，观察服务端是否拒绝。

## 向下衔接

检验任务会复制一批启用的检验项目和标准，保证后续标准修改不会影响已创建任务。
