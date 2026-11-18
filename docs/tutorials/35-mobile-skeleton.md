# 35 移动端骨架

**目标**：建立独立 UniApp 移动端工程，复用 `/api/v1` 契约，为后续扫码报工和扫码检验留出入口。

## 1. 工程结构

```text
frontend/mobile/
  src/api/
  src/stores/
  src/pages/login/
  src/pages/production/report/
  src/pages/production/scan/
  src/pages.json
  src/manifest.json
```

技术栈：

```text
UniApp + Vue 3 + TypeScript + uView Plus
```

## 2. 页面

当前只实现登录，两个业务页是占位：

```text
pages/login/index
pages/production/report/index
pages/production/scan/index
```

`pages.json` 注册路由，后续扫码报工不需要调整工程结构。

## 3. 请求层

`src/api/request.ts`：

- `VITE_API_URL` 为空时使用 `/api/v1`；
- 自动加 `Accept`、`Content-Type`；
- 有 Token 时加 `Authorization: Bearer`；
- 统一解析 `{ code, message, data, timestamp }`；
- 401 清理本地登录态并跳登录；
- 网络失败返回可读错误。

## 4. 登录态

Token 存在 `uni` 本地存储 `mes.mobile.token`。应用启动时：

```ts
restoreSession()
  → hydrateSession()
  → 有 Token 就请求 /me
  → 成功更新用户
  → 401 清理
  → 网络错误保留本地 Token
```

这样离线打开时不会因为一次网络失败就把用户踢下线。

## 5. 开发与构建

```bash
cd frontend/mobile
npm install
npm run dev:h5
npm run dev:app
npm run typecheck
npm test
npm run build:h5
npm run build:app
```

H5 端口和代理配置见 `frontend/mobile/README.md`；App 产物用 HBuilderX 导入 `dist/build/app`。

## 6. 本期边界

- 不新增后端接口；
- 生产报工和 SN 扫码暂不实现业务；
- 小程序依赖未接入；
- 正式发布前需要配置 `manifest.json` 的 appid。

## 7. 测试

```bash
cd frontend/mobile
npm test -- src/api/request.test.ts src/stores/auth.test.ts
npm run typecheck
```

验收：

- 独立安装/构建；
- 登录能拿到 Token；
- 刷新 H5 能恢复登录态；
- 401 跳登录；
- 占位页可导航；
- 与 Web 共用同一套 API。

## 练习

在 H5 中登录后刷新页面，观察 `mes.mobile.token` 是否保留；再手动改坏 Token，验证 401 清理逻辑。
