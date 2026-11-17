<?php

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

return [
    'enabled' => env('OPENAPI_ENABLED', in_array(env('APP_ENV', 'production'), ['local', 'testing'], true)),

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
    ],

    'parameters' => [
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
