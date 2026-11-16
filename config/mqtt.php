<?php

return [
    'host' => env('MQTT_HOST', '127.0.0.1'),
    'port' => (int) env('MQTT_PORT', 11883),
    'factory' => env('MQTT_FACTORY', 'F001'),
    'client_id' => env('MQTT_CLIENT_ID', 'mes-consumer'),
    'username' => env('MQTT_USERNAME'),
    'password' => env('MQTT_PASSWORD'),
];
