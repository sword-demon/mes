<?php

return [
    'host' => env('MQTT_HOST', '127.0.0.1'),
    'port' => (int) env('MQTT_PORT', 11883),
    'factory' => env('MQTT_FACTORY', 'F001'),
    'client_id' => env('MQTT_CLIENT_ID', 'mes-consumer'),
    'username' => env('MQTT_USERNAME'),
    'password' => env('MQTT_PASSWORD'),
    'clean_session' => (bool) env('MQTT_CLEAN_SESSION', false),
    'max_payload_bytes' => (int) env('MQTT_MAX_PAYLOAD_BYTES', 65536),
];
