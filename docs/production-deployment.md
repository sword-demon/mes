# 生产环境编排与部署

本文描述单台 Linux 服务器上的生产部署。Docker Compose 只负责 MySQL、Redis、EMQX，Laravel 由服务器 PHP-FPM 运行，React 构建产物由 Nginx 直接托管，全站使用一个域名。

## 1. 部署形态

| 组件 | 运行位置 | 对外入口 |
| --- | --- | --- |
| Nginx | 宿主机 | `80/443` |
| React 管理端 | 宿主机静态文件 `frontend/web/dist` | `https://mes.example.com/` |
| Laravel API | 宿主机 PHP 8.4-FPM | `https://mes.example.com/api/v1` |
| 上传文件 | 宿主机 `storage/app/public` | `https://mes.example.com/storage/` |
| MySQL / Redis / EMQX | Docker Compose | 仅本机回环；MQTT 按需开放 |

约定项目目录为 `/var/www/mes`，域名占位为 `mes.example.com`，部署时替换为实际值。前端使用相对路径 `/api/v1`，因此前后端同域，不需要额外 CORS 配置。

## 2. 服务器前置条件

- Linux 服务器（示例为 Ubuntu 24.04），已安装 Docker Engine 与 Compose 2.17+（使用 `up --wait`）。
- Nginx、PHP 8.4-FPM、Composer 2、Node.js 22+、Git。
- PHP 扩展：`mysql`、`redis`、`mbstring`、`xml`、`curl`、`zip`、`bcmath`、`intl`、`openssl`。
- 运行用户：Nginx 与 PHP-FPM 使用 `www-data`；下文所有 artisan 命令在 `/var/www/mes` 下执行。

## 3. 环境变量清单

生产 `.env` 放在项目根目录，Compose 与 Laravel 共用同一份文件：

```dotenv
APP_NAME=MES
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://mes.example.com
FRONTEND_URL=https://mes.example.com
APP_BUSINESS_TIMEZONE=Asia/Shanghai
LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=13307
DB_DATABASE=mes
DB_USERNAME=mes
DB_PASSWORD=请替换为强密码
MYSQL_ROOT_PASSWORD=请替换为另一个强密码

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=16379
REDIS_PASSWORD=null
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

MQTT_HOST=127.0.0.1
MQTT_PORT=11883
MQTT_FACTORY=F001
MQTT_CLIENT_ID=mes-consumer
MQTT_USERNAME=
MQTT_PASSWORD=

# 生产默认关闭接口文档；需要临时查阅时改为 true 并重新缓存配置
OPENAPI_ENABLED=false

# 仅供手工执行演示 Seeder 时使用；生产默认留空
SEED_PASSWORD=

# 仅 Compose 使用
EMQX_DASHBOARD_PASSWORD=请替换为 Dashboard 强密码
MQTT_BIND=127.0.0.1
EMQX_DASHBOARD_HOST_PORT=18083
```

关键约束：

- `APP_ENV=production` 与 `APP_DEBUG=false` 必须同时成立；生产环境禁止开启调试。
- `APP_BUSINESS_TIMEZONE=Asia/Shanghai` 决定业务自然日与接口日期筛选口径；数据库时间与应用运行时保持 UTC，不要修改 `config/app.php` 的 `timezone`。
- `APP_KEY` 用 `php artisan key:generate --force` 生成，之后不要更换，否则已加密数据与登录态失效。
- `DB_PASSWORD`、`MYSQL_ROOT_PASSWORD`、`EMQX_DASHBOARD_PASSWORD` 缺失时，生产 Compose 会直接报错拒绝启动。
- 生产启动流程不执行全量 `DatabaseSeeder`；RBAC 元数据与首个管理员由 `php artisan mes:create-admin` 显式初始化，不填充演示业务数据。
- 不要把 `.env` 提交到 Git，也不要在 Nginx 根目录暴露它。

## 4. 首次部署

### 4.1 准备代码与依赖

```bash
cd /var/www/mes
composer install --no-dev --optimize-autoloader
php artisan key:generate --force
```

### 4.2 启动中间件

```bash
docker compose --env-file .env -f docker/docker-compose.prod.yml up -d --wait
docker compose --env-file .env -f docker/docker-compose.prod.yml ps
```

`docker/docker-compose.prod.yml` 只定义 MySQL、Redis、EMQX，不包含 PHP 应用，也不挂载源码目录。默认沿用项目统一的高位端口（MySQL 13307、Redis 16379、MQTT 11883），避免与服务器既有服务冲突；如需改端口，只改 `.env` 中对应的 `DB_PORT`、`REDIS_PORT`、`MQTT_PORT`。

若服务器已有可用的 MySQL，可只启动 `redis emqx`，并把 `.env` 的 `DB_HOST`、`DB_PORT` 指向现有实例。现有 MySQL 需预先创建 `mes` 库与账号，字符集 `utf8mb4`；否则首次迁移会直接失败。

### 4.3 初始化数据库与存储

