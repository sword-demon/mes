# 32 驾驶舱前端页面

**目标**：把后端统计接口组合成今日卡片、趋势图、质量/设备分布图和最新业务列表，并处理权限、空数据和错误。

## 1. 四个视图共用组件

```text
/dashboard/overview   生产数据总览
/dashboard/trend      生产趋势
/dashboard/quality    质量统计
/dashboard/equipment  设备状态
```

`pages/dashboard/index.tsx` 用 `view` 参数控制显示哪些区块，四个页面复用同一套组件。

## 2. 权限控制请求

每个查询只在有权限时启用：

```ts
const canTrend = showTrend && can(user, 'dashboard.trend');

const trend = useQuery({
    queryKey: ['dashboard', 'production-trend'],
    queryFn: getDashboardTrend,
    enabled: canTrend,
});
```

没有权限时不发请求，也不会显示误导性的空图。

## 3. 六个统计卡片

`overview` 显示：

```text
今日订单 / 计划数量 / 实际产量
合格数量 / 不良数量 / 运行设备
```

数据未返回时显示 `-`，不要先显示 0 再跳变。

## 4. 图表

图表使用 ECharts，选项集中在 `pages/dashboard/chart-options.ts`：

```text
近七天生产趋势       折线面积图
各产线产量对比       柱状图
产品合格率           仪表盘/环形图
设备状态分布         环形图
不良原因分布         环形图
```

组件 `ChartCard` 负责加载、错误和空状态，避免每个图表重复写逻辑。

## 5. 最新列表

四个列表：

```text
最新订单
最新报工
最新异常
最新告警
```

列定义在 `table-columns.tsx`，点击行或“查看全部”跳转到对应业务页面。

## 6. 空状态和错误

- 无数据：显示 `Empty` 或“暂无数据”；
- 接口失败：显示 `Alert`，不清空其他已成功区块；
- 无权限：显示“无 xxx 查看权限”，而不是报错。

## 7. 测试

```bash
cd frontend/web
npm test -- src/pages/dashboard/index.test.tsx
npm run types:check
```

重点：

- 有权限才请求对应接口；
- 权限变化后区块显示变化；
- 空数据不崩溃；
- 最新列表可跳转；
- 设备状态五种计数正确。

## 练习

用一个只有 `dashboard.overview` 权限的角色登录，确认只出现 6 张卡片和最新列表，不出现趋势/质量/设备图。
