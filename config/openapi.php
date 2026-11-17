<?php

use App\Core\OpenApi\OpenApiConfig;

$perPage = [
    'name' => 'per_page',
    'in' => 'query',
    'required' => false,
    'description' => '每页条数，默认 15',
    'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
];

$startDate = [
    'name' => 'start_date',
    'in' => 'query',
    'required' => false,
    'description' => '起始日期（含）',
    'schema' => ['type' => 'string', 'format' => 'date'],
];

$endDate = [
    'name' => 'end_date',
    'in' => 'query',
    'required' => false,
    'description' => '结束日期（含）',
    'schema' => ['type' => 'string', 'format' => 'date'],
];

$bomCommonProperties = [
    'code' => ['type' => 'string', 'maxLength' => 64, 'description' => 'BOM 编码'],
    'version' => ['type' => 'string', 'maxLength' => 32, 'description' => 'BOM 版本'],
    'remark' => ['type' => 'string', 'maxLength' => 255, 'nullable' => true],
    'status' => ['type' => 'boolean', 'description' => '启用状态'],
];

$bomCreateProperties = [
    'product_version_id' => ['type' => 'integer', 'description' => '产品版本 ID'],
    ...$bomCommonProperties,
];

$orderProperties = [
    'code' => ['type' => 'string', 'maxLength' => 64, 'description' => '生产订单号'],
    'product_id' => ['type' => 'integer', 'description' => '产品 ID'],
    'bom_id' => ['type' => 'integer', 'description' => 'BOM ID'],
    'route_id' => ['type' => 'integer', 'description' => '工艺路线 ID'],
    'production_line_id' => ['type' => 'integer', 'description' => '产线 ID'],
    'planned_quantity' => [
        'type' => 'number',
        'minimum' => 0,
        'exclusiveMinimum' => true,
        'description' => '计划数量，必须大于 0',
    ],
    'planned_start_at' => ['type' => 'string', 'format' => 'date-time', 'description' => '计划开始时间'],
    'planned_end_at' => [
        'type' => 'string',
        'format' => 'date-time',
        'nullable' => true,
        'description' => '计划结束时间，不得早于计划开始时间',
    ],
    'remark' => ['type' => 'string', 'maxLength' => 255, 'nullable' => true],
];

$exceptionProperties = [
    'type' => ['type' => 'string', 'description' => '异常类型'],
    'production_order_id' => ['type' => 'integer', 'nullable' => true, 'description' => '关联生产订单 ID'],
    'process_record_id' => ['type' => 'integer', 'nullable' => true, 'description' => '关联工序执行记录 ID'],
    'description' => ['type' => 'string', 'maxLength' => 2000],
];

