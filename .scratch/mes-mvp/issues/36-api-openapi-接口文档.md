# 36: OpenAPI 接口文档

**What to build:** 接口有可自动生成、可浏览的文档，展示规范化的一面。

**Blocked by:** 02, 03

**Status:** ready-for-agent

- [ ] 配置 OpenAPI/Swagger 注解或静态定义，覆盖 /api/v1 主要接口
- [ ] 文档可在本地访问，含统一响应格式与认证方式说明
- [ ] 认证方式为 Bearer Token，与实际鉴权实现一致
- [ ] 文档与实现同步：有改接口必须同步文档
- [ ] 生产环境默认关闭文档访问
