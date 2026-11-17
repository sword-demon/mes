<?php

namespace App\Core\OpenApi;

final class OpenApiSchemas
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return [
            'ApiResponse' => [
                'type' => 'object',
                'required' => ['code', 'message', 'data', 'timestamp'],
                'properties' => [
                    'code' => [
                        'type' => 'integer',
                        'description' => '业务状态码；成功为 200，失败与 HTTP 状态码保持一致',
                        'example' => 200,
                    ],
                    'message' => [
                        'type' => 'string',
                        'description' => '结果说明；成功时为 success',
                        'example' => 'success',
                    ],
                    'data' => [
                        'nullable' => true,
                        'description' => '业务数据；分页接口为 PaginatedData，校验失败时为 errors 详情，无数据时为 null',
                    ],
                    'timestamp' => [
                        'type' => 'integer',
                        'description' => '服务器 Unix 时间戳（秒）',
                        'example' => 1760000000,
                    ],
                ],
            ],
            'PaginatedApiResponse' => [
                'allOf' => [
                    ['$ref' => '#/components/schemas/ApiResponse'],
                    [
                        'type' => 'object',
                        'properties' => [
                            'data' => ['$ref' => '#/components/schemas/PaginatedData'],
                        ],
                    ],
                ],
            ],
            'PaginationMeta' => [
                'type' => 'object',
                'required' => ['current_page', 'per_page', 'total', 'last_page'],
                'properties' => [
                    'current_page' => ['type' => 'integer', 'example' => 1],
                    'per_page' => ['type' => 'integer', 'example' => 15],
                    'total' => ['type' => 'integer', 'example' => 42],
                    'last_page' => ['type' => 'integer', 'example' => 3],
                ],
            ],
            'PaginatedData' => [
                'type' => 'object',
                'required' => ['items', 'meta'],
                'properties' => [
                    'items' => [
                        'type' => 'array',
                        'description' => '当前页数据',
                        'items' => ['type' => 'object'],
                    ],
                    'meta' => ['$ref' => '#/components/schemas/PaginationMeta'],
                ],
            ],
        ];
    }
}
