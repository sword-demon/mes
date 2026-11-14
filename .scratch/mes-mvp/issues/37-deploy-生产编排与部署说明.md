# 37: 生产编排与部署说明

**What to build:** 生产环境有一套独立编排与部署文档，别人照着能把系统跑起来。

**Blocked by:** 01, 34, 36

**Status:** ready-for-agent

- [ ] docker/docker-compose.prod.yml 部署中间件，PHP 应用由服务器 PHP-FPM 运行
- [ ] 生产环境关闭 APP_DEBUG、不自动执行 Seeder、不挂载源码目录
- [ ] 数据库数据卷与上传目录持久化
- [ ] 服务器 Nginx 将 / 指向 React 静态资源、/api 指向 Laravel 应用，单域名部署
- [ ] 部署文档包含环境变量清单、启动顺序、常见问题与端口开放建议
