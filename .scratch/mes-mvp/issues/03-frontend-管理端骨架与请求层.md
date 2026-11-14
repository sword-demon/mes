# 03: 管理端骨架与请求层

**What to build:** React 管理端能跑起来并打通后端：登录页提交即拿到 Token，请求层统一带上凭证与错误处理，路由与布局骨架可用。

**Blocked by:** 01, 02

**Status:** done

- [x] frontend/web 以 React 19 + TypeScript + Vite + Ant Design + Zustand + react-query 组织，加入 Ant Design 与 ECharts
- [x] Vite dev server 将 /api 代理到 http://mes.test，不再指向 localhost:8000
- [x] Axios 请求层统一注入 Token、解析统一响应格式、401 时清理登录态并跳登录
- [x] 登录页可调用 POST /auth/tokens，登录成功后可调用 GET /me 拿到当前用户信息
- [x] 建立 layouts / router / pages / api / stores / types / utils 目录骨架
