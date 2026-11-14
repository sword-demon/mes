# 01: 编排中间件与设备模拟器

**What to build:** 开发者在宿主机一条命令拉起 MySQL 8、Redis 7、EMQX 与设备数据模拟发布器，并让 Laravel 侧能连上它们，全程不占用宿主机已被占用的常用端口。

**Blocked by:** None (can start immediately)

**Status:** done

- [x] docker compose up -d 一次拉起 MySQL(13307)、Redis(16379)、EMQX(11883/11888, Dashboard 18083) 与设备模拟发布器，均为高位端口映射，容器内保持默认端口
- [x] MySQL 数据卷持久化，容器重建不丢数据；down -v 可彻底清理
- [x] compose 服务之间用服务名互访（mysql/redis/emqx），宿主机侧用 127.0.0.1:映射端口
- [x] 宿主机 .env 按方案 A（127.0.0.1:13307）配置且与 compose 内变量约定一致；切换为方案 B（宿主机已有 MySQL）仅需改 DB_HOST/DB_PORT
- [x] 提供 .env.docker.example 变量模板与常用命令清单（起停、看日志、重置数据卷）