return [
    'enabled' => env('OPENAPI_ENABLED', OpenApiConfig::enabledByDefault((string) (env('APP_ENV') ?: 'production'))),

    'title' => 'MES 生产管理系统 API',

    'version' => '1.0.0',

    'description' => '所有接口返回统一 JSON 信封：code、message、data、timestamp。分页接口的 data 为 items + meta。业务接口使用 Bearer Token 认证。列表接口支持的筛选参数以对应控制器的校验规则为准。',

    'request_bodies' => [
        'api.auth.tokens.store' => [
            'type' => 'object',
            'required' => ['email', 'password'],
            'properties' => [
                'email' => [
                    'type' => 'string',
                    'format' => 'email',
                    'description' => '登录邮箱',
                ],
                'password' => [
                    'type' => 'string',
                    'format' => 'password',
                    'description' => '登录密码',
                ],
                'device_name' => [
                    'type' => 'string',
                    'maxLength' => 255,
                    'description' => 'Token 设备名，默认 api',
                ],
            ],
        ],
        'api.basic-data.boms.store' => [
            'type' => 'object',
            'required' => ['code', 'version', 'product_version_id'],
            'properties' => $bomCreateProperties,
        ],
        'api.basic-data.boms.update' => [
            'type' => 'object',
            'properties' => $bomCommonProperties,
        ],
        'api.production.orders.store' => [
            'type' => 'object',
            'required' => ['code', 'product_id', 'bom_id', 'route_id', 'production_line_id', 'planned_quantity', 'planned_start_at'],
            'properties' => $orderProperties,
        ],
        'api.production.orders.update' => [
            'type' => 'object',
            'properties' => $orderProperties,
        ],
        'api.production.exceptions.store' => [
            'type' => 'object',
            'required' => ['type', 'description'],
            'properties' => $exceptionProperties,
        ],
    ],

    'parameters' => [
        'api.basic-data.boms.index' => [
            [
                'name' => 'product_version_id',
                'in' => 'query',
                'required' => false,
                'description' => '产品版本 ID',
                'schema' => ['type' => 'integer'],
            ],
            [
                'name' => 'audit_status',
                'in' => 'query',
                'required' => false,
                'description' => '审核状态',
                'schema' => ['type' => 'string'],
            ],
            [
                'name' => 'status',
                'in' => 'query',
                'required' => false,
                'description' => '启用状态',
                'schema' => ['type' => 'boolean'],
            ],
            [
                'name' => 'keyword',
                'in' => 'query',
                'required' => false,
                'description' => 'BOM 编码或版本关键字',
                'schema' => ['type' => 'string'],
            ],
            [
                'name' => 'usable',
                'in' => 'query',
                'required' => false,
                'description' => '仅返回可用 BOM',
                'schema' => ['type' => 'boolean'],
            ],
            $perPage,
        ],
        'api.production.orders.index' => [
            [
                'name' => 'status',
                'in' => 'query',
                'required' => false,
                'description' => '订单状态',
                'schema' => ['type' => 'string'],
            ],
            $startDate,
            $endDate,
            [
                'name' => 'product_id',
                'in' => 'query',
                'required' => false,
                'description' => '产品 ID',
                'schema' => ['type' => 'integer'],
            ],
            [
                'name' => 'production_line_id',
                'in' => 'query',
                'required' => false,
                'description' => '产线 ID',
                'schema' => ['type' => 'integer'],
            ],
            [
                'name' => 'keyword',
                'in' => 'query',
                'required' => false,
                'description' => '生产订单号关键字',
                'schema' => ['type' => 'string'],
            ],
            $perPage,
        ],
        'api.equipments.history' => [
            [
                'name' => 'start_at',
                'in' => 'query',
                'required' => true,
                'description' => '开始时间',
                'schema' => ['type' => 'string', 'format' => 'date-time'],
            ],
            [
                'name' => 'end_at',
                'in' => 'query',
                'required' => true,
                'description' => '结束时间',
                'schema' => ['type' => 'string', 'format' => 'date-time'],
            ],
        ],
        'api.equipments.alarms.index' => [
            [
                'name' => 'equipment_id',
                'in' => 'query',
                'required' => false,
                'description' => '设备 ID',
                'schema' => ['type' => 'integer', 'minimum' => 1],
            ],
            [
                'name' => 'level',
                'in' => 'query',
                'required' => false,
                'description' => '告警级别',
                'schema' => ['type' => 'string'],
            ],
            [
                'name' => 'status',
                'in' => 'query',
                'required' => false,
                'description' => '处理状态',
                'schema' => ['type' => 'string'],
            ],
            $startDate,
            $endDate,
            $perPage,
        ],
        'api.inventory.alerts.index' => [
            [
                'name' => 'material_id',
                'in' => 'query',
                'required' => false,
                'description' => '物料 ID',
                'schema' => ['type' => 'integer', 'minimum' => 1],
            ],
            $perPage,
        ],
        'api.inventory.alerts.options.materials' => [
            [
                'name' => 'keyword',
                'in' => 'query',
                'required' => true,
                'description' => '物料编码或名称关键字',
                'schema' => ['type' => 'string', 'maxLength' => 128],
            ],
        ],
        'api.production.exceptions.index' => [
            [
                'name' => 'type',
                'in' => 'query',
                'required' => false,
                'description' => '异常类型',
                'schema' => ['type' => 'string'],
            ],
            [
                'name' => 'status',
                'in' => 'query',
                'required' => false,
                'description' => '异常状态',
                'schema' => ['type' => 'string'],
            ],
            [
                'name' => 'production_order_id',
                'in' => 'query',
                'required' => false,
                'description' => '生产订单 ID',
                'schema' => ['type' => 'integer', 'minimum' => 1],
            ],
            $startDate,
            $endDate,
            $perPage,
        ],
        'api.production.exceptions.options' => [
            [
                'name' => 'production_order_id',
                'in' => 'query',
                'required' => false,
                'description' => '生产订单 ID',
                'schema' => ['type' => 'integer', 'minimum' => 1],
            ],
            [
                'name' => 'keyword',
                'in' => 'query',
                'required' => false,
                'description' => '订单或工序记录关键字',
                'schema' => ['type' => 'string', 'maxLength' => 100],
            ],
            [
                'name' => 'include',
                'in' => 'query',
                'required' => false,
                'description' => '返回范围',
                'schema' => ['type' => 'string', 'enum' => ['all', 'orders', 'process_records']],
            ],
        ],
        'api.system.login-logs.index' => [
            [
                'name' => 'account',
                'in' => 'query',
                'required' => false,
                'description' => '登录账号',
                'schema' => ['type' => 'string', 'maxLength' => 255],
            ],
            [
                'name' => 'success',
                'in' => 'query',
                'required' => false,
                'description' => '是否登录成功',
                'schema' => ['type' => 'boolean'],
            ],
            $startDate,
            $endDate,
        ],
        'api.system.operation-logs.index' => [
            [
                'name' => 'user_id',
                'in' => 'query',
                'required' => false,
                'description' => '操作用户 ID',
                'schema' => ['type' => 'integer'],
            ],
            [
                'name' => 'action',
                'in' => 'query',
                'required' => false,
                'description' => '操作动作',
                'schema' => ['type' => 'string', 'maxLength' => 64],
            ],
            $startDate,
            $endDate,
        ],
    ],

    'tags' => [
        'auth' => '认证',
        'me' => '当前用户',
        'dashboard' => '驾驶舱',
        'basic-data' => '基础数据',
        'system' => '系统管理',
        'production' => '生产管理',
        'quality' => '质量管理',
        'equipments' => '设备管理',
        'inventory' => '库存管理',
        'materials' => '物料管理',
        'process' => '工艺管理',
        'traceability' => '追溯管理',
    ],
];
