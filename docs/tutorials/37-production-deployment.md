# 37 生产编排与部署

**目标**：把 Laravel、React、MySQL、Redis、EMQX 部署到一台 Linux 服务器，使用单域名和独立生产编排。

## 1. 部署形态

```text
Nginx 80/443
  ├── /            → frontend/web/dist（React 静态文件）
  ├── /api/        → Laravel public/index.php（PHP-FPM）
  ├── /api-docs    → Laravel（默认关闭）
  └── /storage/    → storage/app/public

MySQL / Redis / EMQX → docker compose（仅本机回环）
Laravel Queue / Scheduler / MQTT Consumer → systemd
```

生产 Compose 只编排中间件，不把 PHP 源码挂进容器。

## 2. 生产 compose

`docker/docker-compose.prod.yml` 的特点：

- 项目名 `mes-prod`；
- 只包含 mysql、redis、emqx；
- 端口默认绑定 `127.0.0.1`；
- 数据卷持久化；
- 三个必需密钥缺失时 Compose 直接拒绝启动：

```text
DB_PASSWORD
MYSQL_ROOT_PASSWORD
EMQX_DASHBOARD_PASSWORD
```

启动：

```bash
docker compose --env-file .env -f docker/docker-compose.prod.yml up -d --wait
```

## 3. 环境变量

生产 `.env` 重点：

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://mes.example.com

DB_HOST=127.0.0.1
DB_PORT=13307
DB_DATABASE=mes
DB_USERNAME=mes
DB_PASSWORD=...

REDIS_HOST=127.0.0.1
REDIS_PORT=16379
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

MQTT_HOST=127.0.0.1
MQTT_PORT=11883
MQTT_FACTORY=F001

OPENAPI_ENABLED=false
```

生产不要设置 `SEED_PASSWORD` 或自动执行演示 Seeder。

## 4. 初始化顺序

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan storage:link

cd frontend/web
npm ci
npm run build
```

首个管理员使用：

```bash
php artisan mes:create-admin
```

而不是演示 Seeder。

## 5. Nginx

`docker/nginx/mes.conf.example`：

- `/` 回退到 `index.html`，支持 React Router；
- `/api`、`/api/`、`/api-docs` 转到 Laravel `index.php`；
- `/storage/` 直接读上传目录；
- `fastcgi_pass` 指向 `php8.4-fpm.sock`；
- 禁止访问点文件。

单域名部署，前端使用相对路径 `/api/v1`，不需要 CORS。

## 6. 常驻进程

用 systemd 管理：

```text
mes-queue       php artisan queue:work
mes-scheduler   php artisan schedule:work
mes-mqtt        php artisan mes:consume-mqtt
```

详细 unit 文件见 `docs/production-deployment.md`。

## 7. 验证

```bash
curl -f https://mes.example.com/up
curl -f https://mes.example.com/api/v1/me
```

检查：

- 静态首页能打开；
- `/api` 返回 JSON 而不是 HTML；
- `/storage` 上传文件可访问；
- `/api-docs` 在生产返回 404；
- MySQL/Redis/EMQX 端口没有暴露到公网；
- Queue/Scheduler/MQTT 进程处于 active。

## 8. 测试

```bash
php artisan test --compact tests/Feature/ProductionDeploymentTest.php
```

重点：

- 生产 compose 只含中间件；
- 必需密钥缺失时拒绝启动；
- 端口默认绑定回环；
- 数据卷持久化；
- Nginx 模板正确转发 SPA/API/存储。

## 练习

复制 `docker/nginx/mes.conf.example` 到服务器 Nginx 配置，把域名和项目路径替换成实际值，然后按顺序验证：

```text
/up → /login → 登录 → /api/v1/me → 上传文件 → /storage/...
```
