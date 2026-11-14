# 35: 移动端骨架

**What to build:** 移动端目录与工程骨架就位，为后续扫码报工、扫码检验留好接入点。

**Blocked by:** 01, 03

**Status:** ready-for-agent

- [ ] frontend/mobile 为独立 npm 工程（UniApp + uView Plus），可独立安装与构建
- [ ] 包含登录、生产报工、SN 扫码三个页面的目录与路由占位
- [ ] 请求层复用 /api/v1 契约，具备 Token 注入与登录态恢复能力
- [ ] 移动端与 Web 端共用同一套后端 API，不新增后端接口
- [ ] 本期不实现移动端业务功能，明确在文档中标注
