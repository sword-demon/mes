<?php

return [

    /*
    |--------------------------------------------------------------------------
    | MQTT Broker
    |--------------------------------------------------------------------------
    |
    | The broker runs inside docker compose. The host connects through the
    | mapped high port; containers reach it by the `emqx` service name.
    |
    */

    'host' => env('MQTT_HOST', '127.0.0.1'),

    'port' => (int) env('MQTT_PORT', 11883),

    'username' => env('MQTT_USERNAME'),

    'password' => env('MQTT_PASSWORD'),

    'client_id' => env('MQTT_CLIENT_ID', 'mes-consumer'),

    /*
    |--------------------------------------------------------------------------
    | Topic Convention
    |--------------------------------------------------------------------------
    |
    | Devices publish to mes/{factory}/{device}/{status|data|alarm}.
    |
    */

    'factory' => env('MQTT_FACTORY', 'F001'),

    'topics' => [
        'status' => 'mes/+/+/status',
        'data' => 'mes/+/+/data',
        'alarm' => 'mes/+/+/alarm',
    ],

];
