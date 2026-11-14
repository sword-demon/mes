# 中间件编排（docker compose）

PHP 后端不进容器，由宿主机 Herd 运行；compose 只负责 MySQL、Redis、EMQX 与设备模拟发布器。

## 端口

| 服务 | 容器内 | 宿主机 |
| --- | --- | --- |
| MySQL | 3306 | 13307 |
| Redis | 6379 | 16379 |
| EMQX MQTT | 1883 | 11883 |
| EMQX WebSocket | 8883 | 11888 |
| EMQX Dashboard | 18083 | 18083 |

端口均可用环境变量覆盖：`MYSQL_HOST_PORT`、`REDIS_HOST_PORT`、`MQTT_HOST_PORT`、`MQTT_WS_HOST_PORT`、`EMQX_DASHBOARD_HOST_PORT`。

## 服务互访

- 容器之间：`mysql:3306`、`redis:6379`、`emqx:1883`（compose 服务名）。
- 宿主机应用：`127.0.0.1:13307`、`127.0.0.1:16379`、`127.0.0.1:11883`。

## 常用命令

```bash
# 启动
docker compose --env-file .env -f docker/docker-compose.yml up -d

# 查看状态
docker compose --env-file .env -f docker/docker-compose.yml ps

# 看日志
docker compose --env-file .env -f docker/docker-compose.yml logs -f emqx
docker compose --env-file .env -f docker/docker-compose.yml logs -f simulator

# 停止（保留数据）
docker compose --env-file .env -f docker/docker-compose.yml down

# 停止并彻底清理数据卷
docker compose --env-file .env -f docker/docker-compose.yml down -v

# 首次启动后初始化数据库
php artisan migrate --seed
```

## 数据持久化

MySQL、Redis、EMQX 均使用命名卷（`mes_mysql-data`、`mes_redis-data`、`mes_emqx-data`、`mes_emqx-log`）。容器重建不丢数据，`down -v` 才会删除。

## 连接配置

把 `.env.docker.example` 中方案 A 的变量合并进根目录 `.env`，然后 `php artisan config:clear`。

方案 B（复用宿主机已有 MySQL）只需把 `DB_PORT` 改为 `13306`，其余不变。

`--env-file .env` 让 compose 与 Laravel 读取同一份变量，`DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` 只在一处维护。

## 设备模拟器

模拟器按 `mes/{factory}/{device}/status|data|alarm` 发布消息，消息体含 `device_code`、`timestamp`、`status`、`temperature`、`speed`、`pressure`、`output`。

可调环境变量：`MQTT_FACTORY`、`MQTT_DEVICES`（逗号分隔）、`MQTT_INTERVAL`（秒）。
