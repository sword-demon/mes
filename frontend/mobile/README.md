# MES 移动端

UniApp + Vue 3 + TypeScript + uView Plus 的移动端工程骨架。

## 本期范围

本期只搭工程骨架，不实现移动端业务功能：

- 登录：可用，复用 `POST /api/v1/auth/tokens`，用于验证请求层与登录态。
- 生产报工：页面与路由占位，工单选择、扫码报工在 V1.1 接入。
- SN 扫码：页面与路由占位，扫码设备与业务接口在 V1.1 接入。
- 不新增后端接口，移动端与 Web 端共用同一套 `/api/v1` 契约。

## 页面与路由

| 页面 | 路径 |
| --- | --- |
| 登录 | `pages/login/index` |
| 生产报工占位 | `pages/production/report/index` |
| SN 扫码占位 | `pages/production/scan/index` |

## 开发与构建

```bash
cd frontend/mobile
npm install
npm run dev:h5       # H5 开发，端口 15174，/api 代理到 http://mes.test
npm run typecheck
npm run test
npm run build:h5     # 独立构建 H5 产物到 dist/build/h5
```

当前构建脚本以 H5 作为工程验证目标；App 或小程序打包需在 HBuilderX 中接入，或补充对应平台依赖后再执行。

## 请求层与登录态

- 请求层位于 `src/api/request.ts`，统一解析 `{ code, message, data, timestamp }`，自动注入 `Authorization: Bearer <token>`。
- Token 存在 `uni` 本地存储的 `mes.mobile.token`；应用启动时 `restoreSession()` 读取 Token 并调用 `GET /api/v1/me` 恢复用户信息。
- 接口返回 `401` 时清理本地登录态并重定向到登录页。
- H5 开发环境留空 `VITE_API_URL` 即可走 Vite 代理；App、真机或独立部署时通过环境变量设置绝对地址，例如 `VITE_API_URL=http://mes.test/api/v1`。

## 后续接入点

生产报工与 SN 扫码页面已预留路由和导航入口，后续只需在对应页面接入扫码能力与现有 `/api/v1` 业务接口，不需要调整工程结构。