```bash
php artisan migrate --force
php artisan storage:link
php artisan mes:create-admin --email=admin@example.com
```

`mes:create-admin` 会初始化菜单、权限与角色（不含演示用户和部门），并创建超级管理员。未传 `--password` 时会生成一次性强密码并打印在终端，登录后应立即修改。需要单独补齐 RBAC 元数据时，执行 `php artisan db:seed --class=AccessControlSeeder --force`。

生产环境默认不执行全量演示 Seeder。只有明确需要演示业务数据时，才设置 `SEED_PASSWORD` 后手工执行一次 `php artisan db:seed --force`；该命令不会出现在启动脚本或系统服务中。

### 4.4 构建前端

```bash
cd frontend/web
npm ci
npm run build
cd ../..
```

构建产物位于 `frontend/web/dist`，Nginx 的 `/` 直接指向该目录。

### 4.5 目录权限与配置缓存

```bash
sudo chown -R "$USER":www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
sudo usermod -aG www-data "$USER"
php artisan optimize
```

执行 `usermod` 后需要重新登录，部署用户才会获得 `www-data` 组权限。这样 PHP-FPM 可以写日志与上传目录，部署用户也能执行 `php artisan down/optimize/up`。

`php artisan optimize` 会缓存配置、路由与事件。之后每次修改 `.env`，都要先 `php artisan optimize:clear` 再重新执行 `php artisan optimize`。

### 4.6 配置 Nginx

```bash
sudo cp docker/nginx/mes.conf.example /etc/nginx/sites-available/mes.conf
sudo editor /etc/nginx/sites-available/mes.conf
sudo ln -s /etc/nginx/sites-available/mes.conf /etc/nginx/sites-enabled/mes.conf
sudo nginx -t
sudo systemctl reload nginx
```

至少修改 `server_name`；若项目目录不是 `/var/www/mes`，同步修改全部绝对路径与 PHP-FPM socket。HTTPS 用 Certbot 配置：

```bash
sudo certbot --nginx -d mes.example.com
```

模板的路由规则：

- `/` 读取 React 静态资源，刷新前端路由时回退到 `index.html`。
- `/api` 与 `/api/` 交给 Laravel 的 `public/index.php`，由 PHP-FPM 处理。
- `/api-docs` 在临时开启 `OPENAPI_ENABLED=true` 时交给 Laravel；生产默认关闭时返回 404。
- `/storage/` 直接读取上传目录。
- `/up` 是 Laravel 健康检查。

### 4.7 启动常驻进程

队列、调度与 MQTT 消费必须由进程管理器常驻。以 systemd 为例：

```ini
# /etc/systemd/system/mes-queue.service
[Unit]
Description=MES queue worker
After=network.target docker.service
Wants=docker.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/mes
ExecStart=/usr/bin/php /var/www/mes/artisan queue:work --sleep=3 --tries=3 --max-time=3600
ExecReload=/usr/bin/php /var/www/mes/artisan queue:restart
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

```ini
# /etc/systemd/system/mes-scheduler.service
[Unit]
Description=MES scheduler
After=network.target docker.service
Wants=docker.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/mes
ExecStart=/usr/bin/php /var/www/mes/artisan schedule:work
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

```ini
# /etc/systemd/system/mes-mqtt.service
[Unit]
Description=MES MQTT consumer
After=network.target docker.service
Wants=docker.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/mes
ExecStart=/usr/bin/php /var/www/mes/artisan mes:consume-mqtt
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now mes-queue mes-scheduler mes-mqtt
sudo systemctl status mes-queue mes-scheduler mes-mqtt
```

### 4.8 启动顺序

1. 填写 `.env`，确认 `APP_ENV=production`、`APP_DEBUG=false`、三个密码变量均已设置。
2. `docker compose ... up -d --wait` 启动 MySQL、Redis、EMQX，并等待健康检查通过。
3. `composer install --no-dev`、`php artisan migrate --force`、`php artisan storage:link`、`php artisan mes:create-admin --email=...`。
4. `npm ci && npm run build` 生成 React 静态资源。
5. 配置并重载 Nginx，申请 HTTPS 证书。
6. `php artisan optimize`，启动 PHP-FPM 与三个 systemd 服务。

## 5. 日常发布

```bash
cd /var/www/mes
php artisan down
git pull
composer install --no-dev --optimize-autoloader
cd frontend/web && npm ci && npm run build && cd ../..
php artisan migrate --force
php artisan optimize
sudo chown -R "$USER":www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
sudo systemctl reload php8.4-fpm
sudo systemctl restart mes-queue mes-scheduler mes-mqtt
php artisan up
```

`queue:work` 与 `mes:consume-mqtt` 不会自动加载新代码，发布后必须重启对应服务。数据库迁移前先备份数据库与 `storage/app/public`。
若本次发布包含菜单、权限或角色定义变更，额外执行 `php artisan db:seed --class=AccessControlSeeder --force`。

## 6. 数据持久化与备份

