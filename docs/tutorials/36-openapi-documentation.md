# 36 OpenAPI 接口文档

**目标**：从 Laravel 路由自动生成 OpenAPI 文档，在本地浏览，并让文档中的认证、权限和响应格式与实现一致。

## 1. 文档入口

```text
GET /api-docs
GET /api-docs/openapi.json
```

- `/api-docs`：Swagger UI；
- `/api-docs/openapi.json`：原始 OpenAPI 3.0.3 JSON。

## 2. 自动扫描路由

`OpenApiDocument::build()` 遍历所有 `api/v1/` 路由：

- 用路径生成 `paths`；
- 用 HTTP 方法生成 operation；
- 用路由名前缀生成 tag；
- 用控制器方法名生成 summary；
- 读取 `auth:*` 和 `permission:*` 中间件，生成 security 和权限说明。

因此新增接口后，文档会自动出现，不需要逐条手写路径。

## 3. 统一响应和认证

`OpenApiSchemas` 定义：

```text
ApiResponse
PaginatedApiResponse
PaginationMeta
PaginatedData
```

认证方式：

```yaml
type: http
scheme: bearer
bearerFormat: Sanctum personal access token
```

Swagger UI 支持 `Authorize` 后自动带 Bearer Token。

## 4. 补充业务描述

无法从代码推断的请求体、查询参数放在 `config/openapi.php`，例如：

- 登录请求体；
- BOM/订单创建字段；
- 分页参数；
- 日期范围；
- 设备历史和告警筛选。

规则：能从路由/控制器推断的自动生成；业务字段用配置补充。

## 5. 环境开关

```php
enabledByDefault(string $environment): bool
{
    return in_array($environment, ['local', 'testing'], true);
}
```

生产默认关闭 `/api-docs`，需要时显式设置：

```dotenv
OPENAPI_ENABLED=true
```

并重新执行 `php artisan config:cache`。

## 6. Swagger UI 资源

`SwaggerUiPage` 使用 CDN 加载 Swagger UI；CDN 不可用时页面会显示回退链接，直接打开 OpenAPI JSON。

## 7. 测试

```bash
php artisan test --compact tests/Feature/Api/OpenApiDocumentTest.php
```

重点：

- 本地/测试默认开启，生产默认关闭；
- 文档覆盖所有 `/api/v1` 路由；
- 受保护接口有 Bearer security；
- 权限中间件出现在描述中；
- 分页接口引用分页 schema；
- 配置的请求体/参数出现在文档里；
- 关闭后返回 404。

## 练习

新增一个带 `permission:demo.index` 的接口，重新打开 `/api-docs`，确认：

1. 路径自动出现；
2. summary 来自控制器方法名；
3. 描述里出现所需权限；
4. Swagger UI 能携带 Token 调用。