- MySQL 数据在命名卷 `mes-prod_mysql-data`。
- Redis AOF 在 `mes-prod_redis-data`；EMQX 数据与日志在 `mes-prod_emqx-data`、`mes-prod_emqx-log`。
- 上传文件在宿主机 `storage/app/public`，Nginx 通过 `/storage/` 读取；该目录不随容器重建丢失。
- `docker compose down` 保留命名卷；`docker compose down -v` 会删除中间件数据，生产环境禁止随意执行。

备份示例：

```bash
docker compose --env-file .env -f docker/docker-compose.prod.yml exec -T mysql \
  sh -c 'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' \
  | gzip > "mes-$(date +%F).sql.gz"

tar czf "mes-uploads-$(date +%F).tar.gz" storage/app/public
```

## 7. 端口开放建议

| 端口 | 用途 | 建议 |
| --- | --- | --- |
| `80` / `443` | Nginx | 对公网开放；用 Certbot 申请证书并启用 HTTP→HTTPS 跳转 |
| `22` | SSH | 仅管理来源 IP |
| `11883` | MQTT | 默认只绑定 `127.0.0.1`；真实设备接入时设 `MQTT_BIND=0.0.0.0`，并只放行设备网段 |
| `13307` | MySQL | 不开放，保持 `127.0.0.1` |
| `16379` | Redis | 不开放，保持 `127.0.0.1` |
| `18083` | EMQX Dashboard | 不开放，通过 SSH 隧道访问 |

EMQX Dashboard 隧道：

```bash
ssh -L 18083:127.0.0.1:18083 user@mes.example.com
```

本编排没有内置 MQTT 认证。需要接收真实设备消息时，必须先在 EMQX Dashboard 配置认证，或用防火墙限制 `11883` 的来源地址；不要把未认证的 MQTT 端口直接暴露到公网。

## 8. 验证

```bash
curl -I https://mes.example.com/
curl -fsS https://mes.example.com/up
curl -i -X POST https://mes.example.com/api/v1/auth/tokens \
  -H 'Accept: application/json' -H 'Content-Type: application/json' -d '{}'
```

预期：首页返回 `200`；`/up` 返回健康状态；登录接口返回 Laravel 的 `422` 校验响应，而不是 Nginx 的 `404`。

## 9. 常见问题

**502 Bad Gateway**

检查 `systemctl status php8.4-fpm`，以及 Nginx 中的 socket 是否与 `ls /run/php/` 一致；Ubuntu 常见为 `/run/php/php8.4-fpm.sock`。同时确认 `SCRIPT_FILENAME` 指向 `/var/www/mes/public/index.php`。

**前端刷新返回 404**

确认 `/` 使用 `try_files $uri $uri/ /index.html;`，并已重载 Nginx。

**上传成功但图片 404**

先确认 Nginx 的 `/storage/` alias 指向真实的上传目录 `storage/app/public`，目录存在且 `www-data` 可读，`APP_URL` 是最终 HTTPS 域名。只有通过 `public/storage` 符号链接访问时才需要执行 `php artisan storage:link`；本模板使用 alias，不依赖该链接。

**修改 `.env` 后配置不生效**

生产使用配置缓存。执行 `php artisan optimize:clear && php artisan optimize`，然后 `sudo systemctl reload php8.4-fpm`。

**页面能开，但接口跨域或登录态异常**

同域部署下不应出现 CORS。若通过其他域名访问前端，把 `FRONTEND_URL` 改为前端的完整来源（含协议与端口），并重新缓存配置。

**无法登录或登录后菜单为空**

确认已执行 `php artisan mes:create-admin --email=admin@example.com`。该命令会补齐菜单、权限与超级管理员角色；若邮箱已存在，命令会拒绝重复创建，请改用已有管理员账号或通过用户管理重置密码。

**队列或 MQTT 不消费**

查看 `journalctl -u mes-queue -u mes-mqtt -f`。确认 Redis 健康、`MQTT_HOST=127.0.0.1`、`MQTT_PORT=11883`；远端设备连不上时检查 `MQTT_BIND` 与防火墙。改完代码后执行 `php artisan queue:restart` 并重启 `mes-mqtt`。

**Compose 报缺少密码**

`docker/docker-compose.prod.yml` 对 `DB_PASSWORD`、`MYSQL_ROOT_PASSWORD`、`EMQX_DASHBOARD_PASSWORD` 使用必填校验。补齐 `.env` 后重新执行启动命令。

**生产执行 `migrate:fresh` 被拒绝**

这是预期行为。应用在 `APP_ENV=production` 下禁止破坏性数据库命令，生产只使用 `php artisan migrate --force`。

**Seeder 提示需要 `SEED_PASSWORD`**

这是全量演示 Seeder 的保护，不是首个管理员的初始化路径。生产首个管理员请用 `php artisan mes:create-admin`；确需演示业务数据时，再设置 `SEED_PASSWORD` 并手工执行 `php artisan db:seed --force`。
